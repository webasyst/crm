<?php

/**
 * Personal settings of sender save
 */
class crmEmailPersonalSettingsSaveController extends crmJsonController
{
    public function execute()
    {
        $this->getUserContact()->setEmailSignature($this->getRequest()->post('email_signature'));
        $this->getUserContact()->setSenderName($this->getRequest()->post('sender_name'));
        $this->response = array(
            'email_signature' => $this->getUserContact()->getEmailSignature(),
            'sender_name' => $this->getUserContact()->getSenderName()
        );
    }
}
