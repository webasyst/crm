<?php

class crmContactOperationDelete
{
    /**
     * @var array
     */
    protected $contacts;

    /**
     * @var array
     */
    protected $linked_contacts;

    /**
     * @var array
     */
    protected $free_contacts;

    /**
     * @var array
     */
    protected $links;

    /**
     * @var array
     */
    protected $crm_links;

    /**
     * @var waContact
     */
    protected $contact;

    /**
     * @var int
     */
    protected $contact_id;

    /**
     * @var bool
     */
    protected $is_super_admin;

    /**
     * @var crmRights
     */
    protected $crm_rights;

    public function __construct($options = array())
    {
        $type = 'item';
        $contacts = (array)ifset($options['contacts']);
        if (!empty($contacts)) {
            $contact = reset($contacts);
            if (wa_is_int($contact)) {
                $type = 'int';
            }
        }
        $this->contact = wa()->getUser();
        $this->contact_id = $this->contact['id'];
        $this->crm_rights = new crmRights(array('contact' => $this->contact));
        $contacts = $this->crm_rights->dropUnallowedContacts($contacts, 'edit');
        if ($type === 'int') {
            $contact_ids = $contacts;
            $contacts = array();
            foreach ($contact_ids as $contact_id) {
                $contacts[$contact_id] = array('id' => $contact_id);
            }
        }
        if (isset($contacts[$this->contact_id])) {
            unset($contacts[$this->contact_id]);
        }

        // format names
        foreach ($contacts as $contact_id => &$contact) {
            $contact['name'] = waContactNameField::formatName($contact);
        }
        unset($contact);

        $this->contacts = $contacts;
        $this->is_super_admin = (bool) wa()->getUser()->getRights('webasyst', 'backend');
    }

    /**
     * @return array|null
     */
    public function execute()
    {
        $contacts = $this->getContacts();
        if (!$contacts) {
            return null;
        }

        // Revoke user access before deletion
        $users = $this->getUsers($contacts);
        foreach($users as $user_id => $user) {
            waUser::revokeUser($user_id);
        }

        // prepare log-params
        $count = count($contacts);
        if ($count > 30) {
            $log_params = $count;
        } else {
            $log_params = $this->getNames($contacts);
        }

        $contact_ids = array_keys($contacts);

        $this->deleteContactsAppHistory($contact_ids);

        $contact_model = new waContactModel();
        // When delete contacts also throws a contacts.delete event
        $contact_model->delete($contact_ids);

        return array(
            'count' => $count,
            'log_params' => $log_params
        );
    }

    public function isSuperAdmin()
    {
        return $this->is_super_admin;
    }

    public function getLinkedContacts()
    {
        if ($this->linked_contacts !== null) {
            return $this->linked_contacts;
        }
        $this->splitContactsByLinks();
        return $this->linked_contacts;
    }

    public function getFreeContacts()
    {
        if ($this->free_contacts !== null) {
            return $this->free_contacts;
        }
        $this->splitContactsByLinks();
        return $this->free_contacts;
    }

    public function getContacts()
    {
        return $this->is_super_admin ? $this->contacts : $this->getFreeContacts();
    }

    public function getLinks()
    {
        if ($this->links !== null) {
            return $this->links;
        }

        if (!$this->contacts) {
            return $this->links = array();
        }

        /**
         * Check contacts link in other apps
         * @event links
         * @param int[] $contact_ids
         * @return array $links
         *    Format of returned array
         *    array(
         *        <app_id> => array(
         *            <contact_id> => array(
         *                array(
         *                    'role' => string - Some string named meaning of current links
         *                    'links_number' => int - Number of links
         *                )
         *                ...
         *            )
         *            ...
         *        )
         *        ...
         *    )
         */
        $contact_ids = array_keys($this->contacts);
        $result = wa()->event(array('contacts', 'links'), $contact_ids);

        // Only super admin can delete contacts with links
        // So form links map contact_id => app_id => link-items
        $links = array();
        foreach ($result as $app_id => $app_links) {
            foreach ($app_links as $contact_id => $contact_links) {
                if ($contact_links) {
                    $links[$contact_id][$app_id] = $contact_links;
                }
            }
        }

        // Do not allow non-superadmin to remove users
        if (!$this->is_super_admin) {
            $users = $this->getUsers($this->contacts);
            foreach($users as $user_id) {
                $links[$user_id]['contacts'] = (array) ifset($links[$user_id]['contacts']);
                // User isn't removable by non-superadmin, so mark as there is link for this contact
                $links[$user_id]['contacts'][] = array('user', 1);
            }
        }

        return $this->links = $links;
    }

