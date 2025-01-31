<?php

class crmWhatsappPluginImSource extends crmImSource
{
    protected $provider = 'whatsapp';

    public function getProviderName()
    {
        return 'WhatsApp';
    }

    public function getFontAwesomeBrandIcon()
    {
        return [
            'icon_fab' => 'whatsapp',
            'icon_color' => '#25D366',
        ];
    }

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/whatsapp/img/', true) . 'whatsapp.png';
    }

    public function canInitConversation()
    {
        return true;
    }

    public function renderInitConversationLink($contact)
    {
        if (!($contact instanceof waContact)) {
            $contact = new waContact($contact);
        }
        $to = $contact->get('im.whatsapp', 'default') ?: $contact->get('phone', 'default');
        if (empty($to)) {
            return null;
        }
        $helper = new crmWhatsappPluginImSourceHelper($this);
        return $helper->templatesDropdownItem(0, $contact->getId());
    }
}