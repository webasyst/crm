<?php

class crmLogAction extends crmBackendViewAction
{
    public function execute()
    {
        $contact_id = $this->getContactId();
        $deal_id = $this->getDealId();
        $max_id = $this->getMaxId();

        $id = $deal_id ? $deal_id * -1 : $contact_id;

        // THIS ARRAY USED BY LogTimeline Tooo!!!!
        $filter_actions = self::_getFilterActions();
        $filters = self::_getFilters($filter_actions);

        $lm = new crmLogModel();
        $rm = new crmReminderModel();
        $im = new crmInvoiceModel();
        list($log, $min_id, $count) = $lm->getLog($id, $filters, 0, $max_id);

        $actors = $actor_ids = $invoice_ids = array();

        foreach ($log as &$l) {
            $actor_ids[$l['actor_contact_id']] = 1;
            if (stripos($l['action'], 'invoice_') === 0) {
                $invoice_ids[] = $l['object_id'];
            }
        }
        unset($l);

        if ($actor_ids) {
            $collection = new waContactsCollection('/id/'.join(',', array_keys($actor_ids)));
            $contacts = $collection->getContacts(wa('crm')->getConfig()->getContactFields());
            foreach ($contacts as $c) {
                $contact = $this->newContact($c);
                if ($contact->exists()) {
                    $actors[$c['id']] = $contact;
                } else {
                    $actors[$c['id']] = $contact;
                }
            }
        }
        $reminders = $users = $user_ids = $invoices = $deals = array();
        if ($filters['reminders']['is_active']) {
            if ($lm->deals) {
                $condition = "contact_id IN ($id,-".join(",-", array_keys($lm->deals)).")";
            } else {
                $condition = "contact_id = ".(int)$id;
            }
            // $condition .= " AND complete_datetime IS NULL";

            $reminders = $rm->select('*')->where("$condition")
                            ->order('due_date, ISNULL(due_datetime), due_datetime')->fetchAll('id');
        }
        foreach ($reminders as &$r) {
            $r['state'] = crmHelper::getReminderState($r);
            $r['rights'] = $this->getCrmRights()->reminderEditable($r);
            $user_ids[$r['user_contact_id']] = 1;
        }
        unset($r);

        if ($user_ids) {
            $collection = new waContactsCollection('/id/'.join(',', array_keys($user_ids)));
            $contacts = $collection->getContacts(wa('crm')->getConfig()->getContactFields());
            foreach ($contacts as $c) {
                $users[$c['id']] = $this->newContact($c);
            }
        }
        if ($invoice_ids && ($filters['invoices']['is_active'] || $filters['all']['is_active'])) {
            $invoices = $im->select('*')->where(
                "id IN('".join("','", $im->escape($invoice_ids))."')"
            )->fetchAll('id');
        }
        $dm = new crmDealModel();
        $deal = $deal_id ? $dm->getById($deal_id) : null;
        $contact = $contact_id ? ($this->newContact($id)) : null;

        $ids = array_keys($log);

        if (array_pop($ids) == $min_id) { // last block (first record is reached)
            if ($contact) {
                if ($contact['create_datetime']) {
                    $apps = wa()->getApps();
                    $inline_html = array();
                    if (!empty($apps[$contact['create_app_id']])) {
                        $inline_html[] = _w('app').': '.$apps[$contact['create_app_id']]['name'];
                    }
                    if ($contact['create_method']) {
                        $inline_html[] = _w('method').': '.$contact['create_method'];
                    }
                    $log[] = array(
                        'id' => 0,
                        'create_datetime' => $contact['create_datetime'],
                        'actor_contact_id' => $contact['create_contact_id'],
                        'action' => null,
                        'action_name' => _w('added contact'),
                        'inline_html' => join(', ', $inline_html),
                    );
                    $creator = new waContact($contact['create_contact_id']);
                    $actors[$creator->getId()] = $this->newContact($contact['create_contact_id']);
                }
            } elseif ($deal) {
                $log[] = array(
                    'id' => 0,
                    'create_datetime' => $deal['create_datetime'],
                    'actor_contact_id' => $deal['creator_contact_id'],
                    'action' => null,
                    'action_name' => _w('added deal'),
                );
                $actors[$deal['creator_contact_id']] = $this->newContact($deal['creator_contact_id']);
            }
        }

        $creator_contact = null;
        if ($contact) {
            $creator_contact = $this->newContact($contact->get('create_contact_id'));
        } elseif ($deal) {
            $creator_contact = $this->newContact($deal['creator_contact_id']);
        }

        $this->view->assign(array(
            'contact'             => $contact,
            'deal'                => $deal,
            'log'                 => $log,
            'count'               => $count,
            'actors'              => $actors,
            'users'               => $users,
            'reminders'           => $reminders,
            'invoices'            => $invoices,
            'filters'             => $filters,
            'deal_id'             => $deal_id,
            'deals'               => $lm->deals,
            'creator_contact'     => $creator_contact,
            'filter_actions'      => $filter_actions,
            'can_manage_invoices' => wa()->getUser()->getRights('crm', 'manage_invoices'),
            'crm_app_url'         => wa()->getAppUrl('crm')
        ));

        $actions_path = wa('crm')->whichUI('crm') === '1.3' ? 'actions-legacy' : 'actions';
        if (!$max_id) {
            $this->setTemplate('templates/' . $actions_path . '/log/Log.html');
        } else {
            $this->setTemplate('templates/' . $actions_path . '/log/LogTimeline.html');
        }

        /**
         * @event backend_profile_log
         * @return array[string]string $return[%plugin_id%]
         */
        $this->view->assign('backend_profile_log', wa('crm')->event('backend_profile_log'));
    }