    /**
     * Calculate links array for CRM-app
     * @return array
     * @throws waException
     * @see getLinks for array format
     */
    public function getCrmLinks()
    {
        if ($this->crm_links !== null) {
            return $this->crm_links;
        }

        waLocale::loadByDomain('crm');

        $links = array();

        /**
         * Calculate for each model links count
         * Each model must be implement method getContactLinksCount
         *
         * @var array $role_models
         */
        $role_models = $this->getRoleModels(true);

        foreach ($this->contacts as $contact_id => $contact) {

            $links[$contact_id] = array();

            foreach ($role_models as $role_model) {
                /**
                 * @var crmModel $model
                 */
                $model = $role_model['model'];
                $count = $model->getContactLinksCount($contact_id);
                if ($count > 0) {
                    $links[$contact_id][] = array(
                        'role' => $role_model['role'],
                        'links_number' => $count,
                    );
                }
            }

        }

        return $this->crm_links = $links;
    }

    public function deleteCrmLinks()
    {
        /**
         * Calculate for each model links count
         * Each model must be implement method getContactLinksCount
         *
         * @var array $role_models
         */
        $role_models = $this->getRoleModels();

        foreach ($role_models as $role_model) {
            /**
             * @var crmModel $model
             */
            $model = $role_model['model'];
            $model->unsetContactLinks(array_keys($this->contacts));
        }
    }

    protected function getRoleModels($only_for_show = false)
    {
        $models = array(
            'deals' =>
                array(
                    'role' => _wd('crm', 'Deals'),
                    'model' => new crmDealModel()
                ),
            'segments' =>
                array(
                    'role' => _wd('crm', 'Segments'),
                    'model' => new crmSegmentModel()
                ),
            'notes' =>
                array(
                    'role' => _wd('crm', 'Notes'),
                    'model' => new crmNoteModel()
                ),
            'reminders' =>
                array(
                    'role' => _wd('crm', 'Reminders'),
                    'model' => new crmReminderModel()
                ),
            'files' =>
                array(
                    'role' => _wd('crm', 'Files'),
                    'model' => new crmFileModel()
                ),
            'recent' =>
                array(
                    'role' => _wd('crm', 'Recent views'),
                    'model' => new crmRecentModel()
                ),
            'invoices' =>
                array(
                    'role' => _wd('crm', 'Invoices'),
                    'model' => new crmInvoiceModel()
                ),
            'tags' =>
                array(
                    'role' => _wd('crm', 'Tags'),
                    'model' => new crmContactTagsModel()
                ),
            'messages' =>
                array(
                    'role' => _wd('crm', 'Messages'),
                    'model' => new crmMessageModel()
                ),
            'conversation' =>
                array(
                    'role' => 'Conversations',
                    'model' => new crmConversationModel()
                ),
            'source' =>
                array(
                    'role' => 'Sources',
                    'model' => new crmSourceModel(),
                ),
        );

        if ($only_for_show) {
            unset($models['conversation'], $models['source']);
        }

        return array_values($models);
    }

    /**
     * Split contacts into 2 arrays, first contacts with links, second - without
     */
    protected function splitContactsByLinks()
    {
        if ($this->linked_contacts !== null) {
            return;
        }
        $links = $this->getLinks();
        $contacts = $this->contacts;
        $linked_contacts = array();
        $free_contacts = array();
        foreach ($contacts as $contact_id => $contact) {
            if (!empty($links[$contact_id])) {
                $contact['links'] = $links[$contact_id];
                $linked_contacts[$contact_id] = $contact;
            } else {
                $free_contacts[$contact_id] = $contact;
            }
        }
        $this->linked_contacts = $linked_contacts;
        $this->free_contacts = $free_contacts;
    }

    protected function getUsers($contacts)
    {
        $users = array();
        foreach ($contacts as $contact_id => $contact) {
            if ($contact['is_user'] > 0) {
                $users[$contact_id] = $contact;
            }
        }
        return $users;
    }

    protected function deleteContactsAppHistory($contact_ids)
    {
        if (!wa()->appExists('contacts')) {
            return;
        }
        wa('contacts');
        $hashes = array_map(wa_lambda('$contact_id', 'return "/contact/" . $contact_id;'), $contact_ids);
        $history_model = new contactsHistoryModel();
        $history_model->deleteByField(array(
            'type' => 'add',
            'hash' => $hashes
        ));
    }

    protected function getNames($contacts)
    {
        return waUtils::getFieldValues($contacts, 'name');
    }
}
