<?php

trait crmDealListTrait
{
    protected $user_id;
    protected $funnel_id;
    protected $limit = 50;
    protected $list_params = [];
    protected $view_mode = 'list';

    protected function prepareData($api = false, $felds = [])
    {
        // Funnels data
        $funnels = $this->getFunnels($api);
        $funnel = $this->getFunnel($funnels, $api);
        $this->funnel_id = $funnel['id'];

        $users = [];
        if (!$api || in_array('user', $felds)) {
            // All backend users assigned to deals of this funnel
            if ($funnel['id'] != 'all') {
                $user_ids = array_keys($this->getDealModel()->select('DISTINCT(user_contact_id)')->where('funnel_id=?', $funnel['id'])->fetchAll('user_contact_id', true));
            } else {
                $user_ids = array_keys($this->getDealModel()->select('DISTINCT(user_contact_id)')->fetchAll('user_contact_id', true));
            }
            $users = $this->getContactsByIds($user_ids);

            if ($this->user_id && empty($users[$this->user_id])) {
                $this->user_id = ($api && is_numeric($this->user_id) ? null : 'all');
            }
        }

        // List of deals
        $list_params = $this->getListParams($api);
        if ($funnel['id'] != 'all') {
            $list_params['funnel_id'] = $funnel['id'];
        }
        $is_deals_limit = false;
        $count_params = $list_params;
        $count_params['count_results'] = 'only';
        $total_count = $this->getDealModel()->getList($count_params);
        $max_kanban_deals = wa('crm')->getConfig()->getMaxKanbanDeals();
        if (!$api && $this->view_mode == 'thumbs' && $total_count > $max_kanban_deals) {
            $deals = [];
            $is_deals_limit = true;
        } else {
            $deals = $this->getDeals($list_params, $users, $total_count);
            $this->extendByCanDeleteFlag($deals, $funnels);
        }

        $last_message_ids = array();

        $dm = new crmDealModel();

        // Sort list of deals into funnel stages
        foreach ($deals as &$d) {
            if ($d['last_message_id']) {
                $last_message_ids[$d['last_message_id']] = 1;
            }
            $d['reminder_state'] = crmHelper::getDealReminderState($d['reminder_datetime']);
            $d['reminder_title'] = crmHelper::getReminderTitle($d['reminder_state'], $d['reminder_datetime']);

            $source = $dm->getDealSources($d['id']);
            if ($source) {
                $d['source'] = array(
                    'id'   => (int) $source['id'],
                    'type' => $source['type'],
                    'name' => $source['name']
                );
                if (!empty($source['provider'])) {
                    $d['source']['provider'] = $source['provider'];
                    $d['source']['icon'] = wa()->getAppStaticUrl('crm/plugins/'.$source['provider'].'/img', true).$source['provider'].'.png';
                    $plugin = crmSourcePlugin::factory($source['provider'])
                        and $source_obj = $plugin->factorySource($source['id']) 
                        and $d['source'] += $source_obj->getFontAwesomeBrandIcon();
                }
                if ($source['type'] == "IM") {
                    $d['source']['icon_url'] = wa()->getAppStaticUrl('crm/plugins/'.$source['provider'].'/img', true) . $source['provider'].'.png';
                }
            }

            if (!isset($funnel['stages'][$d['stage_id']])) {
                continue;
            }

            $s =& $funnel['stages'][$d['stage_id']];
            $s['amount'] += $d['amount']; // * $d['currency_rate'];
            $s['deals'][$d['id']] = $d;

        }
        unset($d);

        foreach ($funnel['stages'] as &$s) {
            usort($s['deals'], array($this, 'sortDeals'));
        }
        unset($s);

        // Set unreaded messages flag
        if ($last_message_ids) {
            $mrm = new crmMessageReadModel();
            $message_read = $mrm->select('*')->where(
                "contact_id = ".intval(wa()->getUser()->getId()).
                " AND message_id IN ('".join("','", $mrm->escape(array_keys($last_message_ids)))."')"
            )->fetchAll('message_id', true);
        }
        $negative_ids = array();
        foreach ($deals as &$d) {
            $d['message_unread'] = false;
            if ($d['last_message_id']) {
                $d['message_unread'] = !isset($message_read[$d['last_message_id']]);
            }
            $negative_ids[] = -$d['id'];
        }
        unset($d);

        if ($api) {
            return [
                'deals' => $deals,
                'funnels' => $funnels,
                'total_count' => $total_count,
                'deal_tags' => in_array('tags', $felds) ? $this->getDealTags($negative_ids) : [],
                'list_params' => $list_params
            ];
        }

        // Last deal id used to count and highlight new items
        $deal_max_id = $this->getDealModel()->select('MAX(id) mid')->fetchField('mid');
        wa()->getResponse()->setCookie('deal_max_id', $deal_max_id, time() + 86400);

        $filter_contacts = $this->getFilterContacts($users);
        $selected_filter_contact = $this->getSelectedContact(
            $filter_contacts,
            array_key_exists('user_contact_id', $list_params) ? $list_params['user_contact_id'] : null
        );

        $filter_stages = $this->getFilterStages($funnel);
        $stage = reset($filter_stages);
        if (!empty($list_params['stage_id']) && !empty($filter_stages[$list_params['stage_id']])) {
            $stage = $filter_stages[$list_params['stage_id']];
        }
        if (!empty($list_params['status_id']) && !empty($filter_stages[strtolower($list_params['status_id'])])) {
            $stage = $filter_stages[strtolower($list_params['status_id'])];
        }

        $filter_reminders = $this->getFilterReminders();
        $selected_filter_reminders = $this->getSelectedFilterReminders($list_params, $filter_reminders);

        $tag_cloud = $this->getTagModel()->getCloud();
        $active_deal_tag = $this->getActiveDealTag($tag_cloud, $list_params);

        $deal_fields = $this->getFunnelFields($funnel['id']);

        return array(
            "funnels"                   => $funnels,
            "funnel"                    => $funnel,
            "stages"                    => $funnel['stages'],
            "filter_stages"             => $filter_stages,
            "stage"                     => $stage,
            "deals"                     => $deals,
            "is_deals_limit"            => $is_deals_limit,
            "max_kanban_deals"          => $max_kanban_deals,
            "total_count"               => $total_count,
            "filter_contacts"           => $filter_contacts,
            "selected_filter_contact"   => $selected_filter_contact,
            "filter_reminders"          => $filter_reminders,
            "selected_filter_reminders" => $selected_filter_reminders,
            "sort"                      => $list_params['sort'],
            "asc"                       => $list_params['order'] == 'asc',
            "offset"                    => ifset( $list_params['offset'] ) + ifset( $list_params['limit'] ),
            "lazyloading_disable"       => $total_count === null || $total_count <= $list_params['offset'] + $list_params['limit'],
            "deal_max_id"               => $deal_max_id,
            "deal_tags"                 => $this->getDealTags($negative_ids),
            "active_deal_tag"           => $active_deal_tag,
            "tag_cloud"                 => $tag_cloud,
            "limit"                     => $list_params['limit'],
            "active_fields"             => $list_params['fields'],
            "fields"                    => $deal_fields,
            "list_params"               => $list_params,
        );
    }

