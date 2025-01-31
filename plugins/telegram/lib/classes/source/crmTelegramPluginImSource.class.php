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

    public function findMessage($telegram_message_id)
    {
        return self::getMessageModel()->query("
            SELECT m.* FROM crm_message m 
            INNER JOIN crm_message_params p ON m.id = p.message_id AND p.name = 'telegram_message_id' AND p.value = :telegram_message_id
            WHERE m.source_id = i:source_id", 
            [
                'source_id' => $this->getId(),
                'telegram_message_id' => $telegram_message_id,
            ])->fetchAssoc();
    }
}
