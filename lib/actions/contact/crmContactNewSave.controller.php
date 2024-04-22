<?php

class crmContactNewSaveController extends crmContactSaveController
{
    public function execute()
    {
        parent::execute();
        if ($this->errors) {
            return;
        }
        $contact = parent::getParam('contact');
        $contact = new crmContact($contact['id']);

        $vault_id = waRequest::post('vault_id');
        $owner_ids = waRequest::post('owners');

        if ($vault_id) {
            $this->saveVaultId($contact, (int)$vault_id);
        } elseif ($owner_ids) {
            $this->saveOwners($contact, $owner_ids);
        }

        $segments = waRequest::post('segment');

        if ($segments) {
            $sm = new crmSegmentModel();
            foreach ($segments as $segment) {
                $sm->addTo($segment, $contact['id']);
            }
        }

        $post_deal = null;
        if (waRequest::post('deal')) {
            $controller = new crmDealSaveController();
            $post_deal = $this->getRequest()->post('deal');
            $controller->validate($post_deal);
            if ($controller->errors) {
                $this->errors = $controller->errors;
                return;
            }
            $post_deal['contact_id'] = $contact['id'];
            $post_deal['id'] = $controller->saveDeal($post_deal);
            $this->response['redirect_url'] = wa()->getAppUrl().'deal/'.$post_deal['id'];
        } else {
            $this->response['redirect_url'] = wa()->getAppUrl().'contact/'.$this->params['contact']['id'];
        }

        if (!empty($contact['id']) && ($call_id = waRequest::request('call', null, waRequest::TYPE_INT))) {
            $cm = new crmCallModel();
            if ($call = $cm->getById($call_id)) {
                $cm->updateById($call_id, array(
                    'client_contact_id' => $contact['id'],
                    'deal_id'           => ifset($post_deal['id']),
                ));
                $sql = "UPDATE {$cm->getTableName()} SET client_contact_id = ".(int)$contact['id']
                    ." WHERE plugin_id = '".$cm->escape($call['plugin_id'])
                    ."' AND plugin_client_number = '".$cm->escape($call['plugin_client_number'])
                    ."' AND client_contact_id IS NULL";
                $cm->exec($sql);

                $asm = new waAppSettingsModel();
                $asm->set('crm', 'call_ts', time());

                $contact_id = !empty($post_deal['id']) ? (-$post_deal['id']) : $contact['id'];
                $lm = new crmLogModel();
                $lm->log('call', $contact_id, $call_id, null, null, $contact_id);
            }
        }
    }

    protected function saveVaultId($contact, $vault_id)
    {
        if ($vault_id < 0) {
            throw new waException('Bad arguments');
        } else if ($vault_id > 0) {
            if (!wa()->getUser()->getRights('crm', 'vault.'.$vault_id)) {
                throw new waRightsException();
            }
        }

        $adhoc_group_model = new crmAdhocGroupModel();
        foreach($this->getByVaultWithEmployees($contact) as $old_adhoc_group_id => $contacts) {
            $adhoc_group_model->setContactsVault($contacts, ifempty($old_adhoc_group_id), $vault_id);
        }
    }

    protected function saveOwners($contact, $owner_ids)
    {
        // Validate
        $owner_ids = array_filter(array_map('intval', $owner_ids), wa_lambda('$id', 'return $id > 0;'));
        if (!$owner_ids) {
            throw new waException('Bad arguments');
        }
        $adhoc_group_model = new crmAdhocGroupModel();

        $c = new waContactsCollection('users');
        $c->addWhere("id IN ('".join("','", $adhoc_group_model->escape($owner_ids))."')");
        $owner_ids = array_keys($c->getContacts('id'));
        if (!$owner_ids) {
            throw new waException('Bad arguments');
        }

        foreach($this->getByVaultWithEmployees($contact) as $old_adhoc_group_id => $contacts) {
            $adhoc_group_model->setContactsOwners($contacts, ifempty($old_adhoc_group_id), $owner_ids);
        }

        // If only one owner - we will appoint him responsible for the contact. That's that.. ¯\_(ツ)_/¯
        if (count($owner_ids) == 1 && $contact['crm_user_id'] != $owner_ids['0']) {
            $contact_model = new crmContactModel();
            $contact_model->updateResponsibleContact($contact['id'],$owner_ids['0']); // Update here :x
        }
    }

    protected function getByVaultWithEmployees($contact)
    {
        $old_gid = $contact['crm_vault_id'] < 0 ? -$contact['crm_vault_id'] : '';
        $contacts_by_vault = array(
            $old_gid => array($contact['id']),
        );

        // Also apply to employees?
        if (waRequest::post('employees')) {
            $collection = new crmContactsCollection('company/'.$contact['id'], array(
                'check_rights' => true,
            ));
            foreach($collection->getContacts('id,crm_vault_id') as $c) {
                $old_gid = $c['crm_vault_id'] < 0 ? -$c['crm_vault_id'] : '';
                if (empty($contacts_by_vault[$old_gid])) {
                    $contacts_by_vault[$old_gid] = array();
                }
                $contacts_by_vault[$old_gid][] = $c['id'];
            }
        }
        return $contacts_by_vault;
    }
}
