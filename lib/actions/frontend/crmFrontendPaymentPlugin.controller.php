<?php

class crmFrontendPaymentPluginController extends waController
{
    public function execute()
    {
        $plugin_id = waRequest::param('plugin_id');
        if (!$plugin_id) {
            throw new waException('Plugin not found');
        }
        $order_id = waRequest::post('order_id', null, waRequest::TYPE_INT);
        if (!$order_id) {
            throw new waPaymentException('Order not found');
        }
        $invoices = (array)wa()->getStorage()->get('crm_frontend_invoices');
        if (!isset($invoices[$order_id])) {
            throw new waException(_w('Invoice not found'), 404);
        }
        $im = new crmInvoiceModel();
        $iim = new crmInvoiceItemsModel();
        $invoice = $im->getById($order_id);
        if (!$invoice || $invoice['state_id'] != 'PENDING') {
            throw new waPaymentException('Invoice not found');
        }
        $items = $iim->getByField('invoice_id', $order_id, true);
        foreach ($items as &$i) {
            $i['total'] = $i['price'] * $i['quantity'];
        }
        unset($i);
        $params = array_merge(waRequest::post(), array(
                'app_id'      => 'crm',
                'order_id'    => $order_id,
                'merchant_id' => $invoice['company_id'],
                'amount'      => $invoice['amount'],
                'currency_id' => $invoice['currency_id'],
                'id_str'      => $invoice['number'] ? $invoice['number'] : $order_id,
                'datetime'    => $invoice['create_datetime'],
                'contact_id'  => $invoice['contact_id'],
                'items'       => $items,
            ));
        $plugin = waPayment::factory($plugin_id, $invoice['company_id'], crmPayment::getInstance());

        $action = waRequest::param('action_id');
        $method = $action.'Action';
        if (!$action || !method_exists($plugin, $method)) {
            throw new waException('Action not found', 404);
        }
        echo $plugin->$method($params);
    }
}
