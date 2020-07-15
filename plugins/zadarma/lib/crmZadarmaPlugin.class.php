<?php

class crmZadarmaPlugin extends waPlugin
{
    public function backendAssetsHandler(&$params)
    {
        $version = $this->info['version'];
        if (waSystemConfig::isDebug()) {
            $version .= '.'.filemtime($this->path.'/js/zadarma.js');
        }
        return '<script type="text/javascript" src="'.$this->getPluginStaticUrl().'js/zadarma.js?v'.$version.'"></script>';
    }

    function backendAssets()
    {
        $settings = $this->getSettings();
        if (!empty($settings['key'])) {
            return $settings['key'];
        }
        if (!empty($settings['secret'])) {
            return $settings['secret'];
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
