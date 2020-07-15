<?php

class crmMessageListByConversationAction extends crmBackendViewAction
{
    public function execute()
    {
        $mm = new crmMessageModel();
        $cm = new crmConversationModel();
        $fm = new crmFunnelModel();
        $asm = new waAppSettingsModel();

        $page = waRequest::request('page', null, waRequest::TYPE_INT);

        $list_params['check_rights'] = true;

        // prepare filters
        $this->prepareFilterByResponsible($list_params);
        $this->prepareFilterByTransport($list_params);

        wa()->getUser()->setSettings("crm", "messages_max_id", $mm->countByResponsible("messages_max_id"));

        $list_params['limit'] = crmConfig::ROWS_PER_PAGE;
        $list_params['offset'] = max(0, $page - 1) * $list_params['limit'];

        $total_count = 0;

        $conversations = $cm->getList($list_params, $total_count);
        $this->workup($conversations);


        $deal_ids = array();
        $contact_ids = array();
        $last_message_id = 0;
        foreach ($conversations as $c) {
            if ($c['deal_id']) {
                $deal_ids[$c['deal_id']] = $c['deal_id'];
            }
            $contact_ids[$c['contact_id']] = $c['contact_id'];
            $last_message_id = $c['last_message_id'] > $last_message_id ? $c['last_message_id'] : $last_message_id;
        }

        if (waRequest::post('check')) {
            echo json_encode(array('status' => 'ok', 'data' => $last_message_id));
            exit;
        }

        //


        // Get Deals
        $deals = $this->getDeals($deal_ids);

        // Get Sources
        $cs = new crmSourceModel();
        $active_sources = $cs->select("*")->where("type IN ('".crmSourceModel::TYPE_EMAIL."','".crmSourceModel::TYPE_IM."') AND disabled=0")->fetchAll();

        $this->view->assign(array(
            'conversations'             => $conversations,
            'list_params'               => $list_params,
            'contacts'                  => $this->getContacts($contact_ids),
            'deals'                     => $deals,
            'funnels'                   => $this->getFunnels($deals),
            'total_count'               => $total_count,
            'page'                      => $page,
            'available_funnel'          => $fm->getAvailableFunnel(),
            'message_ts'                => $asm->get('crm', 'message_ts'),
            'active_sources'            => $active_sources,
            'crm_app_url'               => wa()->getAppUrl('crm'),
            'last_message_id'           => $last_message_id,
        ));

        wa('crm')->getConfig()->setLastVisitedUrl('message/');
    }

