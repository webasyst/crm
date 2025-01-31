<?php

class crmContactIdAction extends crmBackendViewAction
{
    protected $owner_ids = null;

    /**
     * @throws waRightsException
     */
    public function execute($contact_id_ext = null)
    {
        $contact_id = ($contact_id_ext ?: waRequest::param('id', null, waRequest::TYPE_INT));
        if (!$contact_id) {
            $this->redirect(wa()->getUrl());
        }

        $rights = new crmRights();
        $contact = new waContact($contact_id);

        if ($contact_id_ext && !$contact->exists()) {
            return;
        }
        if (!$rights->contactVaultId($contact['crm_vault_id'])) {
            $this->redirect(wa()->getUrl());
        }

        // Count company employees
        $contact['employees_count'] = 0;
        if ($contact['is_company']) {
            $wcm = new waContactModel();
            $contact['employees_count'] = $wcm->select('COUNT(*) cnt')->where('company_contact_id='.(int)$contact['id'])->fetchField('cnt');
        }

        // Update list of recently viewed contacts
        $rm = new crmRecentModel();
        $rm->update($contact_id);

        $vault = null;
        if ($contact['crm_vault_id'] > 0) {
            $vault_model = new crmVaultModel();
            $vault = $vault_model->getById($contact['crm_vault_id']);
        }

        // Responsible currently assigned to this contact
        $responsible_data = array();
        if ($contact['crm_user_id'] != 0) {
            try {
                $responsible = new waContact($contact['crm_user_id']);
                $responsible_data = array(
                    'id' => $responsible['id'],
                    'name' => $responsible['name'],
                    'photo_url' => $responsible->getPhoto(20)
                );
            } catch (waException $e) {
                $responsible_data = null;
            }
        }

        $default_email = $contact->getFirst('email');
        $default_phone = $contact->getFirst('phone');

        $duplicate_counters = array(
            'email' => array(
                'value' => '',
                'count' => 0,
            ),
            'phone' => array(
                'value' => '',
                'count' => 0
            )
        );
        if ($default_email) {
            $duplicate_counters['email']['value'] = $default_email['value'];
            $duplicate_counters['email']['count'] = $this->getEmailDuplicatesCounters($contact['id'], $default_email['value']);
        }
        if ($default_phone) {
            $duplicate_counters['phone']['value'] = $default_phone['value'];
            $duplicate_counters['phone']['count'] = $this->getPhoneDuplicatesCounters($contact['id'], $default_phone['value']);
        }

        $this->view->assign(array(
            'contact'            => $contact,
            'tags'               => $this->getTags($contact),
            'tabs'               => $this->getContactTabs($contact_id),
            'tab'                => waRequest::param('tab', null, waRequest::TYPE_STRING_TRIM),
            'access_dialog_name' => $this->getAccessDialogName($contact, $rights, $vault),
            'one_owner_user'     => 1 == $this->countOwners($contact),
            'owner_id'           => $this->getSingleOwnerId($contact),
            'editable'           => $rights->contactEditable($contact),
            'top_info_fields'    => $this->getTopInfoFields($contact),
            'contact_segments'   => $this->getContactSegments($contact_id),
            'search_segments'    => $this->getSearchSegments(),
            'files'              => $this->getContactFiles($contact_id),
            'vault'              => $vault,
            'responsible'        => $responsible_data,
            'is_init_call'       => $rights->isInitCall(),
            'is_sms_configured'  => $this->isSMSConfigured(),
            'duplicate_counters' => $duplicate_counters
        ));
    }

    protected function getContactTabs($contact_id)
    {
        // Same as {$wa->getContactTabs()} in template
        $tabs = $this->view->getHelper()->getContactTabs($contact_id);

        // Show history tab first
        if (!empty($tabs['history'])) {
            $tabs = array('history' => $tabs['history']) + $tabs;
        }

        // CRM info tab replaces one from Team app
        $tabs['info'] = array(
            'id'    => 'info',
            'count' => '',
            'title' => _w('Info'),
            'url'   => '?module=contact&action=profileInfo&id='.$contact_id,
            'html'  => '',
        );

        // Note that some tabs are hidden with CSS,
        // but are still accessible via a direct link.

        return $tabs;
    }

