<?php

class crmSendEmailDialogAction extends crmBackendViewAction
{
    /**
     * @var crmContact
     */
    protected $contact;

    /**
     * @var array
     */
    protected $message;

    const ACT_NEW_MESSAGE = 'new';
    const ACT_REPLY_MESSAGE = 'reply';
    const ACT_FORWARD_MESSAGE = 'forward';
    const ACT_DEAL_MESSAGE = 'deal'; // action when calling dialog when clicking on the contact's email

    public function __construct($params = null)
    {
        parent::__construct($params);
        $actions_path = wa('crm')->whichUI('crm') === '1.3' ? 'actions-legacy' : 'actions';
        $this->setTemplate('templates/' . $actions_path . '/message/MessageSendEmailDialog.html');

        $user_contact = $this->getUserContact();

        // crutch for the redactor.js
        $replacement = array(
            '<div><br></div>' => '',
            '<div><div>'      => '',
            '<div>'           => '<br>',
            '<br><br>'        => '<br>',
            '</div>'          => '',
        );
        $body = str_replace(array_keys($replacement),array_values($replacement),$this->getBody());

        $this->view->assign(array(
            'hash'                 => md5(time() . $user_contact->getId()),
            'email'                => $this->getEmail(),
            'contact'              => $this->getRecipientContact(),
            'body'                 => $body,
            'email_signature'      => $user_contact->getEmailSignature(),
            'sender_photo_url'     => $user_contact->getPhoto('20'),
            'sender_name'          => $user_contact->getSenderName(),
            'sender_emails'        => $user_contact->get('email', 'value'),
            'sender_default_email' => $user_contact->getSenderEmail(),
        ));
    }

    /**
     * @throws crmAccessDeniedException
     * @throws crmNotFoundException
     * @return array|null
     */
    protected function getMessage()
    {
        if ($this->message !== null) {
            return $this->message;
        }

        $id = (int)$this->getParameter('id');
        if ($id <= 0) {
            $this->notFound(_w('Message not found'));
        }
        $message = $this->getMessageModel()->getMessage($id);
        if (!$message) {
            $this->notFound(_w('Message not found'));
        }

        $message['deal'] = null;
        if ($message['deal_id'] > 0) {
            $message['deal'] = $this->obtainDeal($message['deal_id']);
        }

        $message['contact'] = $this->obtainContact($message['contact_id']);

        $reply_supported = $message['source_id'] > 0 ||
            $message['transport'] == crmMessageModel::TRANSPORT_EMAIL ||
            $message['transport'] == crmMessageModel::TRANSPORT_SMS;


        if ($reply_supported) {
            $has_access = $this->getCrmRights()->canViewMessage($message);
            $message['reply_allowed'] = $has_access;
        } else {
            $message['reply_allowed'] = $reply_supported;
        }

        if (!$message['reply_allowed']) {
            $this->accessDenied();
        }

        return $this->message = $message;
    }

    protected function getFiles()
    {
        $message = $this->getMessage();
        $deal_files = [];
        if (!empty($message['deal']['files'])) {
            $deal_files = $message['deal']['files'];
        }
        return $message['attachments'] + $deal_files;
    }

    /**
     * @param int $id
     * @throws crmAccessDeniedException
     * @throws crmNotFoundException
     * @return crmContact
     */
    protected function obtainContact($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            $this->notFound();
        }
        if (!$this->getCrmRights()->contact($id)) {
            $this->accessDenied();
        }
        return new crmContact($id);
    }

    /**
     * @param $id
     * @throws crmAccessDeniedException
     * @throws crmNotFoundException
     * @return array|null
     */
    protected function obtainDeal($id)
    {
        $id = (int)$id;
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
        return $deal;
    }

    protected function getDeal()
    {
        $message = $this->getMessage();
        return $message['deal'];
    }

    protected function getBody()
    {
        return '<p><br></p><br><section data-role="c-email-signature">' . $this->getUserContact()->getEmailSignature().'</section>';
    }

    /**
     * @return mixed|string
     */
    protected function getEmail()
    {
        if ($this->getRecipientEmail()) {
            return $this->getRecipientEmail();
        }
        $contact = $this->getRecipientContact();
        $email = $contact->getDefaultEmailValue();
        return $email;
    }

    /**
     * @return string
     */
    protected function getRecipientEmail()
    {
        $email = trim((string)$this->getParameter('email'));
        return $email;
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
        return $this->contact = $message['contact'];
    }

    /**
     * Return participants that have an email
     * @param array $participants_ids
     * @return array|crmContactsCollection
     */
    protected function getParticipantsData($participants_ids)
    {
        if (!$participants_ids) {
            return array();
        }
        $collection = new crmContactsCollection('id/' . implode(',', $participants_ids));
        $collection = $collection->getContacts('name,email,photo_url_16');
        foreach ($collection as $contact => $field) {
            if (!is_array($field['email']) || empty($field['email'])) {
                unset($collection[$contact]);
            }
        }
        return $collection;
    }

    protected function getParticipantsIds($deal_participants)
    {
        if (!$deal_participants) {
            return array();
        }

        $ids = array();
        foreach ($deal_participants as $participant) {
            $ids[] = $participant['contact_id'];
        }
        // Delete the main contact of the deal..
        $recipient_contact_id = $this->getRecipientContact()->getId();
        if ($recipient_contact_id && in_array($recipient_contact_id, $ids)) {
            unset($ids[array_search($recipient_contact_id, $ids)]);
        }

        // .. and the current user from the list
        if (in_array(wa()->getUser()->getId(), $ids)) {
            unset($ids[array_search(wa()->getUser()->getId(), $ids)]);
        }
        return $ids;
    }
}
