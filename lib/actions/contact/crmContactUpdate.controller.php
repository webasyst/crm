<?php

/**
 */
class crmContactUpdateController extends waJsonController
{
    public function execute()
    {
        $contact = waRequest::post('contact', null, waRequest::TYPE_ARRAY_TRIM);
        $call = waRequest::post('call', null, waRequest::TYPE_ARRAY_TRIM);
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

        //$contact['phone'] = $call['plugin_client_number'];
        $contact->set('phone', $call['plugin_client_number'], true);
        $contact->save();

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