    private function _getFilterActions() {
        $filter_actions = array(
            'all' => array(
                'id'   => 'all',
                'name' => _w('All types'),
            ),
        );
        foreach (wa('crm')->getConfig()->getLogType() as $action => $data) {
            $filter_actions[$action] = array('id' => $action) + $data;
        }

        return $filter_actions;
    }

    private function _getFilters($filter_actions) {
        $_filters = waRequest::post('filters', null, waRequest::TYPE_ARRAY);

        $filters = array(
            'reminders' => array(
                'id' => 'reminders',
                'name' => _w('Reminders'),
                'color' => !empty($filter_actions["reminder"]["color"]) ? $filter_actions["reminder"]["color"] : false,
                'is_active' => !$_filters || isset($_filters['reminders'])
            ),
            'notes' => array(
                'id' => 'notes',
                'name'      => _w('Notes' ),
                'color' => !empty($filter_actions["note"]["color"]) ? $filter_actions["note"]["color"] : false,
                'is_active' => !$_filters || isset($_filters['notes'])
            ),
            'files' => array(
                'id' => 'files',
                'name'      => _w('Files' ),
                'color' => !empty($filter_actions["file"]["color"]) ? $filter_actions["file"]["color"] : false,
                'is_active' => !$_filters || isset($_filters['files'])
            ),
            'invoices' => array(
                'id' => 'invoices',
                'name'      => _w('Invoices' ),
                'color' => !empty($filter_actions["invoice"]["color"]) ? $filter_actions["invoice"]["color"] : false,
                'is_active' => !$_filters || isset($_filters['invoices'])
            ),
            'deals' => array(
                'id' => 'deals',
                'name'      => _w('Deals' ),
                'color' => !empty($filter_actions["deal"]["color"]) ? $filter_actions["deal"]["color"] : false,
                'is_active' => !$_filters || isset($_filters['deals'])
            ),
            'contacts' => array(
                'id' => 'contacts',
                'name'      => _w('Contacts' ),
                'color' => !empty($filter_actions["contact"]["color"]) ? $filter_actions["contact"]["color"] : false,
                'is_active' => !$_filters || isset($_filters['contacts'])
            ),
            'messages' => array(
                'id' => 'messages',
                'name' => _w('Messages'),
                'color' => !empty($filter_actions["message"]["color"]) ? $filter_actions["message"]["color"] : false,
                'is_active' => !$_filters || isset($_filters['messages'])
            ),
            'calls' => array(
                'id' => 'calls',
                'name' => _w('Calls'),
                'color' => !empty($filter_actions["call"]["color"]) ? $filter_actions["call"]["color"] : false,
                'is_active' => !$_filters || isset($_filters['calls'])
            ),
            'order_log' => array(
                'id' => 'order_log',
                'name' => _w('Orders'),
                'color' => !empty($filter_actions["order_log"]["color"]) ? $filter_actions["order_log"]["color"] : false,
                'is_active' => !$_filters || isset($_filters['order_log'])
            )
        );

        return $filters;
    }

    protected function getContactId()
    {
        return (int)$this->getParameter('contact_id');
    }

    protected function getDealId()
    {
        $deal_id = (int)$this->getParameter('id');
        if ($deal_id <= 0) {
            $deal_id = (int)$this->getParameter('deal_id');
        }
        return $deal_id;
    }

    protected function getMaxId()
    {
        $max_id = (int)$this->getParameter('max_id');
        return $max_id;
    }

    /**
     * Get contact object (even if contact not exists)
     * BUT please don't save it
     *
     * @param int|array $contact ID or data
     * @return waContact
     * @throws waException
     */
    protected function newContact($contact)
    {
        if ($contact instanceof waContact) {
            return $contact;
        }

        $contact_id = 0;
        if (wa_is_int($contact) && $contact > 0) {
            $contact_id = $contact;
        } elseif (isset($contact['id']) && wa_is_int($contact['id']) && $contact['id'] > 0) {
            $contact_id = $contact['id'];
        }

        $wa_contact = new waContact($contact);
        if (!$wa_contact->exists()) {
            $wa_contact = new waContact();
            $wa_contact['id'] = $contact_id;
            $wa_contact['name'] = sprintf_wp("Contact with ID %s doesn't exist", $contact_id);
        }
        return $wa_contact;
    }

}
