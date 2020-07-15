<?php

class crmSipuniPluginSettingsSaveController extends crmJsonController
{
    public $plugin_id = "sipuni";

    public function execute()
    {
        $user = waRequest::post('user', "", waRequest::TYPE_STRING);
        $key = waRequest::post('integration_key', "", waRequest::TYPE_STRING);
        $nums = waRequest::post('employees_num', array(), waRequest::TYPE_ARRAY);

        $this->getPlugin()->saveSettings(array('integration_key' => $key, 'user' => $user));

        foreach ($nums as $index => $value) {
            $value = preg_replace("/[^0-9]/", '', $value);
            $nums[$index] = $value;
            if (mb_strlen($value) !== 3) {
                unset($nums[$index]);
            }
        }

        // Delete all records in crm_pbx
        $this->getPbxModel()->deleteByField(
            array(
                'plugin_id' => $this->plugin_id,
            )
        );
        // And add new nums in crm_pbx
        $this->getPbxModel()->multipleInsert(
            array(
                'plugin_id'          => $this->plugin_id,
                'plugin_user_number' => $nums,
            )
        );

        $this->response = array('message' => _w('Saved'));
    }

    protected function getPlugin()
    {
        return waSystem::getInstance()->getPlugin($this->plugin_id);
    }
}