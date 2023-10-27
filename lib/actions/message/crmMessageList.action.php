<?php

class crmMessageListAction extends crmBackendViewAction
{
    public function execute()
    {
        $mm = new crmMessageModel();
        $mrm = new crmMessageReadModel();
        $fm = new crmFunnelModel();
        $asm = new waAppSettingsModel();

        $list_params['check_rights'] = true;

        // prepare filters
        $this->prepareFilterByTransport($list_params);
        $this->prepareFilterByDirection($list_params);
        $this->prepareFilterByUser($list_params);

        wa()->getUser()->setSettings("crm", "messages_max_id", $mm->countByResponsible("messages_max_id"));

        $page = waRequest::request('page', 1);
        $list_params['limit'] = crmConfig::ROWS_PER_PAGE;
        $list_params['offset'] = max(0, $page - 1) * $list_params['limit'];

        $total_count = 0;
        $messages = $mm->getList($list_params, $total_count);
        $this->workup($messages);

        $message_ids = $contact_ids = $deal_ids = array();
        foreach ($messages as &$m) {
            if ($m['contact_id']) {
                $contact_ids[] = intval($m['contact_id']);
            }
            if ($m['deal_id']) {
                $deal_ids[] = intval($m['deal_id']);
            }
            // Mark all messages unread
            $messages[$m['id']]['read'] = 0;
            $message_ids[] = $m['id'];
            unset($m);
        }

        // Read messages
        $read_messages = $mrm->getReadStatus($message_ids, wa()->getUser()->getId());
        foreach ($read_messages as $rm => $v)
        {
            $messages[$rm]['read'] = 1;
            unset($rm);
        }

        $deals = $this->getDeals($deal_ids);

        // Get Sources
        $cs = new crmSourceModel();
        $active_sources = $cs->select("*")->where("type IN ('".crmSourceModel::TYPE_EMAIL."','".crmSourceModel::TYPE_IM."') AND disabled=0")->fetchAll();

        $this->view->assign(array(
            'messages'                => $messages,
            'list_params'             => $list_params,
            'contacts'                => $this->getContacts($contact_ids),
            'deals'                   => $deals,
            'funnels'                 => $this->getFunnels($deals),
            'total_count'             => $total_count,
            'page'                    => $page,
            'available_funnel'        => $fm->getAvailableFunnel(),
            'message_ts'              => $asm->get('crm', 'message_ts'),
            'active_sources'          => $active_sources,
            'is_admin'                => wa()->getUser()->isAdmin(),
            'crm_app_url'             => wa()->getAppUrl('crm'),
        ));

        wa('crm')->getConfig()->setLastVisitedUrl('message/');
    }

    protected function getContacts($contact_ids)
    {
        $contacts = array();
        $collection = new waContactsCollection('/id/'.join(',', $contact_ids));
        $col = $collection->getContacts('id,name,photo_url_32,photo_url_50', 0, count($contact_ids));
        foreach ($col as $id => $c) {
            $contacts[$id] = new waContact($c);
        }
        return $contacts;
    }

    protected function getDeals($deal_ids)
    {
        if ($deal_ids) {
            $dm = new crmDealModel();
            $deals = $dm->select('*')->where("id IN('".join("','", $dm->escape($deal_ids))."')")->fetchAll('id');
            return $deals;
        }
        return array();
    }

    protected function getFunnels($deals)
    {
        $funnels = array();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        foreach ($deals as $d) {
            if ($d['funnel_id'] && empty($funnels[$d['funnel_id']])) {
                $funnel = $fm->getById($d['funnel_id']);
                $funnel['stages'] = $fsm->getStagesByFunnel($funnel);
                $funnels[$d['funnel_id']] = $funnel;
            }
            unset($d);
        }
        return $funnels;
    }

    protected function getUsers()
    {
        $mrm = new crmMessageRecipientsModel();
        $ids = $mrm->getAllRecipientsIds();

        $hash = 'id/'.join(',', $ids);

        $collection = new waContactsCollection($hash);
        $collection->addWhere('c.is_user=1');

        $users = $collection->getContacts('id,name,photo_url_32', 0, count($ids));

        return $users;
    }

    /**
     * Has access to file by users
     * @return bool
     */
    protected function hasAccessToUsersFilter()
    {
        $crm_rights = $this->getCrmRights();
        return $crm_rights->getConversationsRights() >= crmRightConfig::RIGHT_CONVERSATION_ALL;
    }

    protected function workup(&$messages)
    {
        $allowed = $this->getCrmRights()->dropUnallowedMessages($messages);
        foreach ($messages as &$message) {
            $message['can_view'] = !empty($allowed[$message['id']]);
        }
        unset($message);
    }

    protected function getFilterItemsByUser()
    {
        $filter_users = array(
            "all" => array(
                "id"   => "all",
                "name" => _w("All users")
            ),
            wa()->getUser()->getId() => array(
                'id'           => wa()->getUser()->getId(),
                'name'         => wa()->getUser()->getName(),
                'photo_url_32' => wa()->getUser()->getPhoto('32', '32'),
            ),
        );

        foreach ($this->getUsers() as $_user) {
            $filter_users[$_user['id']] = array(
                'id'           => $_user['id'],
                'name'         => $_user['name'],
                'photo_url_32' => $_user['photo_url_32'],
            );
            unset($_user);
        }

        return $filter_users;
    }

