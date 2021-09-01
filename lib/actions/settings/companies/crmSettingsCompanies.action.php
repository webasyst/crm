<?php

class crmSettingsCompaniesAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $company_id = waRequest::param('id');

        $tm = new crmTemplatesModel();
        $cm = new crmCompanyModel();
        $companies = $cm->getAll('id');

        if ($company_id && isset($companies[$company_id])) {
            $company = $companies[$company_id];
        } elseif ($company_id == 'new') {
            $company = array_fill_keys(array_keys($cm->describe()), null);
        } else {
            $company = reset($companies);
            $company_id = isset($company['id']) ? $company['id'] : null;
        }

        if (!$company) {
            $company = $cm->getEmptyRow();
        }

        if (!empty($company['tax_options'])) {
            $company['tax_options'] = json_decode($company['tax_options'], true);
        }

        //Checking template_id for company, if null, set first template from DB
        if (!$company['template_id']) {
            $template_id = $tm->getByField('id', true);
            $company['template_id'] = $template_id['id'];
        }

        // If there is a logo for this company, show it
        $logo_url = null;
        if ($company['logo']) {
            $logo_url = wa()->getDataUrl('logos/'.$company['id'].'.'.$company['logo'], true, 'crm');
        }

        $im = new crmInvoiceModel();
        $invoice_count = $im->select('COUNT(*) cnt')->where('company_id = '.(int)$company_id)->fetchField('cnt');

        $cpm = new crmCompanyParamsModel();
        $company_params = $cpm->getParams($company['id'], $company['template_id']);

        $pm = new crmPaymentModel();
        $payment_count = $pm->select('COUNT(*) cnt')->where('company_id = '.(int)$company_id)->fetchField('cnt');

        $templates = $tm->getAll();

        $tpm = new crmTemplatesParamsModel();
        $arr_basic_param = $tpm->getParamsByTemplates((int)$company['template_id']);

        $domains = wa()->getRouting()->getByApp($this->getAppId());
        $storefront_list = array();
        foreach ($domains as $domain => $storefronts) {
            $storefront_list[$domain] = $domain . '/' . current($storefronts)['url'];
        }
        $has_storefronts = !empty($storefront_list);

        $this->view->assign(array(
            'companies'       => $companies,
            'company'         => $company,
            'invoice_count'   => $invoice_count,
            'payment_count'   => $payment_count,
            //            'logo_file'   => $logo_file,
            'logo_url'        => $logo_url,
            'logo_locale'     => wa()->getLocale() == 'ru_RU' ? 'ru_RU' : 'en_US',
            'templates'       => $templates,
            'company_params'  => $company_params,
            'basic_params'    => $arr_basic_param,
            'photo_path'      => wa()->getDataUrl('company_images/', true, 'crm'),
            'company_id'      => $company['id'],
            'template_id'     => $company['template_id'],
            'has_storefronts' => $has_storefronts,
            'storefront_list' => $storefront_list,
        ));
    }

}
