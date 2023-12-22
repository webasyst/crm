<?php

class crmInvoiceListMethod extends crmApiAbstractMethod
{
    const MAX_LIMIT = 500;
    const DEFAULT_LIMIT = 30;

    public function execute()
    {
        $contact_id = (int) $this->get('contact_id');
        $userpic_sz = (int) $this->get('userpic_size');
        $deal_id    = (int) $this->get('deal_id');
        $number     = (string) $this->get('number');
        $state_id   = (string) $this->get('state_id');
        $sort       = (string) $this->get('sort');
        $sort       = ($sort ?: 'create_datetime');
        $asc        = (bool) $this->get('asc');
        $offset     = (int) $this->get('offset');
        $limit      = (int) $this->get('limit');

        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } elseif (!empty($contact_id) && $contact_id < 1) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } elseif (!empty($deal_id) && $deal_id < 1) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        } elseif (!empty($state_id) && !in_array($state_id, ['PENDING', 'PAID', 'REFUNDED', 'ARCHIVED', 'DRAFT', 'PROCESSING'])) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid parameter: “%s”.', 'state_id'), 400);
        } elseif (!in_array($sort, ['create_datetime', 'update_datetime', 'payment_datetime'])) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid parameter: “%s”.', 'sort'), 400);
        } elseif (!empty($limit) && $limit < 1) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid parameter: “%s”.', 'limit'), 400);
        } elseif (!empty($offset) && $offset < 1) {
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
        if (!empty($contact_id)) {
            $where[] = 'contact_id = i:contact_id';
            $filter['contact_id'] = $contact_id;
        }
        if (!empty($deal_id)) {
            $where[] = 'deal_id = i:deal_id';
            $filter['deal_id'] = $deal_id;
        }
        if (!empty($state_id)) {
            $where[] = 'state_id = s:state_id';
            $filter['state_id'] = $state_id;
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
