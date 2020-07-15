<?php

class crmZadarmaPluginSettingsAction extends crmViewAction
{
    public function execute()
    {
        $this->view->assign(array(
            'key'          => wa()->getSetting('key', '', array('crm', 'zadarma')),
            'secret'       => wa()->getSetting('secret', '', array('crm', 'zadarma')),
            'callback_url' => $this->getCallbackUrl(),
        ));
    }

    protected function getCallbackUrl()
    {
        $routing = wa()->getRouting()->getByApp('crm');
        if (!$routing) {
            return false;
        }
        return rtrim(wa()->getRouteUrl('crm', array(
            'plugin' => 'zadarma',
            'module' => 'frontend',
            'action' => 'callback',
        ), true), '/');
    }
}