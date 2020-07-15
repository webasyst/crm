<?php
/**
 * Base class for view actions with invoice list in middle sidebar.
 */
abstract class crmInvoiceViewAction extends crmBackendViewAction
{
    protected $invoice_id = null;

    public function __construct($params = null)
    {
        parent::__construct($params);

        if (!waRequest::request('content_only', null, waRequest::TYPE_INT)) {
            $action = new crmInvoiceSidebarAction();

            $this->view->assign(array(
                'sidebar_html'     => $action->display(false),
                'invoice_template' => crmViewAction::getTemplate(),
            ));
            $this->invoice_id = $action->invoice_id;
        }
    }

    protected function getTemplate()
    {
        if (!waRequest::request('content_only', null, waRequest::TYPE_INT)) {
            return 'templates/actions/invoice/Invoice.html';
        } else {
            return parent::getTemplate();
        }
    }
}
