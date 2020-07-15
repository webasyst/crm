<?php

class crmMessageSendReplyController extends crmSendEmailController
{
    /**
     * @var array
     */
    protected $message;

    /**
     * @var crmContact
     */
    protected $contact;

    /**
     * @var array
     */
    protected $deal;

    /**
     * @var crmMailMessage
     */
    protected $mail;

    public function preExecute()
    {
        $message = $this->getMessage();
        if ($message['source_id'] > 0 && $message['transport'] != crmMessageModel::TRANSPORT_EMAIL) {
            $source = crmSource::factory($message['source_id']);
            $data = $this->prepareData();
            $result = crmSourceMessageSender::replyToMessage($source, $message, $data);
            if ($result['status'] == 'ok') {
                $this->response = (array)ifset($result['response']);
            } else {
                $this->errors = (array)ifset($result['errors']);
            }
            $this->display();
            exit;
        }
    }

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

    protected function getMessage()
    {
        if ($this->message !== null) {
            return $this->message;
        }

        $id = (int)$this->getParameter('message_id');
        if ($id <= 0) {
            $this->notFound();
        }
        $message = $this->getMessageModel()->getMessage($id);
        if (!$message) {
            $this->notFound();
        }

        if ($message['deal_id'] > 0) {
            $deal = $this->getDealModel()->getDeal($message['deal_id']);
            $has_access = $this->getCrmRights()->deal($deal);
            $message['deal'] = $deal;
        } else {
            $has_access = $this->getCrmRights()->contact($message['contact_id']);
        }
        if (!$has_access) {
            $this->accessDenied();
        }

        $message['reply_allowed'] = $message['source_id'] > 0 ||
            $message['transport'] == crmMessageModel::TRANSPORT_EMAIL;

        if (!$message['reply_allowed']) {
            $this->accessDenied();
        }

        if ($message['original']) {
            $path = $this->getMessageModel()->getEmailSourceFilePath($message['id']);
            $message['original_path'] = $path;
        }

        return $this->message = $message;
    }

    /**
     * @return string
     */
    protected function getRecipientEmail()
    {
        $message = $this->getMessage();
        return trim((string)ifset($message['from']));
    }

    /**
     * @return crmContact
     */
    protected function getRecipientContact()
    {
        if ($this->contact !== null) {
            return $this->contact;
        }
        $message = $this->getMessage();
        return $this->contact = new crmContact((int)$message['contact_id']);
    }

    protected function formEmailMessage($data)
    {
        $user_contact = $this->getUserContact();
        $sender_name = $user_contact->getSenderName();

        if (empty($data['sender_email'])) {
            $data['sender_email'] = key(waMail::getDefaultFrom());
        }

        $from = array($data['sender_email'] => $sender_name);

        $reply_to = array(
            $data['sender_email'] => $sender_name,
        );

        $notification = array(
            'contact'  => $this->getRecipientContact(),
            'email'    => $data['email'],
            'subject'  => $data['subject'],
            'body'     => $data['body'],
            'wa_log'   => true,
            'from'     => $from,
            'reply_to' => $reply_to,
            'sender'   => waMail::getDefaultFrom(),
            'cc'       => $data['cc']
        );

        if ($data['sender_email']) {
            $this->getUserContact()->setSenderEmail($data['sender_email']);
        }

        $notification['attachments'] = $this->formAttachments($data);

        $deal_id = 0;
        if ($this->getDeal()) {
            $deal = $this->getDeal();
            $deal_id = $deal['id'];
            $notification['deal_id'] = $deal['id'];

            if ($deal['source_email']) {
                $notification['reply_to'][$deal['source_email']] = $deal['source_email'];
            }

            $original_message = $this->getOriginalMessage();
            if ($original_message) {
                $notification['in_reply_to'] = $original_message->getMessageId();
                $notification['in_reply_to'] = trim(trim(trim($notification['in_reply_to']), '><'));
                $notification['references'] = $original_message->getReferences();
                $notification['references'][] = $notification['in_reply_to'];
                $notification['references'] = array_unique($notification['references']);
            }
        } else {
            $source_email = $this->emailSource();
            if ($source_email) {
                $notification['reply_to'][$source_email] = $source_email;
            }
        }

        $notification['message_id'] = crmEmailSourceWorker::generateMessageId($deal_id);

        return $notification;
    }

    protected function getDeal()
    {
        if ($this->deal !== null) {
            return $this->deal;
        }

        $id = $this->message['deal_id'];
        if (!$id) {
            $id = $this->getRequest()->post('deal_id');
        }

        if ($id == 'none') {
            return null;
        }
        $id = (int) $id;
        $new_deal = $this->getRequest()->post('deal');
        if(!$id && isset($new_deal['funnel_id']) && isset($new_deal['stage_id'])) {
            $id = $this->getDealModel()->add(array(
                'contact_id'      => (int) $this->contact['id'],
                'status_id'       => 'OPEN',
                'name'            => $this->getRequest()->post('subject'),
                'description'     => $this->getRequest()->post('body'),
                'funnel_id'       => (int) $new_deal['funnel_id'],
                'stage_id'        => (int) $new_deal['stage_id'],
                'user_contact_id' => wa()->getUser()->getId(),
            ));
        }
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

    /**
     * @return crmMailMessage|null
     */
    protected function getOriginalMessage()
    {
        $message = $this->getMessage();
        if (!ifset($message['original_path'])) {
            return null;
        }
        if ($this->mail !== null) {
            return $this->mail;
        }
        return $this->mail = new crmMailMessage($message['original_path']);
    }

    /**
     * @return array
     */
    protected function addNewUploadedFiles()
    {
        $deal = $this->getDeal();
        if ($deal) {
            $contact_id = -$deal['id'];
        } else {
            $contact = $this->getRecipientContact();
            $contact_id = $contact->getId();
        }

        $file_ids = array();
        $file_paths = $this->getUploadedFiles();
        foreach ($file_paths as $file_path) {
            $file_id = $this->getFileModel()->add(array('contact_id' => $contact_id), $file_path);
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
     * @param array $data
     * @return array
     */
    protected function formAttachments($data)
    {
        // existed files
        $exited_files = array();
        $deal = $this->getDeal();
        if ($deal) {
            $exited_files = $deal['files'];
        }

        // attach new uploaded files
        $file_ids = $this->addNewUploadedFiles();
        $attached_files = $this->getFileModel()->getFiles($file_ids);

        // also attach checked existed files
        $file_ids = crmHelper::toIntArray(ifset($data['file_id']));
        foreach ($file_ids as $file_id) {
            if (isset($exited_files[$file_id])) {
                $file = $exited_files[$file_id];
                $attached_files[$file['id']] = $file;
            }
        }

        return $attached_files;
    }
}