    protected function extendByCanDeleteFlag(array &$deals, array $funnels)
    {
        $can_delete_deals = [];
        foreach ($funnels as $f) {
            if ($f['id'] !== 'all') {
                $can_delete_deals[$f['id']] = $this->getCrmRights()->funnel($f) >= crmRightConfig::RIGHT_FUNNEL_ALL;
            }
        }

        foreach ($deals as &$deal) {
            $can_delete = isset($can_delete_deals[$deal['funnel_id']]) ? $can_delete_deals[$deal['funnel_id']] : true;
            $deal['can_delete'] = $can_delete;
        }
        unset($deal);

        return $deals;
    }

    protected function getDeals($list_params, $users, &$total_count = null)
    {
        $deals = array();
        $person_ids = array();
        $default_currency = wa()->getSetting('currency');

        if (!isset($list_params['count_results'])) {
            $list_params['count_results'] = func_num_args() > 2;
        }

        foreach ($this->getDealModel()->getList($list_params, $total_count) as $d) {
            $person_ids[$d['contact_id']] = 1;

            $d['original_amount'] = $d['amount'];
            $d['original_currency_id'] = $d['currency_id'];
            if ($d['amount'] && $d['currency_id'] && $d['currency_id'] != $default_currency) {
                $d['amount'] = $d['amount'] * $d['currency_rate'];
                $d['currency_id'] = $default_currency;
            }
            $deals[$d['id']] = $d;
        }

        $persons = $this->getContactsByIds(array_keys($person_ids));
        
        $persons += $users;
        foreach ($deals as $id => &$d) {
            $d['user_name'] = !empty($persons[$d['user_contact_id']]['name']) ? $persons[$d['user_contact_id']]['name'] : null;
            $d['user'] = !empty($persons[$d['user_contact_id']]) ? $persons[$d['user_contact_id']] : null;
            $d['contact'] = !empty($persons[$d['contact_id']]) ? $persons[$d['contact_id']] : null;
        }
        unset($d);

        // get shop orders info
        // for now only id and paid_date fields

        $shop_order_ids = array();
        foreach ($deals as &$d) {

            $d['shop_order'] = array(
                'id' => 0,
                'paid_date' => null
            );

            $order_id = crmShop::getOrderId($d);
            if (wa_is_int($order_id) && $order_id > 0) {
                $d['shop_order']['id'] = $order_id;
                $shop_order_ids[] = $order_id;
            }
        }
        unset($d);

        if (crmShop::appExists() && $shop_order_ids) {
            $m = new waModel();

            $sql = "SELECT id, paid_date FROM `shop_order` WHERE id IN(:ids) AND paid_date IS NOT NULL";
            $paid_order_map = $m->query($sql, array(
                'ids' => $shop_order_ids
            ))->fetchAll('id', true);

            foreach ($deals as &$d) {
                if (isset($paid_order_map[$d['shop_order']['id']])) {
                    $d['shop_order']['paid_date'] = $paid_order_map[$d['shop_order']['id']];
                }
            }
            unset($d);
        }

        return $deals;
    }

