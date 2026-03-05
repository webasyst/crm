<?php

/**
 * List of reminders by user.
 */

class crmReminderActualAction extends crmBackendViewAction
{
    protected $reminder_id;

    public function execute()
    {
        $is_all_reminders = false;
        $limit = crmConfig::ROWS_PER_PAGE;
        $offset = max(0, waRequest::request('page', 1, waRequest::TYPE_INT) - 1) * $limit;
        $type = waRequest::get('type', null, waRequest::TYPE_STRING_TRIM);
        $contact_id = waRequest::get('contact', null, waRequest::TYPE_INT);
        $deal_id = abs(waRequest::get('deal', null, waRequest::TYPE_INT));
        $iframe = (bool) waRequest::get('iframe', 0, waRequest::TYPE_INT);

        $rm = new crmReminderModel();

        if ($this->reminder_id) {
            $reminder = $rm->getById($this->reminder_id);
            if (!$reminder) {
                throw new waException('Reminder not found', 404);
            }
            $user_id = $reminder['user_contact_id'];
        } else {
            $user_id = waRequest::request('user_id', waRequest::param('user_id', null, waRequest::TYPE_STRING_TRIM), waRequest::TYPE_STRING_TRIM);
        }
        if ($user_id === 'all') {
            $user_id = null;
            $is_all_reminders = true;
        } elseif (!$user_id) {
            $user_id = wa()->getUser()->getId();
        }


        /* Sidebar data */
        $reminder_setting = wa()->getUser()->getSettings('crm', 'reminder_setting', 'all');
        if ($reminder_setting != 'all' && $reminder_setting != 'my') {
            $group_ids = preg_split('~\s*,\s*~', $reminder_setting); // не используется?
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

        $dm = new crmDealModel();
        if ($is_all_reminders) {
            $condition = '1=1';
        }
        if (!empty($deal_id)) {
            $condition = 'contact_id = '.((int) $deal_id * -1);
        } elseif (!empty($contact_id)) {
            $deals = $dm->select('id')->where('contact_id = ?', $contact_id)->fetchAll('id');
            $deal_ids = array_map(function ($_deal_id) {return $_deal_id * -1;}, array_keys($deals));
            $condition = 'contact_id IN ('.implode(',', [$contact_id] + $deal_ids).')';
        } elseif (!empty($user_id)) {
            $condition = 'user_contact_id = '.(int) $user_id;
        }
        $condition .= ' AND complete_datetime IS NULL'.($type && $type != 'all' ? " AND type='".$rm->escape($type)."'" : '');

        // List of uncompleted reminders
        $reminders = $rm->select('*')
            ->where($condition)
            ->order('ISNULL(due_date), due_date, ISNULL(due_datetime), due_datetime')
            ->limit("$offset, $limit")
            ->fetchAll('id');

        $reminders_count = $rm->select('COUNT(*) cnt')
            ->where($condition)
            ->fetchField('cnt');

        $deal_ids = [];
        $person_ids = [];
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
            'users'           => $users,
            'user_id'         => $user_id,
            'reminders'       => $reminders,
            'deals'           => $deals,
            'contacts'        => $persons,
            'reminders_count' => $reminders_count,
            'pages_count'     => ceil($reminders_count / $limit),
            'current_page'    => ceil($offset / $limit) + 1,
            'reminder_max_id' => waRequest::cookie('reminder_max_id', 0, waRequest::TYPE_INT),
            'reminder_id'     => $this->reminder_id,
            'highlight_id'    => waRequest::get('highlight_id', 0, waRequest::TYPE_INT),
            'app_url'         => wa()->getAppUrl('crm'),
            'setting_deal_id'           => $deal_id,
            'setting_contact_id'        => $contact_id,
            'iframe'                    => $iframe,
            'is_all_reminders'          => $is_all_reminders,
            'funnels'                   => (new crmFunnelModel)->getAllFunnels(true),
        ));
        wa()->getResponse()->setCookie('reminder_max_id', $rm->select('MAX(id) mid')->fetchField('mid'), time() + 86400);

        //wa('crm')->getConfig()->setLastVisitedUrl('reminder/');
    }
}
