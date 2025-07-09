<?php

class crmMessageSendForwardController extends crmSendEmailController
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

        $message['reply_allowed'] = $message['transport'] == crmMessageModel::TRANSPORT_EMAIL;

        if (!$message['reply_allowed']) {
            $this->accessDenied();
        }

        if ($message['original']) {
            $path = $this->getMessageModel()->getEmailSourceFilePath($message['id']);
            $message['original_path'] = $path;
        }

        return $this->message = $message;
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
            if (!empty($source_email)) {
                $notification['reply_to'][$source_email] = $source_email;
            } else {
                waLog::log('Source email not found on message send forward controller');
            }
        }

        $original_message = $this->getOriginalMessage();
        if ($original_message) {
            $notification['in_reply_to'] = $original_message->getMessageId();
            $notification['in_reply_to'] = trim(trim(trim($notification['in_reply_to']), '><'));
            $notification['references'] = $original_message->getReferences();
            $notification['references'][] = $notification['in_reply_to'];
            $notification['references'] = array_unique($notification['references']);
        }

        $notification['message_id'] = crmEmailSourceWorker::generateMessageId($deal_id);

        return $notification;
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
            'name'        => trim($this->getParameter('name')),
            'email'       => trim($this->getParameter('email')),
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

    protected function getDeal()
    {
        $message = $this->getMessage();
        if (!isset($message['deal'])) {
            return null;
        }
        if ($this->deal !== null) {
            return $this->deal;
        }

        $deal = $message['deal'];

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
        // attach checked existed files and new uploaded files
        $file_ids = array_merge(crmHelper::toIntArray(ifset($data['file_id'])), $this->addNewUploadedFiles());
        $attached_files = $this->getFileModel()->getFiles($file_ids);

        return $attached_files;
    }
}