    protected function getDeals($deal_ids)
    {
        if (!$deal_ids) {
            return array();
        }
        $dm = new crmDealModel();
        $deals = $dm->select('*')->where("id IN('".join("','", $dm->escape($deal_ids))."')")->fetchAll('id');
        return $deals;
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
        }
        return $funnels;
    }

    /**
     * @return array
     * @throws waException
     */
    protected function getContacts($ids)
    {
        $contacts = array();
        if ($ids) {
            $collection = new waContactsCollection('/id/'.join(',', array_keys($ids)));
            $col = $collection->getContacts(wa('crm')->getConfig()->getContactFields(), 0, count($ids));
            foreach ($col as $id => $c) {
                $contacts[$id] = new waContact($c);
                $contacts[$id]['is_visible'] = $this->getCrmRights()->contact($c);
                if ($id == wa()->getUser()->getId()) {
                    $me = $contacts[$id];
                }
            }
        }
        if (isset($me)) {
            unset($contacts[wa()->getUser()->getId()]);
            $contacts = array(wa()->getUser()->getId() => $me) + $contacts;
        }
        return $contacts;
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

    protected function workup(&$conversations)
    {
        $allowed = $this->getCrmRights()->dropUnallowedConversations($conversations);
        foreach ($conversations as &$conversation) {
            $conversation['can_view'] = !empty($allowed[$conversation['id']]);
        }
        unset($conversation);
    }

    protected function getResponsibleContacts()
    {
        $cm = new crmConversationModel();
        $ids = $cm->select('DISTINCT(user_contact_id) id')->where('user_contact_id IS NOT NULL AND user_contact_id <> 0')->fetchAll('id', true);
        return $this->getContacts($ids);
    }

    protected function getFilterItemsByResponsible($responsible_contacts = array())
    {

        $filter_responsibles = array(
            "all" => array(
                "id"   => "all",
                "name" => _w("All owners")
            ),
        );
        foreach ($responsible_contacts as $r) {
            $filter_responsibles[$r['id']] = array(
                'id'           => $r['id'],
                'name'         => $r['name'],
                'photo_url_32' => $r['photo_url_32'],
            );
        }
        $filter_responsibles += array(
            0 => array(
                'id'           => 0,
                'name'         => _w('No assigned owner'),
                'photo_url_32' => null,
            )
        );
        return $filter_responsibles;
    }

    protected function getFilterItemsByTransport()
    {
        return array(
            "all"   => array(
                "id"   => "all",
                "name" => _w("Any transports")
            ),
            "email" => array(
                "id"   => "email",
                "name" => _w("Email")
            ),
            "im"    => array(
                "id"   => "im",
                "name" => _w("Messengers")
            ),
        );
    }

    /**
     * Prepare filter by responsible:
     *  - Get requested filter or get last from DB
     *  - Prepare filter items (including currently selected)
     *  - Remember selected filter in DB
     *  - Send vars to template engine
     *  - Add condition option in list params (for SQL query)
     *
     * @param &$list_params - List params options, to add condition for sql query
     */
    protected function prepareFilterByResponsible(&$list_params)
    {
        // all responsible contacts
        $responsible_contacts = $this->getResponsibleContacts();

        $filter_responsibles = array();
        $active_filter_responsible = null;
        $has_access_to_user_filter = $this->hasAccessToUsersFilter();

        if (!$has_access_to_user_filter) {
            $this->view->assign(array(
                'has_access_filter_responsibles' => false,
                'responsibles'                   => $responsible_contacts,
                'filter_responsibles'            => $filter_responsibles,
                'active_filter_responsible'      => $active_filter_responsible,
            ));
            return;
        }

        // ui filter items
        $filter_responsibles = $this->getFilterItemsByResponsible($responsible_contacts);

        // requested filter by responsible
        $selected_responsible = waRequest::request('responsible', null, waRequest::TYPE_STRING_TRIM);
        if ($selected_responsible === null) {
            $selected_responsible = wa()->getUser()->getSettings('crm', "message_list_user", 'all');
        }

        // default filter item to be selected (active)
        $active_filter_responsible = reset($filter_responsibles);

        // ok, found in filter item variants, so be it selected (active)
        if (isset($filter_responsibles[$selected_responsible])) {
            $active_filter_responsible = $filter_responsibles[$selected_responsible];
        }

        // list params filter (for sql query)
        if (wa_is_int($active_filter_responsible['id']) && $active_filter_responsible['id'] >= 0) {
            $list_params['responsible'] = (int)$active_filter_responsible['id'];
        }

        // remember filter by responsible (user)
        wa()->getUser()->setSettings('crm', "message_list_user", $active_filter_responsible['id']);

        $this->view->assign(array(
            'has_access_filter_responsibles' => true,
            'responsibles'                   => $responsible_contacts,
            'filter_responsibles'            => $filter_responsibles,
            'active_filter_responsible'      => $active_filter_responsible,
        ));
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

        // requested transport
        $selected_transport = waRequest::request('transport', null, waRequest::TYPE_STRING_TRIM);
        if ($selected_transport === null) {
            $selected_transport = wa()->getUser()->getSettings('crm', 'message_list_transport', 'all');
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
        wa()->getUser()->setSettings('crm', 'message_list_transport', $active_filter_transport['id']);

        $this->view->assign(array(
            'filter_transports'         => $filter_transports,
            'active_filter_transport'   => $active_filter_transport,
        ));
    }
}
