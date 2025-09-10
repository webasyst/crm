<?php
class crmInvoiceTemplatesRenderAction extends crmViewAction
{    
    public function execute()
    {
        $invoice_template_id = waRequest::request('template_id', null, waRequest::TYPE_INT);
        $style_version = waRequest::request('style_version', 2, waRequest::TYPE_INT);
        if (empty($style_version)) {
            $style_version = 2;
        }

        $template = waRequest::post('content');
        $origin_id = waRequest::post('origin_id');

        //Get from wa-apps/crm/templates/actions/settings/SettingsCompanies.html
        $company_id = waRequest::request('company_id', null, waRequest::TYPE_INT);
        $company = null;

        //Get from wa-apps/crm/templates/actions/frontend/FrontendInvoice.html
        $invoice_id = waRequest::request('invoice_id', null, waRequest::TYPE_INT);

        $invoice = null;

        //User rights check. Available to admin or for getting into crm / Invoice
        if ($invoice_id && !$invoice_template_id && !$template && !$company_id) {
            $im = new crmInvoiceModel();
            $invoice = $im->getInvoiceWithCompany($invoice_id);
            if (!empty($invoice)) {
                $contact = new waContact($invoice['contact_id']);
                if (!$this->getCrmRights()->contact($contact)) {
                    $this->accessDenied();
                }
                $company = $invoice['company'];
                if (!empty($company)) {
                    $template_record = (new crmTemplatesModel)->getById($company['template_id']);
                    if (!empty($template_record)) {
                        $template = $template_record['content'];
                        $style_version = $template_record['style_version'];
                        $origin_id = $template_record['origin_id'];
                    }
                }
            }
        } elseif(!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $set_height = waRequest::request('set_height', false, waRequest::TYPE_STRING_TRIM);

        $renderer = new crmTemplatesRender([
            'invoice_template_id' => $invoice_template_id,
            'template'            => $template,
            'origin_id'           => $origin_id,
            'company_id'          => $company_id,
            'company'             => $company,
            'invoice_id'          => $invoice_id,
            'invoice'             => $invoice,
            'style_version'       => $style_version < 2 ? '' : '_v' . $style_version,
        ]);

        $this->view->assign([
            'set_height' => $set_height,
            'style_version' => $style_version < 2 ? '' : '_v' . $style_version,
            'company' => $renderer->company,
            'html' => $renderer->getRenderedTemplate(),
        ]);
    }

    public function preExecute()
    {
        $this::checkSkipUpdateLastPage();
        parent::preExecute();
    }

    public static function checkSkipUpdateLastPage()
    {
        waRequest::setParam('skip_update_last_page', '1');
    }
}