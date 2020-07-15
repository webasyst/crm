<?php

class crmLogLoadMessageAction extends crmBackendViewAction
{
    public function execute()
    {
        $this->view->assign(array(
            'message' => $this->getMessage(),
            'recipients' => $this->getRecipients()
        ));
    }

    /**
     * @throws crmAccessDeniedException
     * @throws crmNotFoundException
     * @return array|null
     */
    protected function getMessage()
    {
        $id = (int)$this->getParameter('id');
        if ($id <= 0) {
            $this->notFound();
        }
        $message = $this->getMessageModel()->getMessage($id);
        if (!$message) {
            $this->notFound();
        }

        $has_access = $this->getCrmRights()->canViewMessage($message);
        if (!$has_access) {
            $this->accessDenied(_w('You have not enough rights to view message.'));
        }

        if ($message['source_id']) {
            $source_helper = crmSourceHelper::factory(crmSource::factory($message['source_id']));
            $res = $source_helper->workupMessageLogItemBody($message);
            $message = $res ? $res : $message;
        }

        $message['reply_allowed'] = $message['transport'] == crmMessageModel::TRANSPORT_EMAIL;

        $this->getMessageReadModel()->setRead($message['id'], $this->getUserId());

        return $message;
    }

    protected function getRecipients()
    {
        $recipients = $this->getRecipientsByMessage();
        $ids = array();
        foreach ($recipients as &$recipient)
        {
            if (wa_is_int($recipient['contact_id'])) {
                $ids[] = $recipient['contact_id'];
            }
        }
        unset($recipient);

        $collection = array();
        if (ifset($ids)) {
            $collection = new crmContactsCollection('id/' . implode(',', $ids));
            $collection = $collection->getContacts('name,email,photo_url_16');
            foreach ($collection as $contact => $field) {
                if (!is_array($field['email'])) {
                    unset($collection[$contact]);
                }
            }
        }

        // Add userpic for recipients
        $recipients_with_photos = array();
        foreach ($recipients as $recipient) {
            if (isset($collection[$recipient['contact_id']])) {
                $recipient['photo'] = $collection[$recipient['contact_id']]['photo_url_16'];
            } else {
                $recipient['photo'] = null;
            }
            $recipients_with_photos[] = $recipient;
            unset($recipient);
        }
        unset($recipients);

        return $recipients_with_photos;
    }

    protected function getRecipientsByMessage()
    {
        $message_id = (int)$this->getParameter('id');
        $mrm = new crmMessageRecipientsModel();
        $recipients = $mrm->getRecipients($message_id);

        foreach ($recipients as &$recipient) {
            /**
             * TODO: Refactor it
             * @deprecated 'email' field
             * Not deprecated until version 1.2
             */
            $recipient['email'] = $recipient['destination'];

            if ($recipient['destination'] == $recipient['contact_id']) {
                unset($recipients[$recipient['destination']]);
                continue;
            }

            // Remove the recipient and the sender from the copy
            if ($recipient['type'] == "TO" || $recipient['type'] == "FROM") {
                unset($recipients[$recipient['destination']]);
                continue;
            }
        }
        unset($recipient);

        return $recipients;
    }
}
