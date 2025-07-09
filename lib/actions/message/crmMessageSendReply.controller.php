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

    public function execute($message = [])
    {
        if (empty($message)) {
            $this->getMessage();
        } else {
            $this->message = $message;
        }
        if ($this->message['source_id'] > 0 && $this->message['transport'] != crmMessageModel::TRANSPORT_EMAIL) {
            $source = crmSource::factory($this->message['source_id']);
            $data = $this->prepareData();
            $result = crmSourceMessageSender::replyToMessage($source, $this->message, $data);
            $id = ifset($result, 'response', 'message_id', 0);
            if ($result['status'] == 'ok') {
                $this->response = (array) ifset($result['response']);
            } else {
                $this->errors = (array) ifset($result['errors']);
                return;
            }
        } else {
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
            $this->response = [
                'id' => $id,
                'message_id' => $email_message['message_id'],
                'attachment_count' => isset($email_message['attachments']) ? count($email_message['attachments']) : 0
            ];
        }

        if (wa()->whichUI('crm') !== '1.3') {
            $sent_message = $this->getMessageModel()->getMessage($id);
            $sent_message = $this->workupMessage($sent_message);
            $view = wa()->getView();
            $view->assign([
                'message' => $sent_message,
                'contact'  => new crmContact((int)$sent_message['creator_contact_id']),
            ]);
            $template = wa()->getAppPath('templates/actions/message/MessageConversationId.singleMessage.inc.html', 'crm');
            $message_html = $view->fetch($template);
            $this->response['html'] = waUtils::jsonEncode($message_html);
        }

        if ($this->message['transport'] != crmMessageModel::TRANSPORT_EMAIL) {
            $this->display();
            exit;
        }
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
            $message = $this->getMessage();
            $source = empty($message['source_id']) ? null : crmEmailSource::factory($message['source_id']);
            $source_email = (empty($source) || $source->isDisabled()) ? $this->emailSource() : $source->getEmail();

            if (!empty($source_email)) {
                $notification['reply_to'][$source_email] = $source_email;
            } else {
                waLog::log('Source email not found on message send reply controller');
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

    protected function workupMessage($message)
    {
        if (empty($message) || !ifset($message, 'source_id', false) || wa()->whichUI() === '1.3') {
            return $message;
        }
        $source_helper = crmSourceHelper::factory(crmSource::factory($message['source_id']));
        $res = $source_helper->normalazeMessagesExtras([$message]);
        return $res ? reset($res) : $message;
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
