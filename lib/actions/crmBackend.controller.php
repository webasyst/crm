<?php

class crmBackendController extends waViewController
{
    public function execute()
    {
        $last_url = wa()->getUser()->getSettings('crm', 'last_url');
        if (!$last_url) {
            $this->executeAction(new crmContactAction());
        } else {
            $this->redirect($last_url);
        }
    }
}
