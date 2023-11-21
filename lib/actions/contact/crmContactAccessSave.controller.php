<?php
class crmContactAccessSaveController extends waJsonController
{
    public function execute()
    {
        $contact_id = waRequest::request('contact_id', null, 'int');
        if ($contact_id) {
            $contact = new crmContact($contact_id);
        }
        if (empty($contact) || !$contact->exists()) {
            throw new waException(_w('Contact not found'), 404);
        }

        $rights = new crmRights();
        if (!$rights->classifyContactAccess($contact)) {
            throw new waRightsException();
        }

        $contact_model = new crmContactModel();

        // Free contact or belongs to a vault
        $vault_id = waRequest::post('vault_id', '', 'string');
        if ($vault_id !== '') {
            $this->saveVaultId($contact, (int)$vault_id);
            $this->response = 'ok';

            $contact->removeCache();
            $contact = new crmContact($contact['id']);
            if ($contact->isResponsibleUserIncceptable($contact['crm_user_id'])) {
                $contact_model->updateResponsibleContact($contact['id']); // Remove responsible user
            }
            return;
        }

        // Limit to a list of owner users
        $owner_ids = waRequest::post('owners', null, 'array_int');
        if ($owner_ids !== null) {
            $this->saveOwners($contact, $owner_ids);
            if (wa()->getUser()->isAdmin('crm') || in_array(wa()->getUser()->getId(), $owner_ids)) {
                $this->response = 'ok';
            } else {
                $this->response = 'revoked';
            }

            $contact->removeCache();
            $contact = new crmContact($contact['id']);
            if ($contact->isResponsibleUserIncceptable($contact['crm_user_id'])) {
                $contact_model->updateResponsibleContact($contact['id']); // Remove responsible user
            }
            return;
        } else {
            throw new waException('Bad arguments');
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
