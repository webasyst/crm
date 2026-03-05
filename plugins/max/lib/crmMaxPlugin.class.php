<?php

class crmMaxPlugin extends crmSourcePlugin
{
    public function factorySource($id, $options = array())
    {
        return new crmMaxPluginImSource($id, $options);
    }

    public function backendAssets()
    {
        $version = $this->info['version'];
        $sources = [];
        $sources[] = '<script src="'.wa()->getAppStaticUrl('crm', true).'plugins/max/js/settings.js?v'.$version.'"></script>';

        return join("", $sources);
    }
}