    protected function getListParams($api = false)
    {
        // Parameters for crmDealModel->getList()
        $list_params = array(
            'check_rights' => true,
        );
        if ($this->view_mode == 'list') {
            $list_params['offset'] = waRequest::request('offset', 0, waRequest::TYPE_INT);
            $list_params['limit'] = $this->limit;
        }

        // Prepare order-by params
        $deal_list_sort = ($api ? null : wa()->getUser()->getSettings('crm', 'deal_list_sort'));
        $list_sort = explode(' ', $deal_list_sort.' ', 2);
        $sort = waRequest::request('sort', ifempty($list_sort[0], 'create_datetime'), waRequest::TYPE_STRING);
        $asc = (int)waRequest::request('asc', trim($list_sort[1]), waRequest::TYPE_STRING);
        $list_params['sort'] = $sort;
        $list_params['order'] = $asc ? 'asc' : 'desc';
        if (!$api && $deal_list_sort !== $sort.' '.$asc) {
            wa()->getUser()->setSettings('crm', 'deal_list_sort', $sort.' '.$asc);
        }

        // deal_user_id
        if ($api && $this->user_id !== 'all' || $this->user_id !== null && is_numeric($this->user_id)) {
            $list_params['user_contact_id'] = $this->user_id;
        }

        if (!$api && $this->user_id !== wa()->getUser()->getSettings('crm', 'deal_user_id')) {
            wa()->getUser()->setSettings('crm', 'deal_user_id', $this->user_id);
        }

        // 'stage' parameter controls stage_id (int) and status (string 'won' or 'lost')
        $old_stage = ($api ? null : wa()->getUser()->getSettings('crm', 'deal_stage_id'));
        if ($api || $this->funnel_id != 'all') {
            $stage = waRequest::request('stage', $old_stage, waRequest::TYPE_STRING_TRIM);
            if ($stage === 'won' || $stage === 'lost') {
                $list_params['status_id'] = strtoupper($stage);
            } else {
                $stage = (int)$stage;
                if ($stage) {
                    $list_params['stage_id'] = $stage;
                    $list_params['status_id'] = 'OPEN';
                    $stage = (string)$stage;
                } else {
                    $stage = null;
                }
            }
        } else {
            $stage = 'all';
        }
        if (!$api && $stage !== $old_stage) {
            wa()->getUser()->setSettings('crm', 'deal_stage_id', $stage);
        }

        $old_tag = ($api ? null : wa()->getUser()->getSettings('crm', 'deal_tag_id'));
        $tag = waRequest::request('tag', null, waRequest::TYPE_STRING_TRIM);
        if (!$api && !empty($tag) && $tag !== $old_tag) {
            wa()->getUser()->setSettings('crm', 'deal_tag_id', $tag);
        }
        if (empty($tag)) {
            $tag = waRequest::request('tag', $old_tag, waRequest::TYPE_INT);
        }
        if ($tag == "all") {
            $tag = "";
        }
        $list_params['tag_id'] = $tag;

        if (!$api) {
            $deal_fields = $this->getFunnelFields($this->funnel_id);
            $old_fields  = $fields = $list_params['fields'] = array();
            foreach ($deal_fields as $key => $value) {
                $old_fields[$key] = ($api ? null : wa()->getUser()->getSettings('crm', 'deal_field_' . $key));
                $fields[$key] = waRequest::request('field-' . $key, null, waRequest::TYPE_STRING_TRIM);
                if (!$api && $fields[$key] !== $old_fields[$key] && !empty($fields[$key])) {
                    wa()->getUser()->setSettings('crm', 'deal_field_' . $key, $fields[$key]);
                }
                if (empty($fields[$key])) {
                    $fields[$key] = waRequest::request($key, $old_fields[$key], waRequest::TYPE_STRING_TRIM);
                }
                if ($fields[$key] == "all") {
                    $fields[$key] = "";
                }
                $list_params['fields'][$key] = $fields[$key];
            }
        }

        $reminder_states = waRequest::request('reminder', array(), waRequest::TYPE_ARRAY_TRIM);
        $unread = waRequest::request('unread_only', 0, waRequest::TYPE_INT);
        $reminder_states += ($unread === 1 ? [-1 => 'unread'] : []);
        $reminder_states = implode('-', $reminder_states);
        if (!$api) {
            if ($reminder_states) {
                if ($reminder_states != 'none') {
                    wa()->getUser()->setSettings('crm', 'deal_reminder_states', $reminder_states);
                } else {
                    $reminder_states = null;
                    wa()->getUser()->delSettings('crm', 'deal_reminder_states');
                }
            } else {
                $reminder_states = wa()->getUser()->getSettings('crm', 'deal_reminder_states');
            }
        }
        if ($reminder_states) {
            $list_params['reminder_state'] = explode('-', $reminder_states);
        }

        return $list_params + $this->list_params;
    }

