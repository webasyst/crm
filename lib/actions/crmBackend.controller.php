<?php

class crmBackendController extends waViewController
{
    public function execute()
    {
        $last_url = wa()->getUser()->getSettings('crm', 'last_url') ?: 'contact/';
        $this->redirect($last_url);
    }
}
