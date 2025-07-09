<?php

class crmMessageSendNewController extends crmSendEmailController
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
     * @var crmDeal
     */
    protected $deal;

    protected $api = false;

    public function execute($api = false)
    {
        $this->api = $api;
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
            'id' => $id,
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

        $has_access = $this->getCrmRights()->contact($message['contact_id']);
        if (!$has_access) {
            $this->accessDenied();
        }

        $message['reply_allowed'] = $message['transport'] == crmMessageModel::TRANSPORT_EMAIL &&
            $message['direction'] == crmMessageModel::DIRECTION_IN;

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
     * @return crmContact
     */
    protected function getRecipientContact()
    {
        if ($this->contact) {
            return $this->contact;
        }

        $id = (int)$this->getParameter('contact_id');

        // For new contact
        $data = array(
            'name'        => trim((string) $this->getParameter('name')),
            'email'       => trim((string) $this->getParameter('email')),
            'crm_user_id' => $this->autoResponsible(),
        );

        if ($id === 0 && $data['name'] && $data['email']) {
            // create new contact
            $contact = new crmContact();
            $contact->save($data);
            return $this->contact = $contact;
        }

        return $this->contact = new crmContact($id);
    }

    /**
     * Does the current user have the option to automatically assign responsibility for the contacts / companies that are being created?
     * If so, the method will return the current user ID
     */
    protected function autoResponsible()
    {
        if (!wa()->getUser()->getSettings('crm', 'contact_create_not_responsible')) {
            return wa()->getUser()->getId();
        }
        return null;
    }

    protected function formEmailMessage($data)
    {
        $user_contact = $this->getUserContact();
        $sender_name = $user_contact->getSenderName();

        if (empty($data['sender_email'])) {
            $data['sender_email'] = key(waMail::getDefaultFrom());
        }

        $from = array($data['sender_email'] => $sender_name);

        $notification = array(
            'contact'  => $this->getRecipientContact(),
            'email'    => $data['email'],
            'subject'  => $data['subject'],
            'body'     => $data['body'],
            'wa_log'   => true,
            'from'     => $from,
            'reply_to' => [],
            'sender'   => waMail::getDefaultFrom(),
            'cc'       => $data['cc']
        );

        if ($data['sender_email']) {
            $this->getUserContact()->setSenderEmail($data['sender_email']);
        }

        $notification['attachments'] = $this->formAttachments($data);

        $deal_id = 0;
        $deal = $this->getDeal();
        if (!empty($deal)) {
            $deal_id = $deal['id'];
            $notification['deal_id'] = $deal['id'];

            if (!empty($deal['source_email'])) {
                $notification['reply_to'][$deal['source_email']] = $deal['source_email'];
            }
        }
        if (empty($deal) || empty($notification['reply_to'])) {
            $source_email = $this->emailSource();
            if ($source_email) {
                $notification['reply_to'][$source_email] = $source_email;
            } else {
                waLog::log('Source email not found on message send new controller');
            }
        }

        $notification['message_id'] = crmEmailSourceWorker::generateMessageId($deal_id);
        
        if (empty($deal_id)) {
            $contact_id = $this->getRecipientContact()->getId();
            $conversations = (array) $this->getConversationModel()->getByField('contact_id', $contact_id, true);
            if (count($conversations) === 1) {
                $conversation = reset($conversations);
                if ($conversation['type'] === crmConversationModel::TYPE_EMAIL) {
                    $notification['conversation_id'] = $conversation['id'];
                }
            }
        }

        return $notification;
    }

    protected function getDeal()
    {
        if ($this->deal !== null) {
            return $this->deal;
        }

        $id = $this->getParameter('deal_id');
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
            $file_id = $this->getFileModel()->add([
                'contact_id' => $contact_id,
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

    protected function getEmail()
    {
        if ($this->api) {
            return trim((string) $this->getParameter('email'));
        }

        return parent::getEmail();
    }

    public function getMessageId()
    {
        return ifset($this->response, 'id', 0);
    }

    public function getError()
    {
        return $this->errors;
    }
}
