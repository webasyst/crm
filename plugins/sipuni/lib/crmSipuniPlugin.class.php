<?php

class crmSipuniPlugin extends waPlugin
{
    public function backendAssetsHandler(&$params)
    {
        $sources = array();
        $sources[] = '<link rel="stylesheet" href="'.wa()->getAppStaticUrl('crm', true).'plugins/sipuni/css/sipuni.css">';
        $sources[] = '<script src="'.wa()->getAppStaticUrl('crm', true).'plugins/sipuni/js/sipuni.js"></script>';

        return join("", $sources);
    }

    function backendAssets()
    {
        $settings = $this->getSettings();
        if (!empty($settings['api_key'])) {
            return $settings['api_key'];
        }
        if (!empty($settings['sign_key'])) {
            return $settings['sign_key'];
        }
    }

    protected function getSettingsConfig()
    {
        if (wa()->getUser()->isAdmin()) {
            return parent::getSettingsConfig();
        } else {
            return array();
        }
    }

    public function saveSettings($settings = array())
    {
        if (wa()->getUser()->isAdmin()) {
            return parent::saveSettings($settings);
        }
    }
}
