<?php

class crmContactUpdateController extends waJsonController
{
    public function execute()
    {
        $contact = waRequest::post('contact', null, waRequest::TYPE_ARRAY_TRIM);
        $call = waRequest::post('call', null, waRequest::TYPE_ARRAY_TRIM);
        $client_delete_phone = !!waRequest::post('client_delete_phone', '', waRequest::TYPE_STRING_TRIM);

        if (!wa()->getUser()->getRights('crm', 'edit')) {
            throw new waRightsException();
        }
        if (empty($contact['id']) || empty($call['id'])) {
            throw new waException('Invalid data');
        }

        $contact = new waContact($contact['id']);
        $cm = new crmCallModel();
        $call = $cm->getById($call['id']);

        if (!$contact->getName() || !$call || !$call['plugin_client_number']) {
            throw new waException('Invalid data');
        }

        $phone_exist = false;
        $contact_phones = (array) $contact->get('phone');
        foreach ($contact_phones as $_phone) {
            if (waContactPhoneField::isPhoneEquals(ifset($_phone, 'value', ''), $call['plugin_client_number'])) {
                $phone_exist = true;
                break;
            }
        }
        if (!$phone_exist) {
            $contact->add('phone', [
                'value' => waContactPhoneField::cleanPhoneNumber($call['plugin_client_number']),
                'ext' => '',
                'status' => 'confirmed',
            ]);
        }
        $contact->save();

        if ($client_delete_phone && !empty($call['client_contact_id'])) {
            $client = new waContact($call['client_contact_id']);
            if ($client->exists()) {
                $phones = (array) $client->get('phone');
                foreach ($phones as $_key => $_phone) {
                    if (waContactPhoneField::isPhoneEquals(ifset($_phone, 'value', ''), $call['plugin_client_number'])) {
                        unset($phones[$_key]);
                    }
                }
                $client->set('phone', array_values($phones));
                $client->save();
            }
        }

        $cm->updateById($call['id'], array(
            'client_contact_id' => $contact['id'],
        ));
        $sql = "UPDATE {$cm->getTableName()} SET client_contact_id = ".(int)$contact['id']
            ." WHERE plugin_id = '".$cm->escape($call['plugin_id'])
            ."' AND plugin_client_number = '".$cm->escape($call['plugin_client_number'])
            ."' AND client_contact_id IS NULL";
        $cm->exec($sql);

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'call_ts', time());

        $lm = new crmLogModel();
        $lm->log('call', $contact['id'], $call['id'], null, null, $contact['id']);

        $this->response = array('id' => $contact['id']);
    }
}
