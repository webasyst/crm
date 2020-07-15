<?php

class crmSettingsPBXDeleteNumberController extends waJsonController
{
    public function execute()
    {
        $data = array(
            'plugin_id'          => waRequest::post('p', null, waRequest::TYPE_STRING_TRIM),
            'plugin_user_number' => waRequest::post('n', null, waRequest::TYPE_STRING_TRIM),
        );

        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $pm = new crmPbxModel();
        $pum = new crmPbxUsersModel();
        $ppm = new crmPbxParamsModel();

        $pm->deleteByField($data);
        $pum->deleteByField($data);
        $ppm->deleteByField($data);
    }
}
