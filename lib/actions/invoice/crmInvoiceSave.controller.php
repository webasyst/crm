<?php
/**
 * Saves data that comes from invoice editor.
 */
class crmInvoiceSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        $im = new crmInvoiceModel();
        $iim = new crmInvoiceItemsModel();

        // First of all determine invoice id to edit
        $invoice_data = waRequest::post('invoice', array(), waRequest::TYPE_ARRAY_TRIM);
        $invoice_id = ifset($invoice_data['id']);
        unset($invoice_data);

        // Invoice exists? Does user have access to it?
        $invoice = null;
        $items = array();
        if ($invoice_id) {
            $invoice = $im->getById($invoice_id);
            if (!$invoice || !$this->getCrmRights()->contact($invoice['contact_id'])) {
                $this->accessDenied();
            }
            if ($invoice['state_id'] != 'DRAFT' && $invoice['state_id'] != 'PENDING') {
                $this->accessDenied();
            }

            $items = $iim->getByField('invoice_id', $invoice_id, 'id');
        }

        // Get validated data
        $items_data = $this->getItemsData($invoice_id, $items);
        $invoice_data = $this->getInvoiceData($items_data);

        // Do not allow to do anything with deal if user does not have access to it
        if ($invoice && $invoice['deal_id'] && !$this->getCrmRights()->deal($invoice['deal_id'])) {
            unset($invoice_data['deal_id']);
        }

        // Show validation errors if occurred
        if ($this->errors) {
            return;
        }

        // Round invoice amount for future payment
        $currency = waCurrency::getInfo($invoice_data['currency_id']);
        if (!empty($currency['precision'])) {
            $invoice_data['amount'] = round($invoice_data['amount'], $currency['precision']);
            $invoice_data['tax_amount'] = round($invoice_data['tax_amount'], $currency['precision']);
        }

        $invoice_data['tax_type'] = ifempty($invoice_data['tax_type'], 'NONE');

        // Insert/update invoice
        $invoice_data['update_datetime'] = date('Y-m-d H:i:s');
        if ($invoice_id) {
            $im->updateById($invoice_id, $invoice_data);
            $this->getLogModel()->log(
                'invoice_updated',
                $invoice_data['contact_id'],
                $invoice_id
            );
        } else {
            $invoice_data['create_datetime'] = date('Y-m-d H:i:s');
            $invoice_data['creator_contact_id'] = wa()->getUser()->getId();
            $invoice_id = $im->insert($invoice_data);
            $this->getLogModel()->log(
                'invoice_add',
                $invoice_data['contact_id'],
                $invoice_id
            );
        }

        // Insert/update items
        $items_deleted = $iim->select('id')->where('invoice_id=?', $invoice_id)->fetchAll('id', true);
        foreach ($items_data as $i) {
            if ($i['id']) {
                unset($items_deleted[$i['id']]);
                $iim->updateById($i['id'], $i);
            } else {
                $i['invoice_id'] = $invoice_id;
                $iim->insert($i);
            }
        }
        if ($items_deleted) {
            $iim->deleteById(array_keys($items_deleted));
        }
        $im->updateById($invoice_id, array('summary' => $this->getSummary($items_data)));

        $params = array('invoice' => $invoice_data);
        wa('crm')->event('backend_invoice_save', $params);

        $this->response = array('id' => $invoice_id);
    }

    protected function getItemsData($invoice_id, $items)
    {
        $result = array();

        foreach (waRequest::post('items', array(), waRequest::TYPE_ARRAY_TRIM) as $i) {
            if (!empty($i['id']) && empty($items[$i['id']])) {
                throw new waException('Item not found', 404);
            }

            $i = array(
                'id'          => ifset($i['id']),
                'invoice_id'  => $invoice_id,
                'name'        => ifset($i['name']),
                'price'       => str_replace(',', '.', ifset($i['price'])),
                'quantity'    => str_replace(',', '.', ifset($i['quantity'])),
                'product_id'  => ifset($i['product_id']),
                /* Item taxes */
                'tax_type'    => ifempty($i['tax_type'], 'NONE'),
                'tax_percent' => ifset($i['tax_percent']),
                'tax_amount'  => 0,
                'amount'      => $i['price'] * $i['quantity'],
            );
            if ($i['tax_type'] != 'NONE') {
                if ($i['tax_type'] == 'APPEND') {
                    $i['tax_amount'] = $i['amount'] * $i['tax_percent'] / 100;
                } else {
                    $i['tax_amount'] = ($i['amount'] / (100 + $i['tax_percent'])) * $i['tax_percent'];
                }
            }
            if ($i['tax_type'] == 'APPEND') {
                $i['amount'] += $i['tax_amount'];
            }
            if (!is_numeric($i['price'])) {
                $this->errors['items[price]'] = _w('invalid number');
            }
            if (!is_numeric($i['quantity'])) {
                $this->errors['items[quantity]'] = _w('invalid number');
            }
            $result[] = $i;
        }
        return $result;
    }

    protected function getInvoiceData($items_data)
    {
        $result = waRequest::post('invoice', array(), waRequest::TYPE_ARRAY_TRIM);

        if (!is_numeric($result['company_id'])) {
            $this->errors['invoice[company_id]'] = _w('company not selected');
        }

        // Do not to change certain keys via this editor
        $im = new crmInvoiceModel();
        $empty_row = array_diff_key($im->getEmptyRow(), array(
            'create_datetime' => 1,
            'update_datetime' => 1,
            'creator_contact_id' => 1,
            'payment_datetime' => 1,
            'currency_rate' => 1,
            'state_id' => 1,
            'id' => 1,
        ));
        $result = array_intersect_key($result, $empty_row) + $empty_row;

        // Some keys are calculated rather than come from POST
        $result = array(
            'amount' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'discount_percent' => 0,
        ) + $result;

        // Convert numeric fields
        foreach (array('tax_percent') as $n) {
            $result[$n] = str_replace(',', '.', $result[$n]);
            if (!is_numeric($result['amount'])) {
                $result[$n] = 0;
            }
        }

        // Currency-related
        $currency_model = new crmCurrencyModel();
        $currency_info = $currency_model->get($result['currency_id']);
        if (!$currency_info) {
            throw new waException('Invalid currency ID', 406);
        }
        $result['currency_rate'] = $currency_info['rate'];
        if (isset($currency_info['precision'])) {
            $result['amount'] = round($result['amount'], $currency_info['precision']);
        }

        // Calculate invoice total and taxes
        foreach ($items_data as $i) {
            $result['amount'] += $i['amount'];
            $result['tax_amount'] += $i['tax_amount'];
        }

        $result['comment'] = (string)ifset($result['comment']);

        $result['due_days'] = (int)$result['due_days'];

        $ts = strtotime($result['due_date']);
        if ($ts) {
            $result['due_date'] = date('Y-m-d', $ts);
        } elseif($result['invoice_date'] && $result['due_days']) {
            $result['due_date'] = date('Y-m-d', strtotime($result['invoice_date']) + $result['due_days'] * 60 * 60 * 24);
        } else {
            $result['due_date'] = '';
        }

        return $result;
    }

    protected function getSummary($items)
    {
        $summary = array();
        foreach ($items as $i) {
            $summary[] = $i['name'];
            if (mb_strlen(join(', ', $summary)) > 255) {
                unset($summary[count($summary) - 1]);
                break;
            }
        }
        return(join(', ', $summary));
    }
}
