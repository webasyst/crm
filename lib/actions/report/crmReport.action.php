<?php

class crmReportAction extends crmBackendViewAction
{
    public function execute()
    {
        $funnel_id = waRequest::request('funnel', null, waRequest::TYPE_STRING_TRIM);
        $user_id = waRequest::request('user', null, waRequest::TYPE_INT);
        $timeframe = waRequest::request('timeframe', null, waRequest::TYPE_STRING_TRIM);
        $start_date = waRequest::request('start', wa()->getUser()->getSettings('crm', 'report_start_date', date('Y-m-d', strtotime('-1 month'))), waRequest::TYPE_STRING_TRIM);
        $end_date = waRequest::request('end', wa()->getUser()->getSettings('crm', 'report_end_date', date('Y-m-d')), waRequest::TYPE_STRING_TRIM);
        $group_by = waRequest::request('groupby', wa()->getUser()->getSettings('crm', 'report_groupby'), waRequest::TYPE_STRING_TRIM);

        // fix
        $start_date = date('Y-m-d', strtotime($start_date));
        $end_date = date('Y-m-d', strtotime($end_date));

        if ($timeframe === null && !waRequest::get('start') && !waRequest::get('end')) {
            $timeframe = wa()->getUser()->getSettings('crm', 'report_timeframe');
        }

        if ($funnel_id !== null) {
            wa()->getUser()->setSettings('crm', 'report_funnel_id', $funnel_id);
        } else {
            $funnel_id = wa()->getUser()->getSettings('crm', 'report_funnel_id', wa()->getUser()->getSettings('crm', 'deal_funnel_id'));
        }
        if ($user_id !== null) {
            wa()->getUser()->setSettings('crm', 'report_deal_user_id', $user_id);
        } else {
            $user_id = wa()->getUser()->getSettings('crm', 'report_deal_user_id');
        }
        if ($timeframe !== null) {
            wa()->getUser()->setSettings('crm', 'report_timeframe', $timeframe);
        }
        if ($start_date !== null) {
            wa()->getUser()->setSettings('crm', 'report_start_date', $start_date);
        }
        if ($end_date !== null) {
            wa()->getUser()->setSettings('crm', 'report_end_date', $end_date);
        }
        if ($group_by !== null) {
            wa()->getUser()->setSettings('crm', 'report_group_by', $group_by);
        }

        $dm = new crmDealModel();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        if ($timeframe == 30) {
            $start_date = date('Y-m-d', strtotime("-30 days"));
            $end_date = date('Y-m-d');
        } elseif ($timeframe == 365) {
            $start_date = date('Y-m-d', strtotime("-365 days"));
            $end_date = date('Y-m-d');
        } elseif ($timeframe == "all") {
            if ($start_date = $dm->select('MIN(closed_datetime) dt')->fetchField('dt')) {
                $start_date = date('Y-m-d', strtotime($start_date));
            } else {
                $start_date = date('Y-m-d');
            }
            $end_date = date('Y-m-d');
        } elseif (waRequest::get('start') || waRequest::get('end')) { // $timeframe == 'custom' ???
            $timeframe = 'custom';
        } else {
            $timeframe = 90;
            $start_date = date('Y-m-d', strtotime("-90 days"));
            $end_date = date('Y-m-d');
        }

        $funnels = $fm->getAllFunnels();
        if (!$funnels) {
            $actions_path = wa('crm')->whichUI('crm') === '1.3' ? 'actions-legacy' : 'actions';
            $this->setTemplate('templates/' . $actions_path . '/deal/DealNoFunnel.html');
            return;
        }
        if (waRequest::get('chart')) {
            $funnels = array(
                    'all' => array('id' => 'all', 'name' => _w('All funnels'))
                ) + $funnels;
        }
        if ($funnel_id && isset($funnels[$funnel_id])) {
            $funnel = $funnels[$funnel_id];
        } else {
            $funnel = reset($funnels);
        }
        $funnel_id = $funnel['id'];

        $deal_fields = $this->getFunnelFields($funnel['id']);
        $active_fields = array();
        foreach ($deal_fields as $deal_field_key => $deal_field_val) {
            if (!empty(waRequest::request('field-'.$deal_field_val['id']))) {
                $list_params['fields'][$deal_field_key] = waRequest::request('field-'.$deal_field_val['id']);
                $active_fields[$deal_field_key] = waRequest::request('field-'.$deal_field_val['id']);
            }
        }

        // All backend users assigned to deals of this funnel
        $condition = is_numeric($funnel['id']) ? 'funnel_id='.intval($funnel['id']) : '1=1';
        $user_ids = array_keys($dm->select('DISTINCT(user_contact_id)')->where($condition)->fetchAll('user_contact_id', true));
        $users = $this->getContactsByIds($user_ids);
        $users = array(
                "all" => array(
                    "id"           => 'all',
                    "name"         => _wp("All responsible users"),
                    "photo_url_16" => wa()->whichUI() === '2.0' ? wa()->getRootUrl()."wa-content/img/userpic.svg" : wa()->getRootUrl()."wa-content/img/userpic20.jpg"
                )
            ) + $users;
        if ($user_id !== null && !empty($users[$user_id])) {
            $user = $users[$user_id];
        } else {
            $user = reset($users);
        }
        if (!isset($users[$user_id])) {
            $user_id = $user['id'];
            wa()->getUser()->setSettings('crm', 'report_deal_user_id', $user_id);
        }

        $condition = is_numeric($funnel_id) ? ("funnel_id=".intval($funnel_id)) : 1;
        $list_params = $this->getListParams();
        $tag_cloud = wa()->whichUI() === '2.0' ? $this->getTagModel()->getCloudFast() : $this->getTagModel()->getCloud();
        $popular_cloud = $this->getTagModel()->getPopularTagsSort(15);
        $active_deal_tag = $this->getActiveDealTag($tag_cloud, $list_params);
        $stages = $fsm->select('*')->where($condition)->order('number')->fetchAll('id');

        $conditions = array(
          'start_date'    => $start_date,
          'end_date'      => $end_date,
          'user_id'       => $user['id'],
          'funnel_id'     => $funnel_id,
          'active_tag'    => isset($active_deal_tag['id']) ? $active_deal_tag['id'] : null,
          'active_fields' => $active_fields
        );

        $lost = $dm->getLostDeals($conditions);
        $closed = $dm->getClosedDeals($conditions);
        $rest = $closed;

        foreach ($stages as &$s) {
            $s['rest_percent'] = $closed ? ($rest * 100 / $closed) : 0;
            $s['rest'] = $rest;
            $s['lost'] = 0;
            foreach ($lost as $l) {
                if ($s['id'] == $l['stage_id']) {
                    $rest -= $l['cnt'];
                    $percent = $closed ? ($rest * 100 / $closed) : 0;
                    $s['rest_percent'] = $percent;
                    $s['rest'] = $rest;
                    $s['lost'] = $l['cnt'];
                }
            }
        }
        unset($s);

        $reasons = $dm->getDealsReasons($conditions);
        $won_deals_stat = $dm->getWonDealsStat($conditions);
        $sd = $start_date;

        if ($timeframe == 365) {
            $group_by = 'months';
            $start_date = date('Y-m-01', strtotime($start_date));
        }
        if (!$group_by) {
            $days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
            if ($days > 300) {
                $group_by = 'months';
                $start_date = date('Y-m-01', strtotime($start_date));
            } else {
                $group_by = 'days';
            }
        }

        $chart_params = array(
            'funnel_id'     => $funnel_id,
            'user_id'       => $user['id'],
            'start_date'    => $start_date,
            'end_date'      => $end_date,
            'timeframe'     => $timeframe,
            'group_by'      => $group_by,
            'active_tag'    => $active_deal_tag,
            'active_fields' => $active_fields
        );

        $charts = null;
        if (waRequest::get('chart')) {
            $charts = array(
                'sum' => $dm->getWonChart($chart_params, 'sum', $funnels),
                'qty' => $dm->getWonChart($chart_params, 'qty', $funnels)
            );
        }

        $this->view->assign(array(
            'funnels'         => $funnels,
            'funnel'          => $funnel,
            'stages'          => $stages,
            'users'           => $users,
            'user'            => $user,
            'timeframe'       => $timeframe,
            'start_date'      => $sd,
            'end_date'        => $end_date,
            'closed'          => $closed,
            'reasons'         => $reasons,
            'won_deals_stat'  => $won_deals_stat,
            'chart_params'    => $chart_params,
            'charts'          => $charts,
            'active_deal_tag' => $active_deal_tag,
            'tag_cloud'       => $tag_cloud,
            'popular_cloud'   => $popular_cloud,
            "active_fields"   => $active_fields,
            "fields"          => $deal_fields,
        ));
        wa('crm')->getConfig()->setLastVisitedUrl('report/');
    }

    protected function getContactsByIds($ids)
    {
        if (!$ids) {
            return array();
        }
        $contacts = array();
        $collection = new waContactsCollection('/id/'.join(',', $ids)); // !!! check rights?..
        $col = $collection->getContacts(wa('crm')->getConfig()->getContactFields(), 0, count($ids));
        foreach ($col as $id => $c) {
            $contacts[$id] = new waContact($c);
        }
        return $contacts;
    }

    protected function getListParams()
    {
        $list_params = array();

        if ($tag = waRequest::request('tag', null, waRequest::TYPE_INT)) {
            $list_params['tag_id'] = $tag;
        }

        return $list_params;
    }

    /**
     * @param array $tags
     * @param array $list_params
     * @return array|null
     */
    protected function getActiveDealTag($tags, $list_params) {
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
                if (isset($f_params[$funnel_id]['filter']) && !empty($f_params[$funnel_id]['filter'])) {
                    $deal_fields[$f_key] = $f_value;
                }
            }
        }
        return $deal_fields;
    }
}
