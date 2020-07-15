<?php

class crmVkPluginChatParticipantModel extends crmVkPluginModel
{
    protected $table = 'crm_vk_plugin_chat_participant';

    public function add($data)
    {
        return $this->insert($data);
    }

    public function delete($id)
    {
        $ids = crmHelper::toIntArray($id);
        if (!$ids) {
            return;
        }
        $this->getParamsModel()->delete($ids);
        $this->deleteById($ids);
    }

    public function deleteByChats($chat_ids)
    {
        $chat_ids = crmHelper::toIntArray($chat_ids);
        if (!$chat_ids) {
            return;
        }
        $ids = $this->select('id')->where('chat_id IN(:chat_ids)', array(
            'chat_ids' => $chat_ids
        ))->fetchAll(null, true);
        if (!$ids) {
            return;
        }
        $this->delete($ids);
    }

    protected function getParamsModel()
    {
        return new crmVkPluginChatParticipantParamsModel();
    }
}
