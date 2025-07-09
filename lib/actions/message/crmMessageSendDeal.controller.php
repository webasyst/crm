<?php

class crmMessageSendDealController extends crmSendEmailController
{
    /**
     * @var array
     */
    protected $deal;

    public function execute()
    {
        $result = $this->send();
        if (isset($result['errors'])) {
            $this->errors = $result['errors'];
            return;
        }

        $id = $result['id'];
        $email_message = $result['email_message'];
        if ($id > 0 && isset($email_message['attachments'])) {
            $this->getMessageModel()->setAttachments($id, array_keys($email_message['attachments']));
        }

        $this->response = array(
            'message_id' => $email_message['message_id'],
            'attachment_count' => isset($email_message['attachments']) ? count($email_message['attachments']) : 0
        );
    }

    protected function getDeal()
    {
        if ($this->deal !== null) {
            return $this->deal;
        }

        $id = (int)$this->getRequest()->post('deal_id');
        if ($id <= 0) {
            $this->notFound();
        }
        $deal = $this->getDealModel()->getDeal($id, true);
        if (!$deal) {
            $this->notFound();
        }

        if (!$this->getCrmRights()->deal($deal)) {
            $this->accessDenied();
        }

        $deal = $this->getDealModel()->getDeal($id);

        $deal['source_email'] = crmHelper::getMagicSourceEmail($deal);

        return $this->deal = $deal;
    }

    protected function formEmailMessage($data)
    {
        $deal = $this->getDeal();

        $user_contact = $this->getUserContact();
        $sender_name = $user_contact->getSenderName();

        if (empty($data['sender_email'])) {
            $data['sender_email'] = key(waMail::getDefaultFrom());
        }

        $from = [$data['sender_email'] => $sender_name];

        $reply_to = [];
        if (!empty($deal['source_email'])) {
            $reply_to[$deal['source_email']] = $deal['source_email'];
        } else {
            $source_email = $this->emailSource();
            if ($source_email) {
                $notification['reply_to'][$source_email] = $source_email;
            } else {
                waLog::log('Source email not found on message send deal controller');
            }
        }

        $notification = array(
            'contact'  => $this->getRecipientContact(),
            'email'    => $data['email'],
            'subject'  => $data['subject'],
            'body'     => $data['body'],
            'deal_id'  => $deal['id'],
            'wa_log'   => true,
            'from'     => $from,
            'reply_to' => $reply_to,
            'sender'   => waMail::getDefaultFrom(),
            'cc'       => $data['cc']
        );

        if ($data['sender_email']) {
            $this->getUserContact()->setSenderEmail($data['sender_email']);
        }

        $notification['message_id'] = crmEmailSourceWorker::generateMessageId($deal['id']);
        $notification['attachments'] = $this->formAttachments($deal, $data);

        return $notification;
    }

    /**
     * @param $deal
     * @return array
     */
    protected function addNewUploadedFiles($deal)
    {
        $file_ids = array();
        $file_paths = $this->getUploadedFiles();
        foreach ($file_paths as $file_path) {
            $file_id = $this->getFileModel()->add([
                'contact_id' => -$deal['id'],
                'source_type' => crmFileModel::SOURCE_TYPE_MESSAGE,
            ], $file_path);
            $file_ids[] = $file_id;
            try {
                waFiles::delete($file_path);
            } catch (Exception $e) {

            }
        }
        return $file_ids;
    }

    /**
     * Form attachments
     * @notice Side-effect here
     * @param array $deal
     * @param array $data
     * @return array
     */
    protected function formAttachments($deal, $data)
    {
        // attach new uploaded files
        $file_ids = $this->addNewUploadedFiles($deal);
        $attached_files = $this->getFileModel()->getFiles($file_ids);

        // also attach checked existed files
        $file_ids = crmHelper::toIntArray(ifset($data['file_id']));
        foreach ($file_ids as $file_id) {
            if (isset($deal['files'][$file_id])) {
                $file = $deal['files'][$file_id];
                $attached_files[$file['id']] = $file;
            }
        }

        return $attached_files;
    }
}