    protected function getFilterItemsByTransport()
    {
        return array(
            "all" => array(
                "id" => "all",
                "name" => _w("Any transports")
            ),
            "email" => array(
                "id" => "email",
                "name" => _w("Email")
            ),
            "sms" => array(
                "id" => "sms",
                "name" => _w("SMS")
            ),
            "im" => array(
                "id" => "im",
                "name" => _w("Messengers")
            ),
        );
    }

    protected function getFilterItemsByDirection()
    {
        return array(
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
    }

    /**
     * Prepare filter by transport:
     *  - Get requested filter or get last from DB
     *  - Prepare filter items (including currently selected)
     *  - Remember selected filter in DB
     *  - Send vars to template engine
     *  - Add condition option in list params (for SQL query)
     *
     * @param &$list_params - List params options, to add condition for sql query
     */
    protected function prepareFilterByTransport(&$list_params)
    {
        // filter by transport preparation
        $filter_transports = $this->getFilterItemsByTransport();
        $selected_transport = waRequest::request('transport', null, waRequest::TYPE_STRING_TRIM);
        if ($selected_transport === null) {
            $selected_transport = wa()->getUser()->getSettings("crm", "message_list_transport", "all");
        }

        // default filter item to be selected (active)
        $active_filter_transport = reset($filter_transports);

        // ok, found in filter item variants, so be it selected (active)
        if (isset($filter_transports[$selected_transport])) {
            $active_filter_transport = $filter_transports[$selected_transport];
        }

        // list params filter (for sql query)
        if ($active_filter_transport['id'] !== "all") {
            $list_params['transport'] = $active_filter_transport['id'];
        }

        // remember filter by transport (user)
        wa()->getUser()->setSettings("crm", "message_list_transport", $active_filter_transport['id']);

        $this->view->assign(array(
            'filter_transports'       => $filter_transports,
            'active_filter_transport' => $active_filter_transport,
        ));
    }

    /**
     * Prepare filter by user:
     *  - Get requested filter or get last from DB
     *  - Prepare filter items (including currently selected)
     *  - Remember selected filter in DB
     *  - Send vars to template engine
     *  - Add condition option in list params (for SQL query)
     *
     * @param &$list_params - List params options, to add condition for sql query
     */
    protected function prepareFilterByUser(&$list_params)
    {
        $filter_users = array();
        $active_filter_user = null;
        $has_access_to_user_filter = $this->hasAccessToUsersFilter();

        if (!$has_access_to_user_filter) {
            $this->view->assign(array(
                'has_access_filter_users' => false,
                'filter_users'            => $filter_users,
                'active_filter_user'      => $active_filter_user,
            ));
            return;
        }


        // user filter preparation: list_params, last value (remembered in DB), ui filter itself

        // ui filter items
        $filter_users = $this->getFilterItemsByUser();

        // requested filter by user
        $selected_user = waRequest::request('user', null, waRequest::TYPE_INT);
        if ($selected_user === null) {
            $selected_user = wa()->getUser()->getSettings("crm", "message_list_user", wa()->getUser()->getId());
        }

        // default filter item to be selected (active)
        $active_filter_user = reset($filter_users);

        // ok, found in filter item variants, so be it selected (active)
        if (isset($filter_users[$selected_user])) {
            $active_filter_user = $filter_users[$selected_user];;
        }

        // list params filter (for sql query)
        if (wa_is_int($active_filter_user['id']) && $active_filter_user['id'] > 0) {
            $list_params['user'] = (int)$active_filter_user['id'];
        }

        // remember filter by responsible (user)
        wa()->getUser()->setSettings("crm", "message_list_user", $active_filter_user['id']);

        $this->view->assign(array(
            'has_access_filter_users' => true,
            'filter_users'            => $filter_users,
            'active_filter_user'      => $active_filter_user,
        ));
    }

    /**
     * Prepare filter by direction:
     *  - Get requested filter or get last from DB
     *  - Prepare filter items (including currently selected)
     *  - Remember selected filter in DB
     *  - Send vars to template engine
     *  - Add condition option in list params (for SQL query)
     *
     * @param &$list_params - List params options, to add condition for sql query
     */
    protected function prepareFilterByDirection(&$list_params)
    {
        // filter items
        $filter_directions = $this->getFilterItemsByDirection();

        // requested direction
        $selected_direction = waRequest::request('direction', null, waRequest::TYPE_STRING_TRIM);
        if ($selected_direction === null) {
            $selected_direction = wa()->getUser()->getSettings("crm", "messages_direction", "all");
        }

        // default value
        $active_filter_direction = reset($filter_directions);

        // ok, found in filter item variants, so be it selected (active)
        if (isset($filter_directions[$selected_direction])) {
            $active_filter_direction = $filter_directions[$selected_direction];
        }

        // list params filter (for sql query)
        if ($active_filter_direction['id'] !== "all") {
            $list_params['direction'] = $active_filter_direction['id'];
        }

        wa()->getUser()->setSettings("crm", "messages_direction", $active_filter_direction['id']);

        $this->view->assign(array(
            'filter_directions'       => $filter_directions,
            'active_filter_direction' => $active_filter_direction,
        ));
    }
}
