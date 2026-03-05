<?php

class crmInvoiceListMethod extends crmApiAbstractMethod
{
    const MAX_LIMIT = 500;
    const DEFAULT_LIMIT = 30;

    protected static $invoice_states = [
        crmInvoiceModel::STATE_DRAFT,
        crmInvoiceModel::STATE_PENDING,
        crmInvoiceModel::STATE_PAID,
        crmInvoiceModel::STATE_REFUNDED,
        crmInvoiceModel::STATE_ARCHIVED,
        crmInvoiceModel::STATE_PROCESSING
    ];

    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $contact_ids = $this->get('contact_id');
        if (!empty($contact_ids) && !is_array($contact_ids)) {
            $contact_ids = [$contact_ids];
        }
        $deal_ids = $this->get('deal_id');
        if (!empty($deal_ids) && !is_array($deal_ids)) {
            $deal_ids = [$deal_ids];
        }
        $state_ids = $this->get('state_id');
        if (!empty($state_ids) && !is_array($state_ids)) {
            $state_ids = [$state_ids];
        }

        $userpic_sz = (int) $this->get('userpic_size');
        $number     = (string) $this->get('number');
        
        $sort       = (string) $this->get('sort');
        $sort       = ($sort ?: 'create_datetime');
        $asc        = (bool) $this->get('asc');
        $offset     = (int) $this->get('offset');
        $limit      = (int) $this->get('limit');

        if (!empty($contact_ids) && !empty(array_filter($contact_ids, function ($value) { return !wa_is_int($value); }))) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid parameter: “%s”.', 'contact_id'), 400);
        }
        if (!empty($deal_ids) && !empty(array_filter($deal_ids, function ($value) { return !wa_is_int($value); }))) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid parameter: “%s”.', 'deal_id'), 400);
        }
        if (!empty($state_ids) && !empty(array_filter($state_ids, function ($value) { return !in_array($value, self::$invoice_states); }))) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid parameter: “%s”.', 'state_id'), 400);
        }
        if (!in_array($sort, ['create_datetime', 'update_datetime', 'payment_datetime'])) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid parameter: “%s”.', 'sort'), 400);
        }
        if (!empty($limit) && $limit < 1) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid parameter: “%s”.', 'limit'), 400);
        }
        if (!empty($offset) && $offset < 1) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid parameter: “%s”.', 'offset'), 400);
        }

        $where  = ['1 = 1'];
        $userpic_sz = (empty($userpic_sz) ? 32 : $userpic_sz);
        $limit = (empty($limit) ? self::DEFAULT_LIMIT : min($limit, self::MAX_LIMIT));
        $filter = [
            'offset' => $offset,
            'limit'  => $limit
        ];
        $invoice_model = $this->getInvoiceModel();
        if (!empty($contact_ids)) {
            $where[] = 'contact_id IN (:contact_ids)';
            $filter['contact_ids'] = $contact_ids;
        }
        if (!empty($deal_ids)) {
            $where[] = 'deal_id IN (:deal_ids)';
            $filter['deal_ids'] = $deal_ids;
        }
        if (!empty($state_ids)) {
            $where[] = 'state_id IN (:state_ids)';
            $filter['state_ids'] = $state_ids;
        }
        if (!empty($number)) {
            $where[] = "number LIKE '%".$invoice_model->escape($number)."%'";
            $filter['number'] = $number;
        }
        $invoices = $invoice_model->query("
            SELECT SQL_CALC_FOUND_ROWS *
            FROM crm_invoice
            WHERE ".implode(' AND ', $where)."
            ORDER BY $sort ".($asc ? 'ASC' : 'DESC')." LIMIT i:offset, i:limit
        ", $filter)->fetchAll();
        $total_count = $invoice_model->query('SELECT FOUND_ROWS()')->fetchField();
        $contact_ids = array_unique(array_column($invoices, 'contact_id'));
        $users = $this->getContactsMicrolist($contact_ids, ['id', 'name', 'userpic'], $userpic_sz);
        $invoices = array_map(function ($inv) use ($users) {
            $_f = [];
            foreach ($users as $_user) {
                if ($inv['contact_id'] == $_user['id']) {
                    $_f['contact'] = $_user;
                    break;
                }
            }
            return $_f + $this->prepareInvoice($inv);
        }, $invoices);

        $this->response = [
            'params' => [
                'offset'      => $offset,
                'limit'       => $limit,
                'total_count' => (int) $total_count,
                'sort'        => [
                    'field' => $sort,
                    'asc'   => $asc
                ]
            ],
            'data' => array_values($invoices)
        ];
    }
}