    protected function getFunnels($api = false)
    {
        $result = array();

        if ($this->view_mode != 'thumbs') {
            $result['all'] = array(
                'id'   => 'all',
                'name' => _w('All funnels'),
            );
        }

        $dm = $api ? null : new crmDealModel();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $funnels = $fm->getAllFunnels(true);

        foreach ($funnels as $f) {
            $conditions = array(
                'funnel_id' => $f['id'],
            );
            if ($this->user_id === null || is_numeric($this->user_id)) {
                $conditions['user_contact_id'] = $this->user_id;
            }

            if (!$api) {
                $f['deal_count'] = $dm->countOpen($conditions);
            }
            $f['stages'] = $fsm->select('*')->where('funnel_id='.intval($f['id']))->order('number')->fetchAll('id');
            $i = 0;
            foreach ($f['stages'] as &$s) {
                $s['color'] = crmFunnel::getFunnelStageColor($f['open_color'], $f['close_color'], $i++, count($f['stages']));
                $s['deals'] = array();
                $s['amount'] = 0;
            }
            unset($s);

            $result[$f["id"]] = $f;
        }
        unset($f);

        return $result;
    }

    protected function getFunnel($funnels, $api)
    {
        $f_sett = wa()->getUser()->getSettings('crm', 'deal_funnel_id');
        $funnel_id = waRequest::request('funnel');
        if (!$api && $funnel_id === null) {
            $funnel_id = $f_sett;
        }

        if (!isset($funnels[$funnel_id])) {
            $funnel_id = 'all';
        }

        if ($this->view_mode == 'thumbs' && $funnel_id == 'all') {
            $funnel = reset($funnels);
            $funnel_id = $funnel['id'];
        }

        if (!$funnel_id || is_numeric($funnel_id)) {
            $old_funnel_id = $f_sett;

            $deal_fields = $this->getFunnelFields($funnel_id);
            if ($funnel_id !== null) {
                if (!$api && $funnel_id != $old_funnel_id) {
                    wa()->getUser()->setSettings('crm', 'deal_stage_id', null);
                    wa()->getUser()->setSettings('crm', 'deal_user_id', null);
                    wa()->getUser()->setSettings('crm', 'deal_tag_id', null);
                    wa()->getUser()->setSettings('crm', 'deal_reminder_states', null);
                    foreach ($deal_fields as $key => $value) {
                        wa()->getUser()->setSettings('crm', 'deal_field_' . $key, null);
                    }
                }
            } else {
                $funnel_id = $old_funnel_id;
            }
            if ($funnel_id && isset($funnels[$funnel_id])) {
                $funnel = $funnels[$funnel_id];
            } else {
                $funnel = reset($funnels);
                $funnel_id = $funnel['id']; // could be 'all' , that is why latter we check by isset
            }

            $funnel['stages'] = [];
            if (isset($funnels[$funnel['id']]['stages'])) {
                $funnel['stages'] = $funnels[$funnel['id']]['stages']; // $fsm->select('*')->where('funnel_id='.intval($funnel_id))->order('number')->fetchAll('id');
            }

        } else {
            $funnel = array(
                'id'     => 'all',
                'name'   => _w('All funnels'),
                'stages' => array(), // $fsm->select('*')->order('number')->fetchAll('id'),
            );
            foreach ($funnels as $f) {
                if (!empty($f['stages'])) {
                    $funnel['stages'] += $f['stages'];
                }
            }
        }
        if (!$api) {
            wa()->getUser()->setSettings('crm', 'deal_funnel_id', $funnel_id);
        }

        $funnel['currency_id'] = wa()->getSetting('currency');

        return $funnel;
    }

