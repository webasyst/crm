<?php

class crmWhatsappPlugin extends crmSourcePlugin
{
    public function factorySource($id, $options = [])
    {
        return new crmWhatsappPluginImSource($id, $options);
    }

    public function backendAssets()
    {
        $version = $this->info['version'];
        $sources = array(
            '<link rel="stylesheet" href="'.wa()->getAppStaticUrl('crm', true).'plugins/whatsapp/css/whatsapp.css?v'.$version.'">',
            '<script src="'.wa()->getAppStaticUrl('crm', true).'plugins/whatsapp/js/whatsapp.js?v'.$version.'"></script>',
        );

        return join("", $sources);
    }

    public function contactUiActions($params)
    {
        $sources = (new crmSourceModel)->getByField([
            'disabled' => 0,
            'provider' => 'whatsapp',
        ], true);
        $items = array_map(function($source) use ($params) {
            $source = crmSource::factory($source);
            return $source->renderInitConversationLink($params['contact_id']);
        }, $sources);
        return [
            'plus_dropdown' => $items,
        ];
    }
}