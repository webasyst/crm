<?php

class crmAtolonlinePluginSettingsActions extends waActions
{
    // Main settings page
    protected function defaultAction()
    {
        $company_id = waRequest::request('company_id', null, waRequest::TYPE_INT);

        $cm = new crmCompanyModel();
        $companies_array = $cm->select('*')->order('sort')->fetchAll();

        $companies = array();
        foreach ($companies_array as $_company) {
            $companies[$_company["id"]] = $_company;
        }

        $company = reset($companies);
        if (!empty($company_id) && !empty($companies[$company_id])) {
            $company = $companies[$company_id];
        }

        $settings = wa('crm')->getPlugin('atolonline')->getSettings();

        $this->display(array(
            'companies'      => $companies,
            'company'        => $company,
            'settings'       => $settings,
            'sno'            => crmAtolonlinePlugin::getSno(),
            'payment_object' => crmAtolonlinePlugin::getPaymentObject(),
            'payment_method' => crmAtolonlinePlugin::getPaymentMethod(),
        ));
    }
}
