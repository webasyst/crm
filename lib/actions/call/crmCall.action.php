<?php

class crmCallAction extends crmBackendViewAction
{
    const USERPIC = 32;

    public function execute()
    {
        $user = $this->getUser();

        $right = $user->getRights('crm', 'calls');
        if ($right == crmRightConfig::RIGHT_CALL_NONE) {
            $this->accessDenied();
        }

        $page = waRequest::request('page', 1, waRequest::TYPE_INT);
        $selected_state = waRequest::request('status', null, waRequest::TYPE_STRING_TRIM);
        $selected_direction = waRequest::request('direction', null, waRequest::TYPE_STRING_TRIM);
        $user_contact_id = waRequest::request('user', null, waRequest::TYPE_INT);
        $contact_id = waRequest::request('contact', null, waRequest::TYPE_INT);
        $deal_id = waRequest::request('deal', null, waRequest::TYPE_INT);
        $time_frame_day = waRequest::request('timeframe', null, waRequest::TYPE_STRING_TRIM);
        $create_start = waRequest::request('start', null, waRequest::TYPE_STRING_TRIM);
        $create_end = waRequest::request('end', null, waRequest::TYPE_STRING_TRIM);
        $iframe = waRequest::request('iframe', 0, waRequest::TYPE_INT);

        if (!empty($iframe) && wa('crm')->whichUI('crm') !== '1.3') {
            $this->setLayout();
            $backend_assets = wa('crm')->event('backend_assets');
        }

        $filter_count = 0;
        // state
        if ($selected_state !== null) {
            $user->setSettings("crm", "calls_state", $selected_state);
        } else {
            $selected_state = $user->getSettings("crm", "calls_state", "ALL");
        }

        // direction
        if ($selected_direction !== null) {
            $user->setSettings("crm", "calls_direction", $selected_direction);
        } else {
            $selected_direction = $user->getSettings("crm", "calls_direction", "all");
        }

        // deal
        if (isset($deal_id) && $deal_id < 1) {
            $deal_id = 0;
        }

        // create datetime
        $create_datetime_begin = null;
        $create_datetime_end = null;
        if (isset($time_frame_day) && $time_frame_day !== 'custom') {
            /** за последние N дней */
            $create_datetime_begin = date('Y-m-d H:i:s', strtotime("- $time_frame_day day"));
            $create_datetime_end   = date('Y-m-d H:i:s');
        } elseif (isset($create_start) || isset($create_end)) {
            /** за интервал */
            if (isset($create_start)) {
                $create_datetime_begin = date('Y-m-d H:i:s', strtotime($create_start));
            }
            if (isset($create_end)) {
                $create_datetime_end = date('Y-m-d H:i:s', strtotime($create_end) + 24*60*60-1);
            }
        } else {
            unset($create_start, $create_end);
        }

        $list_params = [
            'check_rights' => true,
            'user_contact_id' => $user_contact_id,
            'client_contact_id' => $contact_id,
            'deal_id' => $deal_id,
            'create_datetime_begin' => $create_datetime_begin,
            'create_datetime_end' => $create_datetime_end,
            'status_id' => ($selected_state == "ALL" ? null : $selected_state),
            'direction' => ($selected_direction == "all" ? null : $selected_direction),
            'offset' => max(0, $page - 1) * crmConfig::ROWS_PER_PAGE,
            'limit' => crmConfig::ROWS_PER_PAGE,
        ];

        //count filter items
        if ($list_params['user_contact_id'] !== null) {
            $filter_count++;
        }
        if ($list_params['client_contact_id'] !== null) {
            $filter_count++;
        }
        if ($list_params['deal_id'] !== null) {
            $filter_count++;
        }
        if ($list_params['status_id'] !== null) {
            $filter_count++;
        }
        if ($list_params['direction'] !== null) {
            $filter_count++;
        }
        if ($list_params['create_datetime_begin'] !== null || $list_params['create_datetime_end'] !== null) {
            $filter_count++;
        }

        // called from crmCallFinished, so page not need to reload
        $call_ids = $this->getCallIds();
        if (!empty($call_ids)) {
            $list_params['id'] = $call_ids;
        }

        $total_count = 0;
        $cm = new crmCallModel();
        $calls = $cm->getList($list_params, $total_count);

        if (!empty($list_params['create_datetime_end'])) {
            $list_params['create_datetime_end'] = date('Y-m-d', strtotime($create_datetime_end));
        }

        $contact_ids = [];
        $deal_ids = [];
        foreach ($calls as &$c) {
            $c['user_number'] = crmHelper::formatCallNumber($c, 'plugin_user_number');
            $c['client_number'] = crmHelper::formatCallNumber($c);
            $c['gateway_number'] = empty($c['plugin_gateway']) ? '' : crmHelper::formatCallNumber($c, 'plugin_gateway');

            if ($c['client_contact_id']) {
                $contact_ids[$c['client_contact_id']] = 1;
            }
            if ($c['user_contact_id']) {
                $contact_ids[$c['user_contact_id']] = 1;
            }
            if ($c['deal_id']) {
                $deal_ids[intval($c['deal_id'])] = intval($c['deal_id']);
            }
        }
        unset($c);

        if ($right == crmRightConfig::RIGHT_CALL_OWN) {
            $user_ids = [$this->getUser()->getId()];
        } else {
            $user_ids = array_column($cm->query("
                SELECT DISTINCT user_contact_id FROM crm_call
                WHERE user_contact_id IS NOT NULL
            ")->fetchAll(), 'user_contact_id');
        }
        $contact_ids = $contact_ids + array_fill_keys($user_ids, 1);

        $deal_ids = ($deal_id ? array_values($deal_ids) + [$deal_id] : array_values($deal_ids));
        $deals = $this->getDeals($deal_ids);

        $asm = new waAppSettingsModel();

        $states = wa('crm')->getConfig()->getCallStates();
        $filter_states = array(
            "ALL" => array(
            "id" => "ALL",
            "name" => _w("Any state"),
            "color" => "inherit"
        )) + $states;

        if (!empty($selected_state) && !empty($filter_states[$selected_state])) {
            $active_filter_state = $filter_states[$selected_state];
        } else {
            $active_filter_state = reset($filter_states);
        }

        $filter_directions = array(
            "all" => array(
                "id" => "all",
                "name" => _w("Any direction")
            ),
            "in" => array(
                "id" => "in",
                "name" => _w("Incoming")
            ),
            "out" => array(
                "id" => "out",
                "name" => _w("Outgoing")
            ),
        );

        if (!empty($selected_direction) && !empty($filter_directions[$selected_direction])) {
            $active_filter_direction = $filter_directions[$selected_direction];
        } else {
            $active_filter_direction = reset($filter_directions);
        }

        $fm = new crmFunnelModel();

        // Does current user has PBX numbers assigned?
        $pbx_users_model = new crmPbxUsersModel();
        $numbers_assigned = !!$pbx_users_model->getByContact($user->getId());

        $deal = [];
        $contact = [];
        $contacts = $this->getContacts($contact_ids);
        if ($contact_id) {
            /** @var crmContact $c */
            $c = ifset($contacts, $contact_id, new crmContact($contact_id));
            if ($c->exists()) {
                $email = $c->getFirst('email');
                $phone = $c->getFirst('phone');
                $contact = [
                    'name'    => $c->getName(),
                    'userpic' => $c->getPhoto(self::USERPIC),
                    'email'   => ifempty($email, 'value', ''),
                    'phone'   => $this->formatPhone($phone),
                ];
            }
        }
        if ($deal_id) {
            $d = ifset($deals, $deal_id, null);
            if (isset($d['funnel_id'], $d['stage_id'])) {
                $funnel_stages = $this->getFunnelStageModel()->getStagesByFunnel($d['funnel_id']);
                $funnel_stages = ifset($funnel_stages, $d['stage_id'], []);
            }
            if ($d) {
                $deal = [
                    'name' => ifset($d, 'name', ''),
                    'amount' => ifset($d, 'amount', ''),
                    'create_datetime' => ifset($d, 'create_datetime', ''),
                    'funnel' => ifset($funnel_stages, []),
                    'state_id' => ifset($d, 'status_id', ''),
                    'date' => [
                        'expected_date' => ifempty($d, 'expected_date', ''),
                        'closed_datetime' => ifempty($d, 'closed_datetime', '')
                    ]
                ];
            }
        }

        $users = array_filter($contacts, function($el) use ($user_ids) {
            return in_array($el['id'], $user_ids);
        });

        $this->view->assign(array(
            'iframe'                  => $iframe,
            'backend_assets'          => ifset($backend_assets, []),
            'calls'                   => $calls,
            'list_params'             => $list_params,
            'users'                   => $users,
            'contacts'                => $contacts,
            'contact'                 => $contact,
            'deals'                   => $deals,
            'deal'                    => $deal,
            'funnels'                 => $this->getFunnels(),
            'availble_funnels'        => $fm->getAvailableFunnel(),
            'states'                  => $states,
            'filter_states'           => $filter_states,
            'active_filter_state'     => $active_filter_state,
            'filter_directions'       => $filter_directions,
            'active_filter_direction' => $active_filter_direction,
            'pbx_plugins'             => $calls ? null : wa( 'crm' )->getConfig()->getTelephonyPlugins(),
            'total_count'             => $total_count,
            'page'                    => $page,
            'pages_count'             => ceil($total_count / $list_params['limit']),
            'current_page'            => ceil($list_params['offset'] / $list_params['limit']) + 1,
            'call_ts'                 => $asm->get( 'crm', 'call_ts' ),
            'numbers_assigned'        => $numbers_assigned,
            'site_url'                => wa()->getRootUrl(true),
            'filter_count'            =>  $filter_count,
            'timeframe'               => ($time_frame_day === null ? 'all' : $time_frame_day),
        ));
        wa()->getResponse()->setCookie('call_max_id', $cm->select('MAX(id) mid')->fetchField('mid'), time() + 86400);

        wa('crm')->getConfig()->setLastVisitedUrl('call/');
    }

    protected function formatPhone($phone)
    {
        if (empty($phone)) {
            return '';
        }
        class_exists('waContactPhoneField');
        $formatter = new waContactPhoneFormatter();
        $phone = str_replace(str_split("+-() \n\t"), '', $phone);
        return $formatter->format($phone);
    }

    protected function getContacts($ids)
    {
        $contacts = array();
        $collection = new waContactsCollection('/id/'.join(',', array_keys($ids)));
        $col = $collection->getContacts('id,firstname,middlename,lastname,photo,photo_url_32', 0, count($ids));

        foreach ($col as $id => $c) {
            $contacts[$id] = new waContact($c);
            $contacts[$id]['is_visible'] = $this->getCrmRights()->contact($c);
        }

        return $contacts;
    }

    protected function getDeals($ids)
    {
        // @TODO: return $dm->getListByIds($ids);
        $deals = array();
        $dm = new crmDealModel();
        $deals_list = $dm->select('*')->where("id IN('".join("','", $dm->escape($ids))."')")->fetchAll('id');

        foreach ($deals_list as $deal => $d) {
            $deals[$deal] = $d;
            $deals[$deal]['is_visible'] = $this->getCrmRights()->deal($d);
        }
        return $deals;
    }

    protected function getFunnels()
    {
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        return $fsm->withStages($fm->getAllFunnels(true));
    }

    protected function getCallIds()
    {
        $call_ids = $this->getParameter('call_id');
        return is_array($call_ids) ? $call_ids : array();
    }
}
