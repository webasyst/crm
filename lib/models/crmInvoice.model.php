<?php

class crmInvoiceModel extends crmModel
{
    protected $table = 'crm_invoice';

    protected $link_contact_field = array('creator_contact_id', 'contact_id');

    public function getInvoice($id)
    {
        $invoice = $this->getById($id);
        if (!$invoice) {
            return null;
        }
        $invoice['comment_sanitized'] = crmHtmlSanitizer::work($invoice['comment']);
        $invoice['params'] = $this->getInvoiceParams($invoice['id']);
        $invoice['items'] = $this->getInvoiceItems($invoice['id']);
        return $invoice;
    }

    /**
     * Combining company data with invoice data
     * @param $invoice_id
     * @return array|null
     * @throws waException
     */
    public function getInvoiceWithCompany($invoice_id, $company_id = null)
    {
        $invoice = $this->getInvoice($invoice_id);

        if (empty($invoice)) {
            throw new waException(_w('Invoice not found'), 404);
        }

        if (!empty($company_id)) {
            $invoice['company_id'] = $company_id;
        }

        if (empty($invoice['company_id'])) {
            return $invoice;
        }

        $invoice['company'] = $company = (new crmCompanyModel)->getById($invoice['company_id']);

        if (empty($company)) {
            return $invoice;
        }

        if (!empty($invoice['company']['logo'])) {
            $invoice['company']['logo_url'] = wa()->getDataUrl(
                'logos/'.$invoice['company']['id'].'.'.$invoice['company']['logo'],
                true,
                'crm'
            );
        }

        $invoice['company']['invoice_options'] = crmTemplatesRender::getCompanyTemplateParams($company);

        return $invoice;
    }

    public function getEmptyInvoice()
    {
        $item = $this->getEmptyRow();
        $item['comment_sanitized'] = '';
        return $item;
    }

    /**
     * @param $id
     * @return array
     */
    public function getInvoiceParams($id)
    {
        return (array)$this->getInvoiceParamsModel()->getParams($id);
    }

    /**
     * @param $id
     * @return array
     */
    public function getInvoiceItems($id)
    {
        return (array)$this->getInvoiceItemsModel()->getItems($id);
    }

    public function getList($params, &$total_count = null)
    {
        // LIMIT
        if (isset($params['offset']) || isset($params['limit'])) {
            $offset = (int)ifset($params['offset'], 0);
            $limit = (int)ifset($params['limit'], 50);
            if (!$limit) {
                return array();
            }
        } else {
            $offset = $limit = null;
        }

        // ORDER BY
        $sort = 'i.id';
        $table_fields = $this->getMetadata();
        if (!empty($params['sort']) && !empty($table_fields[$params['sort']])) {
            $sort = 'i.'.$this->escapeField($params['sort']);
        }
        $order = ifset($params['order'], 'ASC');
        if (strtolower($order) !== 'asc') {
            $order = 'DESC';
        }
        if (isset($params['company_id']) && $params['company_id'] == 'all') {
            unset($params['company_id']);
        }

        // WHERE: access rights check
        $access_rights_join = '';
        $access_rights_conditions = '1=1';
        if (!empty($params['check_rights']) && !wa()->getUser()->isAdmin('crm')) {
            // Only keep contacts from available vaults
            $rights = new crmRights();
            $access_rights_conditions = "c.crm_vault_id IN (".join(',', $rights->getAvailableVaultIds()).")";
            $access_rights_join = 'JOIN wa_contact c ON c.id=i.contact_id';

            // Show only our invoices if access does not allow for more (wa_contact_rights.manage_invoices = 1)
            if (wa()->getUser()->getRights('crm', 'manage_invoices') == 1) {
                $access_rights_conditions .= " AND creator_contact_id = ".wa()->getUser()->getId();
            }
        }

        // WHERE: filter conditions
        $where = array_intersect_key($params, $table_fields);
        $filter_conditions = $this->getWhereByField($where, 'i');
        $filter_conditions = ifempty($filter_conditions, '1=1');

        // WHERE: id > something to check for new items only
        if (!empty($params['min_id'])) {
            $filter_conditions .= ' AND i.id > '.((int)$params['min_id']);
        }

        // Exclude ARCHIVED rows
        if (empty($params['state_id'])) {
            $filter_conditions .= " AND i.`state_id` <> 'ARCHIVED'";
        }

        if (!empty($params['payment_start_date'])) {
            $filter_conditions .= " AND i.payment_datetime >= '".$this->escape($params['payment_start_date'])." 00:00:00'";
        }
        if (!empty($params['payment_end_date'])) {
            $filter_conditions .= " AND i.payment_datetime <= '".$this->escape($params['payment_end_date'])." 23:59.59'";
        }

        // Count rows setting
        if (empty($params['count_results'])) {
            $select = "SELECT i.*";
        } else {
            if ($params['count_results'] == 'only') {
                $select = "SELECT count(*)";
            } else {
                $select = "SELECT SQL_CALC_FOUND_ROWS i.*";
            }
        }

        // Fetch rows
        $sql = "{$select}
                FROM {$this->getTableName()} AS i
                    {$access_rights_join}
                WHERE $filter_conditions
                    AND $access_rights_conditions
                ORDER BY $sort $order";
        if ($limit) {
            $sql .= " LIMIT {$offset}, {$limit}";
        }

        // Count rows setting
        $db_result = $this->query($sql);
        if (empty($params['count_results'])) {
            return $db_result->fetchAll('id');
        } else {
            if ($params['count_results'] == 'only') {
                $total_count = $db_result->fetchField();
                return $total_count;
            } else {
                $total_count = $this->query('SELECT FOUND_ROWS()')->fetchField();
                return $db_result->fetchAll('id');
            }
        }
    }

