<?php

class crmSettingsPaymentAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $company_id = waRequest::request('company', null, waRequest::TYPE_INT);

        $cm = new crmCompanyModel();
        $companies = $cm->getAll('id');
        if ($company_id && isset($companies[$company_id])) {
            $company = $companies[$company_id];
        } else {
            $company = reset($companies);
//            $company_id = isset($company['id']) ? $company['id'] : null;
        }

        $plugins = array();
        $instances = array();

        if ($company) {
            $plugins = crmPayment::getList();

            $pm = new crmPaymentModel();
            if (!empty($company['id'])) {
                $instances = $pm->select('*')->where('company_id = '.(int)$company['id'])->order('sort')->fetchAll('id');
            } else {
                $instances = $pm->select('*')->order('sort')->fetchAll('id');
            }
        }

        $this->view->assign(array(
            'instances' => $instances,
            'plugins'   => $plugins,
            'installer' => $this->getUser()->getRights('installer', 'backend'),
            'companies' => $companies,
            'company'   => $company,
        ));
    }
}