    protected function getContactsByIds($ids)
    {
        if (!$ids) {
            return array();
        }

        $contacts = array();
        $contact_fields = [
            'id',
            'name',
            'photo_url_16',
            'photo_url_32',
            'photo_url_50',
            'jobtitle',
            '_online_status',
            '_event'
        ];
        $collection = new waContactsCollection('/id/'.join(',', $ids)); // !!! check rights?..
        $col = $collection->getContacts(implode(',', $contact_fields), 0, count($ids));
        foreach ($col as $id => $c) {
            $contacts[$id] = new waContact($c);
        }

        return $contacts;
    }

    private function getFilterStages($funnel)
    {

        $out = array(
                array(
                    "id"   => 'all',
                    "name" => _w("All stages"),
                )
            ) + (array)$funnel['stages'] + array(
                "won"  => array(
                    "id"   => "won",
                    "name" => _w("Won")
                ),
                "lost" => array(
                    "id"   => "lost",
                    "name" => _w("Lost")
                ),
            );
        $dm = new crmDealModel();
        foreach ($out as &$o) {
            if (is_numeric($o['id'])) {
                $conditions = array(
                    'funnel_id' => $funnel['id'],
                    'stage_id'  => $o['id'],
                );
                if ($this->user_id === null || is_numeric($this->user_id)) {
                    $conditions['user_contact_id'] = $this->user_id;
                }
                $o['deal_count'] = $dm->countOpen($conditions);
            }
        }
        unset($o);
        return $out;
    }

    protected function getFilterContacts($contacts)
    {
        return array(
                "all" => array(
                    "id"           => 'all',
                    "name"         => _w("All owners"),
                    "photo_url_16" => wa()->getRootUrl()."wa-content/img/userpic20.jpg"
                )
            ) + $contacts + array(
                0 => array(
                    "id"           => 0,
                    "name"         => _w("No assigned owner"),
                    "photo_url_16" => null,
                )
            );
    }

