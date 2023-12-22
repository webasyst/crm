<?php

class crmContactAccessUpdateMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $contact_id = $this->get('id', true);
        $_json = $this->readBodyAsJson();
        $owner_ids = ifempty($_json, 'owner_id', null);
        $vault_id  = ifset($_json, 'vault_id', null);

        if (!is_numeric($contact_id) || $contact_id < 1) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } elseif (isset($owner_ids, $vault_id)) {
            throw new waAPIException('invalid_data', sprintf_wp('One of the values is expected: %s.', sprintf_wp('“%s” or “%s”', 'owner_id', 'vault_id')), 400);
        } elseif (isset($vault_id) && (!is_numeric($vault_id) || $vault_id < 0)) {
            throw new waAPIException('not_found', _w('Vault not found.'), 404);
        }

        $contact = new crmContact($contact_id);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } elseif (!$this->getCrmRights()->classifyContactAccess($contact)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } elseif ($errors = $this->validate($owner_ids, $vault_id)) {
            throw new waAPIException('invalid_data', implode(', ', $errors), 400);
        }

        if ($owner_ids) {
            $this->saveOwners($contact, $owner_ids);
        } elseif (isset($vault_id)) {
            if (!$this->getUser()->getRights('crm', "vault.$vault_id")) {
                throw new waAPIException('forbidden', _w('Access denied'), 403);
            }
            $this->saveVaultId($contact, $vault_id);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }

    private function validate($owner_ids, $vault_id)
    {
        $errors = [];
        if (isset($owner_ids)) {
            foreach ((array) $owner_ids as $owner_id) {
                if (!is_numeric($owner_id) || $owner_id < 1) {
                    $errors[] = (empty($owner_id) ? sprintf_wp('Empty “%s” value.', 'owner_id') : sprintf_wp("Invalid “%s” value: %s.", 'owner_id', '$owner_id'));
                }
            }
            if (!$errors) {
                $adhoc_group_model = new crmAdhocGroupModel();
                $contact_collection = new waContactsCollection('users');
                $contact_collection->addWhere("id IN ('".join("','", $adhoc_group_model->escape($owner_ids))."')");
                $owners = array_keys($contact_collection->getContacts('id'));
                if ($id_diff = array_diff($owner_ids, $owners)) {
                    $errors[] = sprintf_wp('Nonexistent user’s IDs: %s.', implode(', ', $id_diff));
                }
                if (!$errors) {
                    $user_ids = [];
                    foreach ($owners as $_id) {
                        if ((new crmContact($_id))->getRights('crm', 'backend')) {
                            $user_ids[] = $_id;
                        }
                    }
                    if ($id_diff = array_diff($owners, $user_ids)) {
                        $errors[] = sprintf_wp('IDs of users without access rights: %s.', implode(', ', $id_diff));
                    }
                }
            }
        } elseif (isset($vault_id) && $vault_id != 0) {
            if (!$this->getVaultModel()->getById($vault_id)) {
                $errors[] = _w('Vault does not exist.');
            }
        }

        return $errors;
    }

    private function saveOwners($contact, $owner_ids)
    {
        $old_gid = ($contact['crm_vault_id'] < 0 ? -$contact['crm_vault_id'] : '');
        $contacts_by_vault = [
            $old_gid => [$contact['id']]
        ];
        $adhoc_group_model = new crmAdhocGroupModel();
        foreach($contacts_by_vault as $old_adhoc_group_id => $contacts) {
            $adhoc_group_model->setContactsOwners($contacts, ifempty($old_adhoc_group_id), $owner_ids);
        }

        /** if only one owner - we will appoint him responsible for the contact. That's that.. ¯\_(ツ)_/¯ */
        if (count($owner_ids) == 1 && $contact['crm_user_id'] != $owner_ids['0']) {
            $contact_model = new crmContactModel();
            $contact_model->updateResponsibleContact($contact['id'],$owner_ids['0']);
        }
    }

    private function saveVaultId($contact, $vault_id)
    {
        $old_gid = ($contact['crm_vault_id'] < 0 ? -$contact['crm_vault_id'] : '');
        $contacts_by_vault = [
            $old_gid => [$contact['id']]
        ];
        $adhoc_group_model = new crmAdhocGroupModel();
        foreach($contacts_by_vault as $old_adhoc_group_id => $contacts) {
            $adhoc_group_model->setContactsVault($contacts, ifempty($old_adhoc_group_id), $vault_id);
        }
    }
}
