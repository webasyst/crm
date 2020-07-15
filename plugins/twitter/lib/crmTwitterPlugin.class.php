<?php

class crmTwitterPlugin extends crmSourcePlugin
{
    public function factorySource($id, $options = array())
    {
        return new crmTwitterPluginImSource($id, $options);
    }

    public function backendAssets()
    {
        $v = $this->info['version'];

        $sources = array();
        $sources[] = '<link rel="stylesheet" href="'.wa()->getAppStaticUrl('crm', true).'plugins/twitter/css/twitter.css?v=' . $v . '">';
        $sources[] = '<script src="'.wa()->getAppStaticUrl('crm', true).'plugins/twitter/js/twitter.js?v' . $v . '"></script>';

        return join("", $sources);
    }
}
