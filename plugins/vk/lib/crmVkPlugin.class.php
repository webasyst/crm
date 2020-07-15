<?php

class crmVkPlugin extends crmSourcePlugin
{
    public function factorySource($id, $options = array())
    {
        return new crmVkPluginImSource($id, $options);
    }

    public function backendAssets()
    {
        $static_url = wa()->getAppStaticUrl('crm', true) . 'plugins/vk/';
        wa()->getResponse()->addJs("{$static_url}js/conversation.sender.form.js");
        wa()->getResponse()->addJs("{$static_url}js/dialog.sender.js");
        return '<link rel="stylesheet" href="' . $static_url . 'css/vk.css">';
    }

    public function messageDelete($params)
    {
        $mm = new crmVkPluginChatMessagesModel();
        $mm->deleteByMessages((array)ifset($params['ids']));
    }
}
