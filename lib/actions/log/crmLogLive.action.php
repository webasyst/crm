<?php

class crmLogLiveAction extends crmBackendViewAction
{
    public function execute()
    {
        $log = [];
        $actors = [];
        $actor_ids = [];
        $invoice_ids = [];
        $reminders = [];
        $users = [];
        $user_ids = [];
        $invoices = [];

        $list_params = $this->getListParams();
        $chart_params = $this->getTimeframeParams();
        $is_ui_13 = (wa('crm')->whichUI('crm') === '1.3');

        $lm = new crmLogModel();
        $chart = $lm->getLogLiveChart($list_params, $chart_params);

        if (waRequest::request('chart')) {
            echo json_encode(array('status' => 'ok', 'data' => array('chart' => $chart)));
            exit;
        }

        if ($is_ui_13) {
            $log = $lm->getLogLive($list_params);
            foreach ($log as &$l) {
                $actor_ids[$l['actor_contact_id']] = 1;
                if (stripos($l['action'], 'invoice_') === 0) {
                    $invoice_ids[] = $l['object_id'];
                }
            }
            unset($l);

            if (!$list_params['action_type'] || $list_params['action_type'] == 'reminder') {
                $ids = array();
                if ($list_params['user_id']) {
                    $ids[] = $list_params['user_id'];
                }
                if ($lm->deals) {
                    $ids[] = '-'.join(",-", array_keys($lm->deals));
                }
                $condition = '';
                if ($ids) {
                    $condition = "AND contact_id IN (".join(",", $ids).")";
                }
                $reminders = $this->getReminderModel()->select('*')
                    ->where("complete_datetime IS NULL $condition")
                    ->order('due_date, ISNULL(due_datetime), due_datetime')
                    ->fetchAll('id');
            }
            foreach ($reminders as &$r) {
                $r['state'] = crmHelper::getReminderState($r);
                $r['rights'] = $this->getCrmRights()->reminderEditable($r);
                $user_ids[$r['user_contact_id']] = 1;
            }
            unset($r);

            if ($invoice_ids && (!$list_params['action_type'] || $list_params['action_type'] == 'invoice')) {
                $invoices = $this->getInvoiceModel()->select('*')
                    ->where('id IN (?)', array($invoice_ids))
                    ->fetchAll('id');
            }
        }

        $contact = $list_params['user_id'] ? (new waContact($list_params['user_id'])) : null;

        $creator_contact = null;
        if ($contact) {
            $creator_contact = new waContact($contact->get('create_contact_id'));
        }

        $rights_model = new waContactRightsModel();
        foreach($rights_model->getUsers('crm') as $id) {
            $user_ids[$id] = 1;
        }

        // THIS ARRAY USED BY LogLiveTimeline Tooo!!!!
        $filter_actions = array(
            'all' => array(
                'id'   => 'all',
                'name' => _w('All types'),
            ),
        );
        foreach (wa('crm')->getConfig()->getLogType() as $action => $data) {
            $filter_actions[$action] = array('id' => $action) + $data;
        }

        if ($list_params['action_type'] && !empty($filter_actions[$list_params['action_type']])) {
            $selected_filter_action = $filter_actions[$list_params['action_type']];
        } else {
            $selected_filter_action = reset($filter_actions);
        }

        if ($actor_ids || $user_ids) {
            $collection = new crmContactsCollection(array_keys($actor_ids + $user_ids), array(
                'check_rights' => true,
            ));
            $contacts = $collection->getContacts(null, 0, count($user_ids) + count($actor_ids));
            foreach (array_keys($actor_ids) as $id) {
                if(empty($actors[$id]) && !empty($contacts[$id])) {
                    $actors[$id] = new waContact($contacts[$id]);
                }
            }
            foreach (array_keys($user_ids) as $id) {
                if(empty($users[$id]) && !empty($contacts[$id])) {
                    $users[$id] = new waContact($contacts[$id]);
                }
            }
        }

        $this->view->assign(array(
            'contact'                => $contact,
            'log'                    => $log,
            'actors'                 => $actors,
            'reminders'              => $reminders,
            'invoices'               => $invoices,
            'deals'                  => $lm->deals,
            'creator_contact'        => $creator_contact,
            'list_params'            => $list_params,
            'chart_params'           => $chart_params,
            'users'                  => $users,
            'chart'                  => $chart,
            'filter_actions'         => $filter_actions,
            'selected_filter_action' => $selected_filter_action,
            'can_manage_invoices'    => wa()->getUser()->getRights('crm', 'manage_invoices'),
        ));
        $actions_path = ($is_ui_13 ? 'actions-legacy' : 'actions');
        if (!$list_params['max_id']) {
            $this->setTemplate('templates/' . $actions_path . '/log/LogLive.html');
        } else {
            $this->setTemplate('templates/' . $actions_path . '/log/LogLiveTimeline.html');
        }
        wa('crm')->getConfig()->setLastVisitedUrl('live/');
    }

