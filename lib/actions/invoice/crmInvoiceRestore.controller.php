<?php

class crmInvoiceRestoreController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }
        $im = new crmInvoiceModel();

        $invoice = $im->getById(waRequest::post('id', null, waRequest::TYPE_INT));
        if (!$invoice || $invoice['state_id'] != 'ARCHIVED') {
            throw new waException('Invoice not found');
        }
        $invoice['state_id'] = 'PENDING';
        $im->updateById($invoice['id'], array('state_id' => $invoice['state_id']));

        $invoice['contact'] = new waContact($invoice['contact_id']);
        $view = wa()->getView();
        $view->assign(array(
            'invoice'    => $invoice,
        ));
        $this->response = array(
            'html' => $view->fetch(wa()->getAppPath('templates/actions/invoice/InvoiceSidebar.item.inc.html', 'crm')),
        );
    }
}
