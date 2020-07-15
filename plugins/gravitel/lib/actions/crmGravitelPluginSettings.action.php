<?php

class crmGravitelPluginSettingsAction extends crmViewAction
{
    public function execute()
    {
        $this->view->assign(array(
            'pbx_url'      => wa()->getSetting('pbx_url', '', array('crm', 'gravitel')),
            'pbx_key'      => wa()->getSetting('pbx_key', '', array('crm', 'gravitel')),
            'crm_key'      => wa()->getSetting('crm_key', '', array('crm', 'gravitel')),
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
            'plugin' => 'gravitel',
            'module' => 'frontend',
            'action' => 'callback',
        ), true), '/');
    }
}