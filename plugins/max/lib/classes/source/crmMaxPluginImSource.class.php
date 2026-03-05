<?php

/**
 * MAX messenger IM source for CRM.
 */
class crmMaxPluginImSource extends crmImSource
{
    protected $provider = 'max';

    public function getProviderName()
    {
        return 'MAX';
    }

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/max/img', true) . 'max.png';
    }

}
