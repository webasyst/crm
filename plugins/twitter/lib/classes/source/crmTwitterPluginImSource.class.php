<?php

class crmTwitterPluginImSource extends crmImSource
{
    protected $provider = 'twitter';

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/twitter/img', true) . 'twitter.png';
    }
}
