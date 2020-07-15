<?php

class crmYandextelephonyPluginSettingsAction extends crmViewAction
{
    public function execute()
    {
        $this->view->assign(array(
            'api_key'      => wa()->getSetting('api_key', '', array('crm', 'yandextelephony')),
            'user_key'     => wa()->getSetting('user_key', '', array('crm', 'yandextelephony')),
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
            'plugin' => 'yandextelephony',
            'module' => 'frontend',
            'action' => 'callback',
        ), true), '/');
    }
}