    public function unsetContactLinks($contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$contact_ids) {
            return;
        }
        $sql = "DELETE FROM {$this->getTableName()} WHERE contact_id IN('".join("','", $contact_ids)."') AND state_id IN('DRAFT','ARCHIVED')";
        $this->exec($sql);
    }

    public function getPaidChart($chart_params, $value = null)
    {
        $condition = '';
        if ($chart_params['company_id'] != 'all') {
            $condition .= ' AND i.company_id = '.(int)$chart_params['company_id'];
        }
        if ($chart_params['user_id'] != 'all') {
            $condition .= ' AND i.creator_contact_id = '.(int)$chart_params['user_id'];
        }

        if ($chart_params['group_by'] == 'months') {
            $select = "DATE_FORMAT(payment_datetime, '%Y-%m-01') d";
            $group_by = "YEAR(payment_datetime), MONTH(payment_datetime)";
            $step = '+1 month';
        } else {
            $select = "DATE(payment_datetime) d";
            $group_by = "DATE(payment_datetime)";
            $step = '+1 day';
        }
        $val = $value != 'sum' ? 'COUNT(*)' : "SUM(amount * currency_rate)";

        $sql = "SELECT $select, $val cnt FROM {$this->getTableName()} i
            WHERE i.state_id = 'PAID'
            AND i.payment_datetime >= '".$this->escape($chart_params['start_date'])." 00:00:00'
            AND i.payment_datetime <= '".$this->escape($chart_params['end_date'])." 23:59:59'
            $condition GROUP BY $group_by ORDER BY $group_by";

        $res = $this->query($sql)->fetchAll();

        if ($chart_params['group_by'] == 'months') {
            $chart_params['start_date'] = date('Y-m-01', strtotime($chart_params['start_date']));
            $chart_params['end_date'] = date('Y-m-01', strtotime($chart_params['end_date']));
        }

        $chart = array();
        $points = array();
        $d = $chart_params['start_date'];
        while ($d <= $chart_params['end_date']) {
            $val = 0;
            foreach ($res as $l) {
                if ($l['d'] == $d) {
                    $val = $l['cnt'];
                }
            }
            $points[] = array(
                'date'  => $d,
                'value' => floatval($val),
            );
            $d = date('Y-m-d', strtotime($step, strtotime($d)));
        }
        $chart[] = array(
            'data' => $points,
        );
        return $chart;
    }
}
