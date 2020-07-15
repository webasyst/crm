<?php
class crmInvoiceTemplatesRenderAction extends crmViewAction
{
    public function execute()
    {
        $invoice_template_id = waRequest::request('template_id', null, 'int');

        $template = waRequest::post('content');

        //Get from wa-apps/crm/templates/actions/settings/SettingsCompanies.html
        $company_id = waRequest::request('company_id', null, 'int');

        //Get from wa-apps/crm/templates/actions/frontend/FrontendInvoice.html
        $invoice_id = waRequest::request('invoice_id', null, 'int');

        //User rights check. Available to admin or for getting into crm / Invoice
        if ($invoice_id && !$invoice_template_id && !$template && !$company_id) {
            $im = new crmInvoiceModel();
            $invoice = $im->getById($invoice_id);
            $contact = new waContact($invoice['contact_id']);
            if (!$this->getCrmRights()->contact($contact)) {
                $this->accessDenied();
            }
        } elseif(!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $set_height = waRequest::request('set_height', false, waRequest::TYPE_STRING_TRIM);

        $this->view->assign(array(
            'set_height' => $set_height,
            'html'       => new crmTemplatesRender( array(
                'invoice_template_id' => $invoice_template_id,
                'template'            => $template,
                'company_id'          => $company_id,
                'invoice_id'          => $invoice_id
            ))
        ));
    }
}