<?php

class crmReportStagesAction extends crmBackendViewAction
{
    public function execute()
    {
        $funnel_id = waRequest::request('funnel', null, waRequest::TYPE_INT);
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
        $dsm = new crmDealStagesModel();

        if ($timeframe == 30) {
            $start_date = date('Y-m-d', strtotime("-30 days"));
            $end_date = date('Y-m-d');
        } elseif ($timeframe == 365) {
            $start_date = date('Y-m-01', strtotime("-365 days"));
            $end_date = date('Y-m-d');
            $group_by = 'months';
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
        if ($funnel_id && isset($funnels[$funnel_id])) {
            $funnel = $funnels[$funnel_id];
        } else {
            $funnel = reset($funnels);
        }
        $funnel_id = $funnel['id'];

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

        $condition = "funnel_id=".intval($funnel_id);
        $stages = $fsm->select('*')->where($condition)->order('number')->fetchAll('id');
        crmHelper::getFunnelStageColors(array($funnel_id => $funnel), $stages);

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
            'funnel_id'  => $funnel_id,
            'user_id'    => $user['id'],
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'timeframe'  => $timeframe,
            'group_by'   => $group_by,
        );

        $charts = array(
            'charts' => array(
                $dsm->getOverdueNow($chart_params, $stages),
                $dsm->getChartMin($chart_params, $stages)
            ),
            'stages' => $this->getStagesArray($stages)
        );

        $total = array(
            'sec_avg'      => $dm->getTimeAvg($chart_params),
            'closed_count' => $dm->getClosedCount($chart_params),
        );

        $this->view->assign(array(
            'funnels'      => $funnels,
            'funnel'       => $funnel,
            'users'        => $users,
            'user'         => $user,
            'user_id'      => $user['id'],
            'timeframe'    => $timeframe,
            'start_date'   => $sd,
            'end_date'     => $end_date,
            'chart_params' => $chart_params,
            'charts_data'  => $charts,
            'total'        => $total,
        ));
        wa('crm')->getConfig()->setLastVisitedUrl('report/');
    }

    protected function getStagesArray($stages) {
        $result = array();

        foreach ($stages as $_stage) {
            $result[(int)$_stage["number"]] = $_stage;
        }

        return $result;
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
}
