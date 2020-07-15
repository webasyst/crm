<?php

class crmVkPluginChatModel extends crmVkPluginModel
{
    protected $table = 'crm_vk_plugin_chat';

    public function add($data)
    {
        $max_id = 0;
        $auto_name = false;
        if (!isset($data['name'])) {
            $auto_name = true;
            $max_id = $this->select('MAX(id)')->fetchField() + 1;
            $data['name'] = sprintf(_wp('Chat #%s'), $max_id);
        }

        $id = $this->insert($data);
        if ($auto_name && $max_id != $id) {
            $this->updateById($id, array(
                'name' => sprintf(_wp('Chat #%s'), $id)
            ));
        }
        return $id;
    }

    public function delete($id)
    {
        $ids = crmHelper::toIntArray($id);
        if (!$ids) {
            return;
        }
        $this->getMessagesModel()->deleteByChats('chat_id', $ids);
        $this->getParamsModel()->delete($ids);
        $this->deleteById($ids);
    }

    protected function getMessagesModel()
    {
        return new crmVkPluginChatMessagesModel();
    }

    protected function getParamsModel()
    {
        return new crmVkPluginChatParamsModel();
    }

    protected function getParticipantsModel()
    {
        return new crmVkPluginChatParticipantModel();
    }
}
