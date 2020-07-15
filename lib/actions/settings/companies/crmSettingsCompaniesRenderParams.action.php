<?php

class crmSettingsCompaniesRenderParamsAction extends crmViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $template_id = waRequest::request('template_id', 0, 'int');
        $company_id = waRequest::request('company_id', 0, 'int');

        $cm = new crmCompanyModel();
        $tpm = new crmTemplatesParamsModel();

        $company = $cm->getById($company_id);
        $cpm = new crmCompanyParamsModel();
        $company_params = $cpm->getParams($company_id, $template_id);

        //Do not receive invoice_option if the data does not match
        if ($company['template_id'] != $template_id) {
            $arr_company_params = null;
        }

        $arr_basic_param = $tpm->getParamsByTemplates($template_id);

        $this->view->assign(array(
            'basic_params'   => $arr_basic_param,
            'company_params' => $company_params,
            'photo_path'     => wa()->getDataUrl('company_images/', true, 'crm', false),
            'company_id'     => $company_id,
            'template_id'    => $template_id,
        ));
    }
}
