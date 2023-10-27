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

    public function getFontAwesomeBrandIcon()
    {
        return [
            'icon_fab' => 'telegram-plane',
            'icon_color' => '#229ED9',
        ];
    }
}
