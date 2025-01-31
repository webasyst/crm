<?php

class crmVkPlugin extends crmSourcePlugin
{
    public function factorySource($id, $options = array())
    {
        return new crmVkPluginImSource($id, $options);
    }

    public function backendAssets()
    {
        $version = $this->info['version'];
        $static_url = wa()->getAppStaticUrl('crm', true) . 'plugins/vk/';
        wa()->getResponse()->addJs("{$static_url}js/conversation.sender.form.js?v{$version}");
        wa()->getResponse()->addJs("{$static_url}js/dialog.sender.js?v{$version}");
        return '<link rel="stylesheet" href="' . $static_url . 'css/vk.css?v'.$version.'">';
    }

    public function messageDelete($params)
    {
        $mm = new crmVkPluginChatMessagesModel();
        $mm->deleteByMessages((array)ifset($params['ids']));
    }

    public function mergeContacts($params)
    {
        $master_id = $params['id'];
        $merge_ids = (array)$params['contacts'];
        (new crmVkPluginChatParticipantModel)->exec("UPDATE crm_vk_plugin_chat_participant SET contact_id=:master WHERE contact_id IN(:ids)", [
            'master' => $master_id, 
            'ids' => $merge_ids
        ]);

    }
}
