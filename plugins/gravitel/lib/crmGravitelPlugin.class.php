<?php

class crmGravitelPlugin extends waPlugin
{
    public function backendAssetsHandler(&$params)
    {
        $version = $this->info['version'];
        if (waSystemConfig::isDebug()) {
            $version .= '.'.filemtime($this->path.'/js/gravitel.js');
        }
        return '<script type="text/javascript" src="'.$this->getPluginStaticUrl().'js/gravitel.js?v'.$version.'"></script>';
    }

    function backendAssets()
    {
        $settings = $this->getSettings();
        if (!empty($settings['pbx_url'])) {
            return $settings['pbx_url'];
        }
        if (!empty($settings['pbx_key'])) {
            return $settings['pbx_key'];
        }
        if (!empty($settings['crm_key'])) {
            return $settings['crm_key'];
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
