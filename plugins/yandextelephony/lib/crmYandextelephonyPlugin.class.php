<?php

class crmYandextelephonyPlugin extends waPlugin
{
    public function backendAssetsHandler(&$params)
    {
        $version = $this->info['version'];
        if (waSystemConfig::isDebug()) {
            $version .= '.'.filemtime($this->path.'/js/yandextelephony.js');
        }
        return '<script type="text/javascript" src="'.$this->getPluginStaticUrl().'js/yandextelephony.js?v'.$version.'"></script>';
    }

    function backendAssets()
    {
        $settings = $this->getSettings();
        if (!empty($settings['api_key'])) {
            return $settings['api_key'];
        }
        if (!empty($settings['user_key'])) {
            return $settings['user_key'];
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
