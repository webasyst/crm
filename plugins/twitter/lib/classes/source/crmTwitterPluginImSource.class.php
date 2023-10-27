<?php

class crmTwitterPluginImSource extends crmImSource
{
    protected $provider = 'twitter';

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/twitter/img', true) . 'twitter.png';
    }

    public function getFontAwesomeBrandIcon()
    {
        return [
            'icon_fab' => 'twitter',
            'icon_color' => '#1DA1F2',
        ];
    }
}
