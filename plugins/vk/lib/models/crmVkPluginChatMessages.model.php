<?php

class crmVkPluginChatMessagesModel extends crmVkPluginModel
{
    protected $table = 'crm_vk_plugin_chat_messages';

    public function deleteByChats($chat_ids)
    {
        $this->deleteByField('chat_id', $chat_ids);
    }

    public function deleteByMessages($message_ids)
    {
        $this->deleteByField('message_id', $message_ids);
    }
}
