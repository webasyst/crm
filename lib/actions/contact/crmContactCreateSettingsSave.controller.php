<?php

class crmContactCreateSettingsSaveController extends crmJsonController
{
    public function execute()
    {
        $data = waRequest::post('setting', array(), waRequest::TYPE_ARRAY_TRIM);
        $white_list = array('type'=>'','not_responsible'=>'');

        $data = array_intersect_key($data, $white_list);

        foreach ($data as $key => $value) {
            wa()->getUser()->setSettings('crm', "contact_create_$key", $data[$key]);
        }
    }
}
