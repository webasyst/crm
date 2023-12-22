<?php

class crmInvoiceTransactionMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json      = $this->readBodyAsJson();
        $invoice_id = (int) ifset($_json, 'id', 0);
        $action     = (string) ifset($_json, 'action', '');
        $actions    = [
            'accept',
            'refuse',
            'refund',
            'paid',
            'activate',
            'delete',
            'archive',
            'cancel',
            'draft'
        ];

        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } else if (empty($invoice_id)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        } else if (empty($action)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'action'), 400);
        } elseif ($invoice_id < 1) {
            throw new waAPIException('not_found', _w('Invoice not found'), 404);
        } elseif (!in_array($action, $actions)) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid parameter: “%s”.', 'action'), 400);
        }

        $cim = new crmInvoiceModel();
        $invoice = $cim->getById($invoice_id);
        if (!$invoice) {
            throw new waAPIException('not_found', _w('Invoice not found'), 404);
        } else if (!$this->getCrmRights()->contact($invoice['contact_id'])) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } else if (!method_exists('crmInvoice', $action)) {
            throw new waAPIException('invalid_param', _w('Unknown action.'), 400);
        }

        if ($errors = crmInvoice::$action($invoice)) {
            throw new waAPIException('error', implode("\n", $errors), 400);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}