    protected function getSelectedContact($contacts, $selected_contact_id)
    {
        if (array_key_exists($selected_contact_id, $contacts)) {
            $selected_contact = $contacts[$selected_contact_id];
        } else {
            $selected_contact = reset($contacts);
        }
        return $selected_contact;
    }

    protected function sortDeals($a, $b)
    {
        if ($a['reminder_datetime'] && !$b['reminder_datetime']) {
            return 1;
        } elseif (!$a['reminder_datetime'] && $b['reminder_datetime']) {
            return -1;
        }
        return $a['reminder_datetime'] > $b['reminder_datetime'] ? 1 : -1;
    }

    protected function getFilterReminders() {
        $result = array(
            "no" => array(
                "id" => "no",
                "name" => _w( "No reminders assigned" ),
                "icon_class" => "icon16 exclamation"
            ),
            "overdue" => array(
                "id" => "overdue",
                "name" => _w( "Overdue reminder" ),
                "icon_class" => "c-reminder-marker overdue"
            ),
            "burn" => array(
                "id" => "burn",
                "name" => _w( "Reminder due today" ),
                "icon_class" => "c-reminder-marker burn"
            ),
            "actual" => array(
                "id" => "actual",
                "name" => _w( "Reminder due tomorrow" ),
                "icon_class" => "c-reminder-marker actual"
            ),
            "unread" => array(
                "id" => "unread",
                "name" => _w( "Unread messages" ),
                "icon_class" => "icon16 email"
            )
        );

        return $result;
    }

    protected function getSelectedFilterReminders($list_params, $filter_array)
    {
        $result = array();
        if (empty($list_params['reminder_state'])) {
            return $result;
        }
        foreach ($list_params['reminder_state'] as $id) {
            if (!empty($filter_array[$id])) {
                $result[$id] = $filter_array[$id];
            }
        }
        return $result;
    }

    /**
     * @param array $negative_ids
     * @return array
    */
    protected function getDealTags($negative_ids)
    {
        $deals_tags = $this->getTagModel()->getByContact( $negative_ids, false );
        $result = array();

        foreach ($deals_tags as $_deal_id => $_deal_tags) {
            if (!empty($_deal_tags)) {
                $_deal_id = abs($_deal_id);
                $_tags = array();
                foreach ($_deal_tags as $_tag) {
                    if (empty($result[$_tag["id"]])) {
                        $_tags[] = $_tag;
                    }
                }
                $result[$_deal_id] = $_tags;
            }
        }

        return $result;
    }

    /**
     * @param array $tags
     * @param array $list_params
     * @return array|null
     */
    protected function getActiveDealTag($tags, $list_params)
    {
        $result = null;
        $tag_id = null;

        if (!empty($list_params["tag_id"])) {
            $tag_id = $list_params["tag_id"];
        }

        if ($tag_id) {
            foreach ($tags as $_tag) {
                if ((string)$_tag["id"] === (string)$tag_id ) {
                    $result = $_tag;
                    break;
                }
            }
        }

        return $result;
    }

    protected function getFunnelFields($funnel_id)
    {
        $field_constructor = new crmFieldConstructor();
        $fields = $field_constructor->getAllFields();
        $deal_fields = array();
        foreach ($fields['other'] as $f_key => $f_value) {
            if (ifempty($f_value['deal_mirror']) && ($f_value['type'] == 'Radio' || $f_value['type'] == 'Select')) {
                $f_params = crmDealFields::get($f_key)->getFunnelsParameters();
                if ($funnel_id == "all") {
                    foreach ($f_params as $param_key => $param_value) {
                        if (isset($f_params[$param_key]['filter']) && !empty($f_params[$param_key]['filter'])) {
                            $deal_fields[$f_key] = $f_value;
                            break;
                        }
                    }
                } else {
                    if (isset($f_params[$funnel_id]['filter']) && !empty($f_params[$funnel_id]['filter'])) {
                        $deal_fields[$f_key] = $f_value;
                    }
                }
            }
        }
        return $deal_fields;
    }

}