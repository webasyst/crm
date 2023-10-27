<?php

class crmContactMergeAction extends crmContactsAction
{
    public function preExecute() {
        if (wa()->whichUI() === '1.3') {
            parent::preExecute();
        }
    }

    public function execute()
    {
        parent::execute();

        $iframe = waRequest::get('iframe', 0, waRequest::TYPE_INT);
        if (!empty($iframe)) {
            $this->setLayout();
        }
        $this->view->assign([
            'iframe' => $iframe
        ]);
    }

    public function afterExecute()
    {
        $this->accessDeniedForNotEditRights();

        $ids = $this->getIds();
        if (!$ids) {
            $iframe = waRequest::get('iframe', 0, waRequest::TYPE_INT);
            $this->redirect(wa()->getAppUrl('crm').'?module=contactMergeDuplicates'.(empty($iframe) ? '' : '&iframe=1'));
            return;
        }

        $collection = new crmContactsCollection('id/'.implode(',', $ids));
        $collection->orderBy('~data', 'DESC');
        $contacts = $collection->getContacts('*,photo_url_96', 0, 500);
        foreach ($contacts as &$c) {
            $c['name'] = waContactNameField::formatName($c);
        }
        unset($c);

        // Field names
        $fields = array(); // field id => field name
        foreach (waContactFields::getAll('enabled') as $field_id => $field) {
            $fields[$field_id] = $field->getName();

            // Format data for template if needed
            foreach($contacts as &$c) {
                if (empty($c[$field_id])) {
                    continue;
                }

                if (!is_array($c[$field_id]) || $this->is_assoc($c[$field_id])) {
                    $c[$field_id] = $field->format($c[$field_id], 'html');
                } else {
                    foreach($c[$field_id] as &$v) {
                        $v = $field->format($v, 'html');
                    }
                    unset($v);
                    $c[$field_id] = implode(', ', $c[$field_id]);
                }
            }
            unset($c);
        }

        // skip some fields in the list
        $fields = array_diff_key($fields, array(
            'title' => true,
            'name' => true,
            'photo' => true,
            'firstname' => true,
            'middlename' => true,
            'lastname' => true,
            'locale' => true,
            'timezone' => true,
        ));

        // Initialize 'master_only' key
        foreach($contacts as &$c) {
            $c['master_only'] = '';
        }
        unset($c);

        // Event to allow other applications to add their data if needed
        $params = array_keys($contacts);
        $links = wa()->event(array('contacts', 'links'), $params);
        $apps = wa()->getApps();
        foreach($links as $app_id => $app_links) {
            foreach($app_links as $contact_id => $contact_links) {
                foreach($contact_links as $l) {
                    // Show information about links
                    $field_name = $apps[$app_id]['name'].'/'.$l['role'];
                    $fields[$field_name] = $field_name;
                    $contacts[$contact_id][$field_name] = _w("%d link", "%d links", $l['links_number']);

                    // Show warning if this contact cannot be merged into other contacts.
                    if (!empty($l['forbid_merge_reason'])) {
                        if (!empty($contacts[$contact_id]['master_only'])) {
                            $contacts[$contact_id]['master_only'] .= '<br>';
                        } else {
                            $contacts[$contact_id]['master_only'] = '';
                        }
                        $contacts[$contact_id]['master_only'] .= $l['forbid_merge_reason'];
                    }
                }
            }
        }

        // List of contacts that can be safely merged into other contacts
        $slave_ids = array();
        foreach($contacts as &$c) {
            if ($c['is_user'] > 0) {
                $c['master_only'] = ($c['master_only'] ? $c['master_only'].'<br>' : '')._w('Users can not be merged into other contacts.');
            } else if (empty($c['master_only'])) {
                $slave_ids[] = $c['id'];
            }

            $author = array(
                'name' => ''
            );
            if ($c['create_contact_id']) {
                $author_contact = new waContact($c['create_contact_id']);
                if ($author_contact->exists()) {
                    $author = $author_contact;
                }
            }
            $c['author'] = $author;

        }
        unset($c);

        $this->view->assign(array(
            'slave_ids' => $slave_ids,
            'contacts' => $contacts,
            'fields' => $fields
        ));
    }

    protected function getIds()
    {
        $ids = $this->getRequest()->request('ids', '', waRequest::TYPE_STRING_TRIM);
        $ids = array_filter(array_map('trim', explode(',', $ids)));
        return crmHelper::dropNotPositive($ids);
    }

    protected function is_assoc($array)
    {
        return array_values($array) !== $array;
    }
}
