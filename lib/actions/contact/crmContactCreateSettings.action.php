<?php

class crmContactCreateSettingsAction extends waViewAction
{
    public function execute()
    {
       $options = array(
           'type' => wa()->getUser()->getSettings('crm', 'contact_create_type', 'dialog'),
           'not_responsible' => wa()->getUser()->getSettings('crm', 'contact_create_not_responsible'),
        );
        $this->view->assign($options);
    }
}
