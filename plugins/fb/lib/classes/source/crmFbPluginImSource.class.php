<?php

class crmFbPluginImSource extends crmImSource
{
    protected $provider = 'fb';

    public function getProviderName()
    {
        return 'Facebook';
    }

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/fb/img/', true) . 'fb.png';
    }

    public function getMarkerToken()
    {
        return $this->getParam('access_marker');
    }

    public function getFontAwesomeBrandIcon()
    {
        return [
            'icon_fab' => 'facebook-f',
            'icon_color' => '#4267B2',
        ];
    }
}
