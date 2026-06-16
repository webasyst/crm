<?php

class crmMessageListAction extends crmBackendViewAction
{
    use crmMessageListHelpersTrait;

    public function execute()
    {
        $contact_id = waRequest::request('contact', null, waRequest::TYPE_INT);
        $deal_id = waRequest::request('deal', null, waRequest::TYPE_INT);

        $list_params = [
            'check_rights' => true,
            'contact_id'   => $contact_id,
            'deal_id'      => $deal_id
        ];

        // prepare filters
        $this->prepareFilterByTransport($list_params);
        $this->prepareFilterByDirection($list_params);
        $this->prepareFilterByUser($list_params);

        wa()->getUser()->setSettings("crm", "messages_max_id", $this->getMessageModel()->countByResponsible("messages_max_id"));

        $page = waRequest::request('page', 1);
        $page_of_item = waRequest::request('pt', null, waRequest::TYPE_INT);

        $list_params['limit'] = crmConfig::ROWS_PER_PAGE;
        $list_params['offset'] = max(0, $page - 1) * $list_params['limit'];
        $current_page = ceil($list_params['offset'] / $list_params['limit']) + 1;

        $total_count = 0;
        $messages = $this->getMessageModel()->getList($list_params, $total_count);
        $pages_count = ceil($total_count / $list_params['limit']);
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

            $messages[$m['id']]['update_datetime'] = $m['create_datetime'];
            $messages[$m['id']]['summary_html'] = htmlentities(ifset($m['subject'], ''), ENT_QUOTES, 'UTF-8', false);
            unset($m);
        }

        $last_message_id = 0;
        if ($messages) {
            $last_message_id = max($message_ids);
        }

        if (waRequest::post('check')) {
            echo json_encode(array('status' => 'ok', 'data' => $last_message_id));
            exit;
        }

        // Read messages
        $read_messages = $this->getMessageReadModel()->getReadStatus($message_ids, wa()->getUser()->getId());
        foreach ($read_messages as $rm => $v)
        {
            $messages[$rm]['read'] = 1;
            unset($rm);
        }

        $deals = $this->getDeals($deal_ids);

        $contacts = $this->getContacts($contact_ids);
        $lazy_filter_params = $this->getLazyFilterParams($list_params);