    protected function getTopInfoFields($contact)
    {
        $fields = waContactFields::getInfo($contact['is_company'] ? 'company' : 'person', true);
        unset(
            $fields['name'],
            $fields['title'],
            $fields['firstname'],
            $fields['middlename'],
            $fields['lastname'],

            $fields['company_contact_id'],
            $fields['jobtitle'],
            $fields['company'],

            $fields['email'],
            $fields['phone']
        );

        $info = array();
        foreach ($fields as $field) {
            $field = array(
                    'values' => array(),
                    'data'   => array(),
                ) + $field;

            $data = $contact->get($field['id'], 'js,list');
            if (empty($field['multi'])) {
                $data = array($data);
            }
            foreach ($data as $row) {
                if (!$row) {
                    continue;
                }

                if (!is_array($row)) {
                    $value = (string)$row;
                } else {
                    if (isset($row['value']) && !is_array($row['value'])) {
                        $value = $row['value'];
                    } else {
                        // Don't know how to show unexpected data format :(
                        if (SystemConfig::isDebug()) {
                            $value = json_encode($row);
                        } else {
                            continue;
                        }
                    }
                }

                // Do not show fields with no value set
                if ($value === '') {
                    continue;
                }

                // Show option label for select-based fields
                if (!empty($field['options'][$value])) {
                    $field['values'][] = $field['options'][$value];
                } else {
                    $field['values'][] = $value;
                }
                $field['data'][] = $row;
            }

            if ($field['values']) {
                $info[$field['id']] = $field;
            }
        }

        return $info;
    }

    protected function getTags($contact)
    {
        $tm = new crmTagModel();
        return $tm->getByContact($contact['id']);
    }

    protected function getAccessDialogName($contact, $rights, $vault)
    {
        if (!$rights->classifyContactAccess($contact)) {
            return null;
        }

        if ($contact['crm_vault_id'] == 0) {
            return _w('Manage access');
        }

        if ($contact['crm_vault_id'] > 0) {
            if ($vault) {
                return $vault['name'];
            } else {
                return 'deleted vault_id='.$contact['crm_vault_id'];
            }
        }

        $owner_ids = $this->getOwnerIds($contact);
        if (count($owner_ids) > 1) {
            return _w('%d owner', '%d owners', count($owner_ids));
        }

        $owner = new waContact(reset($owner_ids));
        if (!$owner->exists()) {
            return 'deleted owner contact_id='.((int)reset($owner_ids));
        }

        return $owner['name'];
    }

    /**
     * This method is required to display the responsible user if it is different from the single contact owner.
     * If 0 is returned, then the contact does not have a single owner.
     * Else, the owner's id is returned.
     */
    protected function getSingleOwnerId($contact)
    {
        if ($contact['crm_vault_id'] < 0) {
            $owner_ids = $this->getOwnerIds($contact);
            if (count($owner_ids) == 1) {
                return reset($owner_ids);
            }
        }
        return null;
    }

    protected function countOwners($contact)
    {
        return count($this->getOwnerIds($contact));
    }

    protected function getOwnerIds($contact) {
        if ($contact['crm_vault_id'] >= 0) {
            return array();
        }
        if ($this->owner_ids === null) {
            $adhoc_group_model = new crmAdhocGroupModel();
            $this->owner_ids = $adhoc_group_model->getByGroup(-$contact['crm_vault_id']);
        }
        return $this->owner_ids;
    }

    protected function getContactSegments($contact_id)
    {
        return $this->getSegmentModel()->getByContact($contact_id);
    }

    protected function getSearchSegments()
    {
        return $this->getSegmentModel()->getByField('type', 'search', true);
    }

    protected function getContactFiles($contact_id)
    {
        $fm = new crmFileModel();
        return $fm->select('*')->where("contact_id = ".(int)$contact_id)->order('name')->fetchAll('id');
    }

    protected function getEmailDuplicatesCounters($contact_id, $email)
    {
        if (!wa_is_int($contact_id) || $contact_id < 0) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM `wa_contact_emails`
                    WHERE email = :email AND contact_id != :id AND sort = 0";
        $cem = new waContactEmailsModel();
        return $cem->query($sql, array(
            'id' => $contact_id,
            'email' => $email
        ))->fetchField();
    }

    protected function getPhoneDuplicatesCounters($contact_id, $phone) {
        if (!wa_is_int($contact_id) || $contact_id < 0) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM `wa_contact_data`
                    WHERE `value` = :phone AND contact_id != :id AND field = 'phone' AND sort = 0";
        $cdm = new waContactDataModel();
        return $cdm->query($sql, array(
            'id' => $contact_id,
            'phone' => $phone
        ))->fetchField();
    }
}
