<?php

class crmSettingsCompaniesRenderParamsAction extends crmViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $template_id = waRequest::request('template_id', 0, waRequest::TYPE_INT);
        $company_id = waRequest::request('company_id', 0, waRequest::TYPE_INT);
        $company_params = (new crmCompanyParamsModel)->getParams($company_id, $template_id);
        $arr_basic_param = (new crmTemplatesParamsModel)->getParamsByTemplates($template_id);

        $this->view->assign([
            'basic_params'   => $arr_basic_param,
            'company_params' => $company_params,
            'photo_path'     => wa()->getDataUrl('company_images/', true, 'crm', false),
            'company_id'     => $company_id,
            'template_id'    => $template_id,
        ]);
    }
}