        $this->view->assign([
            'list'                    => $messages,
            'list_params'             => $list_params,
            'contacts'                => $contacts,
            'contacts_all'            => $contacts,
            'deals'                   => $deals,
            'funnels'                 => $this->getFunnels($deals),
            'total_count'             => $total_count,
            'page'                    => $page,
            'pages_count'             => $pages_count,
            'available_funnel'        => $this->getFunnelModel()->getAvailableFunnel(),
            'message_ts'              => (new waAppSettingsModel)->get('crm', 'message_ts'),
            'active_sources'          => $this->getActiveSources(),
            'is_admin'                => wa()->getUser()->isAdmin(),
            'crm_app_url'             => wa()->getAppUrl('crm'),
            'current_page'            => $current_page,
            'page_of_item'            => $page_of_item,
            'last_message_id'         => $last_message_id,
            'load_url'                => '?module=message&action=list&background_process=1',
            'lazy_filter_params'      => $lazy_filter_params,
            'is_flat'                 => true,
            'show_flat_toggle'        => true,
        ]);
        $this->setTemplate('templates/actions/message/MessageListByConversation.html');
        wa('crm')->getConfig()->setLastVisitedUrl('message/');
    }

    protected function getLazyFilterParams($list_params)
    {
        $filter_params = [
            'is_flat_message_view' => 1,
        ];

        foreach (['transport', 'source_id', 'user', 'direction'] as $key) {
            if (isset($list_params[$key])) {
                $filter_params[$key] = $list_params[$key];
            }
        }

        return $filter_params;
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
        $active_sources = $this->getActiveSources();

        // requested transport
        $selected_transport = waRequest::request('transport', null, waRequest::TYPE_STRING_TRIM);
        $is_transport_changed = $selected_transport !== null;
        $iframe = waRequest::request('iframe', 0, waRequest::TYPE_INT);
        if ($selected_transport === null && !$iframe) {
            $selected_transport = wa()->getUser()->getSettings('crm', 'message_list_transport', 'email');
            if ($selected_transport === 'all') {
                $selected_transport = 'email';
            }
        }

        // Flat mode supports email-only list. If user switched transport to a filter that can contain
        // non-email messages, force-disable persistent flat mode preference.
        if ($is_transport_changed) {
            $is_non_email_transport = isset($filter_transports[$selected_transport]) && !in_array($selected_transport, ['email', 'sms']);
            $is_non_email_source = isset($active_sources[$selected_transport]) && $active_sources[$selected_transport]['type'] !== crmSourceModel::TYPE_EMAIL;
            if ($is_non_email_transport || $is_non_email_source) {
                wa()->getUser()->setSettings('crm', 'messages_flat_mode_enabled', 0);
            }
        }

        // Allow only email for flat message list
        if (isset($filter_transports[$selected_transport]) && !in_array($selected_transport, ['email', 'sms'])) {
            $this->redirect(wa()->getConfig()->getBackendUrl(true) . 'crm/message/?' . http_build_query(['transport' => $selected_transport]));
        } elseif (isset($active_sources[$selected_transport]) && $active_sources[$selected_transport]['type'] !== crmSourceModel::TYPE_EMAIL) {
            $this->redirect(wa()->getConfig()->getBackendUrl(true) . 'crm/message/?' . http_build_query(['transport' => $selected_transport]));
        }

        // default filter item to be selected (active)
        $active_filter_transport = reset($filter_transports);

        // ok, found in filter item variants, so be it selected (active)
        $selected_transport_type = strtoupper((string)$selected_transport);
        if (isset($filter_transports[$selected_transport])) {
            $active_filter_transport = $filter_transports[$selected_transport];
        } elseif (isset($active_sources[$selected_transport])) {
            $active_filter_transport = $active_sources[$selected_transport];
            $selected_transport_type = $active_filter_transport['type'];
        }

        // list params filter (for sql query)
        if ($active_filter_transport['id'] !== "all") {
            if (wa_is_int($active_filter_transport['id'])) {
                $list_params['source_id'] = $active_filter_transport['id'];
            } else {
                $list_params['transport'] = $active_filter_transport['id'];
            }
        }

        // remember filter by transport (user)
        if (!$iframe) {
            wa()->getUser()->setSettings('crm', 'message_list_transport', $active_filter_transport['id']);
        }

        $this->view->assign(array(
            'filter_transports'         => $filter_transports,
            'active_filter_transport'   => $active_filter_transport,
            'selected_transport_type'   => $selected_transport_type,
            'forced_flat_transport'     => $selected_transport_type !== crmConversationModel::TYPE_EMAIL 
                                            ? 'email' : $active_filter_transport['id'],
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
                'has_access_filter_users'        => false,
                'has_access_filter_responsibles' => false,
                'filter_users'            => $filter_users,
                'filter_responsibles'     => $filter_users,
                'active_filter_user'        => $active_filter_user,
                'active_filter_responsible' => $active_filter_user,
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
            'has_access_filter_users'        => true,
            'has_access_filter_responsibles' => true,
            'filter_users'            => $filter_users,
            'filter_responsibles'     => $filter_users,
            'active_filter_user'        => $active_filter_user,
            'active_filter_responsible' => $active_filter_user,
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
        $is_flat_message_view = !!(
            waRequest::param('is_flat_message_view', 0, waRequest::TYPE_INT)
            || waRequest::request('is_flat_message_view', 0, waRequest::TYPE_INT)
        );

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

        if (empty($list_params['direction']) && !$is_flat_message_view) {
            $list_params['direction'] = crmMessageModel::DIRECTION_IN;
        }

        wa()->getUser()->setSettings("crm", "messages_direction", $active_filter_direction['id']);

        $this->view->assign(array(
            'filter_directions'       => $filter_directions,
            'active_filter_direction' => $active_filter_direction,
        ));
    }

}
