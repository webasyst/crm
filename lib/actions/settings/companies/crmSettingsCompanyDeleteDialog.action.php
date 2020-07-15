<?php

class crmSettingsCompanyDeleteDialogAction extends waViewAction
{
    public function execute()
    {
        $company_id = $this->getId();

        $cm = new crmCompanyModel();
        $im = new crmInvoiceModel();

        $companies = $cm->getAll('id');

        if (!empty($companies[$company_id])) {
            $company = $companies[$company_id];
        } else {
            $company = $cm->getById($company_id);
        }

        if (!$company) {
            throw new waException('Company not found');
        }
        $invoices = $im->getByField('company_id', $company_id, true);

        $pm = new crmPaymentModel();
        $payments = $pm->select('*')->where('company_id = '.(int)$company_id)->fetchAll('id');

        $this->view->assign(array(
            'company'   => $company,
            'companies' => $companies,
            'invoices'  => $invoices,
            'payments'  => $payments,
        ));
    }

    protected function getId()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $company_id = waRequest::request('id', null, waRequest::TYPE_INT);
        if (!$company_id) {
            throw new waException('Empty company ID');
        }
        return $company_id;
    }
}
