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
            throw new waException(_w('Invoice not found'));
        }
        $invoice['state_id'] = 'PENDING';
        $im->updateById($invoice['id'], array('state_id' => $invoice['state_id']));

        $contact_id = (empty($invoice['deal_id']) ? ifempty($invoice, 'contact_id', null) : -1 * $invoice['deal_id']);
        $this->getLogModel()->log(
            'invoice_restored',
            $contact_id,
            $invoice['id']
        );
        $invoice['contact'] = new waContact($invoice['contact_id']);
        $view = wa()->getView();
        $view->assign(array(
            'invoice' => $invoice,
        ));
        $actions_path = wa('crm')->whichUI('crm') === '1.3' ? 'actions-legacy' : 'actions';
        $this->response = array(
            'html' => $view->fetch(wa()->getAppPath('templates/'.$actions_path.'/invoice/InvoiceSidebar.item.inc.html', 'crm')),
        );
    }
}
