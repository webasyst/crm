<?php

class crmContactAccessDialogAction extends waViewAction
{
    public function execute()
    {
        $contact_id = waRequest::request('contact_id', 0, 'int');
        if ($contact_id) {
            $contact = new waContact($contact_id);
        }
        if (empty($contact) || !$contact->exists()) {
            throw new waException(_w('Contact not found'), 404);
        }

        $rights = new crmRights();
        if (!$rights->classifyContactAccess($contact)) {
            throw new waRightsException();
        }

        $rights = new crmRights();
        if (!$rights->contact($contact)) {
            throw new waRightsException();
        }

        // List of available vaults
        $vault_model = new crmVaultModel();
        $vaults = $vault_model->getAvailable();

        // Owners currently assigned to this contact
        $owners = array();
        if ($contact['crm_vault_id'] < 0) {
            $adhoc_group_model = new crmAdhocGroupModel();
            $owner_ids = $adhoc_group_model->getByGroup(-$contact['crm_vault_id']);
            if ($owner_ids) {
                $c = new waContactsCollection('users');
                $c->addWhere("id IN ('".join("','", $vault_model->escape($owner_ids))."')");
                $owners = $c->getContacts('id,name,login,firstname,middlename,lastname,photo,photo_url_96,is_user');
                foreach ($owners as &$c) {
                    if (!empty($c['firstname']) || !empty($c['middlename']) || !empty($c['lastname'])) {
                        $c['name'] = waContactNameField::formatName($c);
                        $c['photo_url'] = $c['photo_url_96'];
                    }
                }
            } else {
                // Immidiately show self in list of owners after switching to limited mode
                $me = wa()->getUser();
                $owners = array(
                    $me->getId() => array(
                        'id'        => $me->getId(),
                        'name'      => $me['name'],
                        'photo_url' => $me->getPhoto('20'),
                    ),
                );
            }
        }

        // Company employees
        $employees_count = null;
        if ($contact['is_company'] > 0) {
            $collection = new crmContactsCollection('company/'.$contact['id'], array(
                'check_rights' => true,
            ));
            $employees_count = $collection->count();
        }

        $this->view->assign(array(
            'employees_count' => $employees_count,
            'contact' => $contact,
            'owners' => $owners,
            'vaults' => $vaults,
            'vaults_count' => count($vaults),
        ));
    }
}
