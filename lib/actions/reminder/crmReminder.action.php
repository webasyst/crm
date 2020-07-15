<?php

/**
 * List of reminders by user.
 */

class crmReminderAction extends crmBackendViewAction
{
    protected $reminder_id;

    public function execute()
    {
        $rm = new crmReminderModel();

        if ($this->reminder_id) {
            $reminder = $rm->getById($this->reminder_id);
            if (!$reminder) {
                throw new waException('Reminder not found', 404);
            }
            $user_id = $reminder['user_contact_id'];
        } else {
            $user_id = waRequest::request('user_id', waRequest::param('user_id', null, waRequest::TYPE_INT), waRequest::TYPE_INT);
        }
        if (!$user_id) {
            $user_id = wa()->getUser()->getId();
        }
        $type = waRequest::get('type', null, waRequest::TYPE_STRING_TRIM);


        /* Sidebar data */
        $reminder_setting = wa()->getUser()->getSettings('crm', 'reminder_setting', 'all');
        if ($reminder_setting != 'all' && $reminder_setting != 'my') {
            $group_ids = preg_split('~\s*,\s*~', $reminder_setting);
        }

        $users = array();

        $reminder_users = array();
        if ($reminder_setting == 'all') {
            $crm = new waContactRightsModel();
            $reminder_users = array(wa()->getUser()->getId() => array()) + array_flip($crm->getUsers('crm'));
        } elseif ($reminder_setting != 'my') {
            $group_ids = preg_split('~\s*,\s*~', $reminder_setting);
            $ugm = new waUserGroupsModel();
            foreach ($ugm->getByField('group_id', $group_ids, true) as $g) {
                $reminder_users[$g['contact_id']] = 1;
            }
        }
        if ($reminder_users) {
            $collection = new waContactsCollection('/id/'.join(',', array_keys($reminder_users)));
            $collection->orderBy('last_datetime', 'desc');
            $contacts = $collection->getContacts(wa('crm')->getConfig()->getContactFields(), 0, count($reminder_users));
            foreach ($contacts as $id => $c) {
                $users[$id] = new waContact($c);
            }
        }
        if ($user_id && $user_id != wa()->getUser()->getId()) {
            $assign_to_user = new waContact($user_id);
        } else {
            $assign_to_user = wa()->getUser();
        }

        unset($users[wa()->getUser()->getId()]);
        $users = array(wa()->getUser()->getId() => wa()->getUser()) + $users;

        $user_counts = $rm->getUsersCounts();
        foreach ($users as $id => &$user) {
            $counts = ifset($user_counts[$id], array()) + array(
                    'count'        => 0,
                    'due_count'    => 0,
                    'burn_count'   => 0,
                    'actual_count' => 0
                );
            foreach ($counts as $k => $v) {
                $user[$k] = $v;
            }
        }
        unset($user);

        $condition = ($type && $type != 'all') ? " AND type='".$rm->escape($type)."'" : '';

        // Number of completed reminders
        $completed_reminders_count = $rm->select('COUNT(*) cnt')->where('user_contact_id = '.$user_id
            .' AND complete_datetime IS NOT NULL')->fetchField('cnt');

        $limit = crmConfig::ROWS_PER_PAGE;
        $offset = max(0, waRequest::request('page', 1, waRequest::TYPE_INT) - 1) * $limit;

        // List of uncompleted reminders
        $reminders = $rm->select('*')->where(
                'user_contact_id = '.$user_id.' AND complete_datetime IS NULL'.$condition
            )->order('due_date, ISNULL(due_datetime), due_datetime')->limit("$offset, $limit")->fetchAll('id');

        $reminders_count = $rm->select('COUNT(*) cnt')->where(
                'user_contact_id = '.$user_id.' AND complete_datetime IS NULL'.$condition
            )->fetchField('cnt');

        $dm = new crmDealModel();
        $deal_ids = $person_ids = array();
        foreach ($reminders as &$r) {
            if ($r['contact_id'] < 0) {
                $deal_ids[abs((int)$r['contact_id'])] = 1;
            } else {
                if ($r['contact_id'] > 0) {
                    $person_ids[$r['contact_id']] = 1;
                }
            }
            $r['state'] = crmHelper::getReminderState($r);
            $r['rights'] = $this->getCrmRights()->reminderEditable($r);
        }
        unset($r);

        // List of deals attached to reminders
        $deals = $dm->getList(array(
            'id'           => array_keys($deal_ids),
            'check_rights' => true,
        ));
        foreach ($deals as $d) {
            if ($d['contact_id']) {
                $person_ids[$d['contact_id']] = 1;
            }
        }

        // List of contacts attached to reminders and deals
        $persons = array();
        if ($person_ids) {
            $collection = new crmContactsCollection('/id/'.join(',', array_keys($person_ids)), array(
                'check_rights' => true,
            ));
            $contacts = $collection->getContacts(null, 0, count($person_ids));
            foreach ($contacts as $c) {
                $persons[$c['id']] = new waContact($c);
            }
        }

        $this->view->assign(array(
            'users'                     => $users,
            'user_id'                   => $user_id,
            'reminders'                 => $reminders,
            'deals'                     => $deals,
            'contacts'                  => $persons,
            'completed_reminders_count' => $completed_reminders_count,
            'pages_count'               => ceil($reminders_count / $limit),
            'reminder_max_id'           => waRequest::cookie('reminder_max_id', 0, waRequest::TYPE_INT),
            'reminder_setting'          => $reminder_setting,
            'assign_to_user'            => $assign_to_user,
            'reminder_id'               => $this->reminder_id,
        ));
        wa()->getResponse()->setCookie('reminder_max_id', $rm->select('MAX(id) mid')->fetchField('mid'), time() + 86400);

        wa('crm')->getConfig()->setLastVisitedUrl('reminder/');
    }
}
