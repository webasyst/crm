<?php

class crmCallAction extends crmBackendViewAction
{
    public function execute()
    {
        $user = $this->getUser();

        $right = $user->getRights('crm', 'calls');
        if ($right == crmRightConfig::RIGHT_CALL_NONE) {
            $this->accessDenied();
        }

        $list_params = $this->getListParams();

        $selected_state = waRequest::request('status', null, waRequest::TYPE_STRING_TRIM);
        $selected_direction = waRequest::request('direction', null, waRequest::TYPE_STRING_TRIM);

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

        $list_params['status_id'] = ($selected_state == "ALL" ? null : $selected_state);
        $list_params['direction'] = ($selected_direction == "all" ? null : $selected_direction);

        $page = waRequest::request('page', 1);
        $list_params['limit'] = crmConfig::ROWS_PER_PAGE;
        $list_params['offset'] = max(0, $page - 1) * $list_params['limit'];

        // called from crmCallFinished, so page not need to reload
        $call_ids = $this->getCallIds();
        if (!empty($call_ids)) {
            $list_params['id'] = $call_ids;
        }

        $total_count = 0;

        $cm = new crmCallModel();

        $calls = $cm->getList(array_merge($list_params, array(
            'check_rights' => true
        )), $total_count);

        $contact_ids = $deal_ids = array();
        foreach ($calls as &$c) {
            $c['user_number'] = crmHelper::formatCallNumber($c, 'plugin_user_number');
            $c['client_number'] = crmHelper::formatCallNumber($c);

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

        $deal_ids = array_values($deal_ids);
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

        //

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

        //
        $fm = new crmFunnelModel();

        // Does current user has PBX numbers assigned?
        $pbx_users_model = new crmPbxUsersModel();
        $numbers_assigned = !!$pbx_users_model->getByContact($user->getId());

        $this->view->assign(array(
            'calls'                   => $calls,
            'list_params'             => $list_params,
            'contacts'                => $this->getContacts( $contact_ids ),
            'deals'                   => $deals,
            'funnels'                 => $this->getFunnels( $deals ),
            'availble_funnels'        => $fm->getAvailableFunnel(),
            'states'                  => $states,
            'filter_states'           => $filter_states,
            'active_filter_state'     => $active_filter_state,
            'filter_directions'       => $filter_directions,
            'active_filter_direction' => $active_filter_direction,
            'pbx_plugins'             => $calls ? null : wa( 'crm' )->getConfig()->getTelephonyPlugins(),
            'total_count'             => $total_count,
            'page'                    => $page,
            'call_ts'                 => $asm->get( 'crm', 'call_ts' ),
            'numbers_assigned'        => $numbers_assigned,
        ));
        wa()->getResponse()->setCookie('call_max_id', $cm->select('MAX(id) mid')->fetchField('mid'), time() + 86400);

        wa('crm')->getConfig()->setLastVisitedUrl('call/');
    }

    protected function getListParams()
    {
        $list_params = array();

        $user_contact_id = waRequest::request('user', null, waRequest::TYPE_INT);
        $list_params['user_contact_id'] = $user_contact_id;

        return $list_params;
    }

    protected function getContacts($ids)
    {
        $contacts = array();
        $collection = new waContactsCollection('/id/'.join(',', array_keys($ids)));
        $col = $collection->getContacts(wa('crm')->getConfig()->getContactFields(), 0, count($ids));

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

    protected function getFunnels($deals)
    {
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        return $fsm->withStages($fm->getAllFunnels());
    }

    protected function getCallIds()
    {
        $call_ids = $this->getParameter('call_id');
        return is_array($call_ids) ? $call_ids : array();
    }
}
