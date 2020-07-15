<?php

class crmAtolonlinePluginReceiptController extends waJsonController
{
    public function execute()
    {
        $invoice_id = waRequest::request('invoice', null, waRequest::TYPE_INT);
        $operation = waRequest::request('operation', null, waRequest::TYPE_STRING_TRIM);
        $operation = $operation == 'refund' ? 'sell_refund' : 'sell';
        $receipt_id = waRequest::request('receipt', null, waRequest::TYPE_INT);

        // Check access rights
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        $im = new crmInvoiceModel();
        $invoice = $im->getById($invoice_id);
        if (!$invoice_id || !$invoice) {
            throw new waException('Invoice not found', 404);
        }

        $crm_rights = new crmRights();
        $contact = new waContact($invoice['contact_id']);
        if (!$crm_rights->contact($contact)) {
            throw new waRightsException();
        }

        $receipt = null;
        if ($receipt_id) {
            $rm = new crmAtolonlineReceiptModel();
            $receipt = $rm->getById($receipt_id);
            if (!$receipt) {
                throw new waException('Receipt not found', 404);
            }
        }

        $tax_type = $invoice['tax_type'];
        $available_taxes = crmAtolonlinePlugin::getAvailableTaxes();
        if (!in_array($invoice['tax_percent'], $available_taxes) && !$receipt) {
            $tax_percent = waRequest::post('tax_percent', null, waRequest::TYPE_STRING_TRIM);
            if ($tax_percent === null || (is_numeric($tax_percent) && !in_array((int)$tax_percent, $available_taxes))) {
                throw new waException('Tax not found', 404);
            }
            $invoice['tax_percent'] = (int)$tax_percent;
            if ($tax_percent == 'none') {
                $tax_type = 'NONE';
            }
        }

        // Fetch rest of data from DB
        $iim = new crmInvoiceItemsModel();
        $invoice['items'] = $iim->getByField('invoice_id', $invoice_id, true);

        $tm = new waTransactionModel();
        $transaction = $tm->select('*')
                          ->where("app_id='crm'")
                          ->where("order_id=?", (int)$invoice_id)
                          ->where("type IN (?)", array(array(waPayment::OPERATION_AUTH_ONLY, waPayment::OPERATION_AUTH_CAPTURE)))
                          ->order('id DESC')
                          ->fetchAssoc();

        $params = array(
            'invoice'     => $invoice,
            'transaction' => $transaction,
            'receipt'     => $receipt,
            'tax_type'    => $tax_type,
        );

        crmAtolonlinePluginReceipt::sell($params, $operation);
    }
}
