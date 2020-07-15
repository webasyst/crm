<?php

class crmSettingsPBXDeleteController extends waJsonController
{
    public function execute()
    {
        $data = array(
            'plugin_id'          => waRequest::post('p', null, waRequest::TYPE_STRING_TRIM),
            'plugin_user_number' => waRequest::post('n', null, waRequest::TYPE_STRING_TRIM),
            'contact_id'         => waRequest::post('u', null, waRequest::TYPE_INT),
        );

        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $pum = new crmPbxUsersModel();
        $pbx_user = $pum->getByField($data);
        if (!$pbx_user) {
            throw new waException('PBX user not found');
        }

        $pum->deleteByField($data);

        $params = array($data);
        wa('crm')->event('pbx_numbers_deleted', $params);
    }
}
