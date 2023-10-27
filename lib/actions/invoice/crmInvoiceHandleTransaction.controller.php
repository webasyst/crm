<?php
/**
 * Perform invoice state change after one of action buttons is activated.
 */
class crmInvoiceHandleTransactionController extends crmJsonController
{
    private $invoice;
    private $data;

    public function execute()
    {
        $this->validateInvoice();
        $action = waRequest::post('action', null, waRequest::TYPE_STRING_TRIM);
        $this->data = waRequest::request('data');

        if (
            !in_array($action, explode('|', 'accept|refuse|refund|paid|activate|delete|archive|cancel|draft'))
            || !method_exists('crmInvoice', $action)
        ) {
            throw new waException('Incorrect action');
        }

        if ($errors = crmInvoice::$action($this->invoice, $this->data)) {
            $this->errors[] = $errors;
        }

        $this->invoice['state_id'] = $this->getInvoiceModel()
            ->select('state_id')
            ->where('id = ?', $this->invoice['id'])
            ->fetchField('state_id');

        $this->invoice['contact'] = new waContact($this->invoice['contact_id']);
        $view = wa()->getView();
        $view->assign(array(
            'invoice' => $this->invoice,
        ));
        $actions_path = wa('crm')->whichUI('crm') === '1.3' ? 'actions-legacy' : 'actions';
        $this->response = array(
            'html' => $view->fetch(wa()->getAppPath('templates/'.$actions_path.'/invoice/InvoiceSidebar.item.inc.html', 'crm')),
        );
    }

    private function validateInvoice()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }
        $invoice_id = waRequest::post('invoice_id', null, waRequest::TYPE_INT);
        $im = new crmInvoiceModel();
        $this->invoice = $im->getById($invoice_id);
        if (!$invoice_id || !$this->invoice) {
            throw new waException('Invoicenot found');
        }
        if (!$this->getCrmRights()->contact($this->invoice['contact_id'])) {
            $this->accessDenied();
        }
    }
}
