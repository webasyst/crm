<?php

class crmSettingsPBXAddController extends waJsonController
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

        $pm = new crmPbxModel();
        $pbx = $pm->getByField(array('plugin_id' => $data['plugin_id'], 'plugin_user_number' => $data['plugin_user_number']));
        if (!$pbx) {
            $this->errors[] = _w('PBX number not found');
            return;
        }

        $pum = new crmPbxUsersModel();
        $pbx_user = $pum->getByField($data);
        if ($pbx_user) {
            $this->errors[] = _w('User already added to PBX number');
            return;
        }

        $contact = new waContact($data['contact_id']);
        if (!$contact->exists()) {
            $this->errors[] = _w('User not found.');
            return;
        }

        $pum->insert($data);

        $right = $contact->getRights('crm', 'calls');
        if ($right < crmRightConfig::RIGHT_CALL_OWN) {
            $contact->setRight('crm', 'calls', crmRightConfig::RIGHT_CALL_OWN);
        }

        $params = array($data);
        wa('crm')->event('pbx_numbers_added', $params);
    }
}
