<?php

class crmZebratelecomPlugin extends waPlugin
{
    public function backendAssetsHandler(&$params)
    {
        $version = $this->info['version'];
        if (waSystemConfig::isDebug()) {
            $version .= '.'.filemtime($this->path.'/js/zebratelecom.js');
        }
        return '<script type="text/javascript" src="'.$this->getPluginStaticUrl().'js/zebratelecom.js?v'.$version.'"></script>';
    }

    function backendAssets()
    {
        $settings = $this->getSettings();
        if (!empty($settings['login'])) {
            return $settings['login'];
        }
        if (!empty($settings['password'])) {
            return $settings['password'];
        }
        if (!empty($settings['sip_server'])) {
            return $settings['sip_server'];
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
            parent::saveSettings($settings);
            $api = new crmZebratelecomPluginApi();
            $api->getWebHooks();
        }
    }
}
