<?php

class crmReportInvoicesAction extends crmBackendViewAction
{
    public function execute()
    {
        $company_id = waRequest::request('company', null, waRequest::TYPE_STRING_TRIM);
        $user_id = waRequest::request('user', null, waRequest::TYPE_STRING_TRIM);
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

        if ($company_id !== null) {
            wa()->getUser()->setSettings('crm', 'report_company_id', $company_id);
        } else {
            $company_id = wa()->getUser()->getSettings('crm', 'report_company_id', 'all');
        }
        if ($user_id !== null) {
            wa()->getUser()->setSettings('crm', 'report_creator_id', $user_id);
        } else {
            $user_id = wa()->getUser()->getSettings('crm', 'report_creator_id', 'all');
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

        $im = new crmInvoiceModel();
        $cm = new crmCompanyModel();

        if ($timeframe == 30) {
            $start_date = date('Y-m-d', strtotime("-30 days"));
            $end_date = date('Y-m-d');
        } elseif ($timeframe == 365) {
            $start_date = date('Y-m-d', strtotime("-365 days"));
            $end_date = date('Y-m-d');
        } elseif ($timeframe == "all") {
            if ($start_date = $im->select('MIN(payment_datetime) dt')->where("state_id = 'PAID'")->fetchField('dt')) {
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

        $companies = $cm->getAll('id');
        if (!$companies) {
            throw new waException('Companies not found', 404);
        }

        if ($company_id != 'all') {
            if ($company_id && isset($companies[$company_id])) {
                $company = $companies[$company_id];
            } else {
                $company = reset($companies);
            }
            $company_id = (int)$company['id'];
        }
        $companies = array(
                'all' => array(
                    'id'   => 'all',
                    'name' => _w('All companies'),
                )
            ) + $companies;

        // All backend users assigned to invoices
        $user_ids = array_keys($im->select('DISTINCT(creator_contact_id)')->where("state_id = 'PAID'")->fetchAll('creator_contact_id', true));

        $users = $this->getContactsByIds($user_ids);
        $users = array(
                "all" => array(
                    "id"           => 'all',
                    "name"         => _wp("All responsibles"),
                    "photo_url_16" => wa()->getRootUrl()."wa-content/img/userpic20.jpg"
                )
            ) + $users;
        if ($user_id != 'all') {
            if ($user_id && isset($users[$user_id])) {
                $user = $users[$user_id];
            } else {
                $user = reset($users);
            }
            $user_id = (int)$user['id'];
        }

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
            'company_id' => $company_id,
            'user_id'    => $user_id,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'timeframe'  => $timeframe,
            'group_by'   => $group_by,
        );

        $charts = array(
            'sum' => $im->getPaidChart($chart_params, 'sum'),
            'qty' => $im->getPaidChart($chart_params, 'qty')
        );

        $this->view->assign(array(
            'companies'          => $companies,
            'company_id'         => $company_id,
            'users'              => $users,
            'user_id'            => $user_id,
            'timeframe'          => $timeframe,
            'start_date'         => $start_date,
            'end_date'           => $end_date,
            'chart_params'       => $chart_params,
            'charts'             => $charts,
            'paid_invoices_stat' => $this->getPaidInvoicesStat($company_id, $user_id, $start_date, $end_date),
            'is_invoices'        => true,
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

    protected function getPaidInvoicesStat($company_id, $user_id, $start_date, $end_date)
    {
        $default_currency = wa()->getSetting('currency');

        $im = new crmInvoiceModel();

        $list_params = array(
            'company_id'   => $company_id,
            'state_id'     => 'PAID',
            'check_rights' => true,
        );
        if ($user_id && is_numeric($user_id)) {
            $list_params['creator_contact_id'] = $user_id;
        }
        if ($start_date) {
            $list_params['payment_start_date'] = $start_date;
        }
        if ($end_date) {
            $list_params['payment_end_date'] = $end_date;
        }

        $stat = array('count' => 0, 'amount' => 0, 'currency_id' => $default_currency);

        foreach ($im->getList($list_params) as $d) {
            if ($d['currency_id'] == $default_currency) {
                $stat['amount'] += $d['amount'];
            } else {
                $stat['amount'] += $d['amount'] * $d['currency_rate'];
            }
            $stat['count']++;
        }
        return $stat;
    }
}