    protected function getListParams()
    {
        $list_params = array(
            'action_type' => waRequest::get('type', waRequest::cookie('live_action_type'), waRequest::TYPE_STRING_TRIM),
            'user_id'     => waRequest::get('user', waRequest::cookie('live_user_id'), waRequest::TYPE_INT),
            'max_id'      => waRequest::request('max_id', 0, waRequest::TYPE_INT),
            'limit'       => 50,
        );
        if ($list_params['action_type'] == 'all') {
            $list_params['action_type'] = null;
        }
        if (waRequest::get('type') !== null) {
            wa()->getResponse()->setCookie('live_action_type', $list_params['action_type'], time() + 86400);
        }
        if (waRequest::get('user') !== null) {
            wa()->getResponse()->setCookie('live_user_id', $list_params['user_id'], time() + 86400);
        }
        return $list_params;
    }

    public function getTimeframeParams()
    {
        $timeframe = waRequest::request('timeframe', waRequest::cookie('live_timeframe'));
        $from = $to = null;
        if ($timeframe === 'all') {
            $start_date = null;
            $end_date = null;
        } elseif ($timeframe == 'custom') {
            $from = waRequest::request('from', waRequest::cookie('live_from'));
            $start_date = $from ? date('Y-m-d', strtotime($from)) : null;

            $to = waRequest::request('to', waRequest::cookie('live_to'));
            $end_date = $to ? date('Y-m-d', strtotime($to)) : null;
            if ($from !== null || $to !== null) {
                wa()->getResponse()->setCookie('live_from', $from, time() + 86400);
                wa()->getResponse()->setCookie('live_to', $to, time() + 86400);
            }
        } else {
            if (!wa_is_int($timeframe)) {
                $timeframe = 90;
            }
            $start_date = date('Y-m-d', time() - $timeframe * 24 * 3600);
            $end_date = null;
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        if (!$start_date) {
            $lm = new crmLogModel();
            $start_date = $lm->select('DATE(MIN(create_datetime))')->fetchField();
        }
        $group_by = waRequest::request('groupby', 'days', waRequest::TYPE_STRING_TRIM);
        $days = (strtotime($end_date) - strtotime($start_date) + 1) / (60 * 60 * 24);
        if ($days < 31) {
            $group_by = 'days';
        } elseif ($days > 360 && !waRequest::request('groupby')) {
            $group_by = 'months';
        }
        //if ($group_by == 'months') {
        //    $start_date = date('Y-m-01', strtotime($start_date));
        //}
        if (waRequest::request('timeframe') !== null) {
            wa()->getResponse()->setCookie('live_timeframe', $timeframe, time() + 86400);
        }
        return array(
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'group_by'   => $group_by,
            'timeframe'  => $timeframe,
            'from'       => $from,
            'to'         => $to,
        );
    }
}
