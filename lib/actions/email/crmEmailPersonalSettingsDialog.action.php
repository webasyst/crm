<?php

/**
 * Dialog for personal settings of current user (sender)
 */
class crmEmailPersonalSettingsDialogAction extends crmBackendViewAction
{
    public function execute()
    {
        $this->view->assign(array(
            'email_signature' => $this->getUserContact()->getEmailSignature(),
            'sender_name' => $this->getUserContact()->getSenderName(),
            'user_name' => $this->getUserContact()->getName()
        ));
    }
}
