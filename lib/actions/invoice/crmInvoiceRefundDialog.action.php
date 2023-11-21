<?php

/**
 * HTML for a invoice refund dialog.
 */
class crmInvoiceRefundDialogAction extends crmViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        // Invoice ID may come from routing, or from parent class
        $invoice_id = waRequest::request('invoice_id', null, waRequest::TYPE_INT);

        // Get invoice data
        $im = new crmInvoiceModel();
        $invoice = $im->getInvoice($invoice_id);
        if (!$invoice) {
            throw new waException(_w('Invoice not found'), 404);
        }

        // Parameters for events
        $params = array('invoice' => $invoice);
        $backend_invoice_refund = wa('crm')->event('backend_invoice_refund', $params);

        $this->view->assign(array(
            'invoice'                => $invoice,
            'backend_invoice_refund' => $backend_invoice_refund,
        ));
    }
}