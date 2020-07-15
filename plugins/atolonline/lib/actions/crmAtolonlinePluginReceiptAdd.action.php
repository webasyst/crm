<?php

class crmAtolonlinePluginReceiptAddAction extends waViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        $invoice_id = waRequest::request('invoice_id', null, waRequest::TYPE_INT);

        if ( !empty($invoice_id) ) {
            $im = new crmInvoiceModel();
            $invoice = $im->getInvoice($invoice_id);

            if (!$invoice) {
                throw new waException('Invoice not found', 404);
            }
        } else {
            throw new waException('Invoice id is required', 404);
        }

        $rm = new crmAtolonlineReceiptModel();
        $receipt = $rm->select('*')->where("invoice_id = $invoice_id AND status<>'fail'")->fetchAssoc();

        $available_taxes = crmAtolonlinePlugin::getAvailableTaxes();

        $this->view->assign(array(
            'invoice'       => $invoice,
            'receipt'       => $receipt,
            'tax_available' => in_array($invoice['tax_percent'], $available_taxes),
        ));

        $this->setTemplate('plugins/atolonline/templates/ReceiptAdd.html');
    }
}
