<?php

class crmTelegramPluginImSource extends crmImSource
{
    protected $provider = 'telegram';

    public function getProviderName()
    {
        return 'Telegram';
    }

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/telegram/img', true) . 'telegram.png';
    }
}
