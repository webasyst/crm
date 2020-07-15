<?php

class crmSettingsCompanyDeleteController extends waJsonController
{
    public function execute()
    {
        $id = $this->getId();

        $cm = new crmCompanyModel();
        $im = new crmInvoiceModel();

        $company = $cm->getById($id);
        $count = count($cm->getAll('id'));
        if (!$company || $count <= 1) {
            throw new waException('Company not found');
        }
        $invoices = $im->getByField('company_id', $id);

        $switch_to = waRequest::post('switch_to', null, waRequest::TYPE_INT);
        if ($invoices && !$switch_to) {
            throw new waException('New company not found');
        }
        $cm->deleteById($id);
        $im->updateByField('company_id', $id, array('company_id' => $switch_to));

        $pm = new crmPaymentModel();
        $payments = $pm->select('*')->where('company_id = '.(int)$id)->fetchAll('id');
        if ($payments) {
            $pm->deleteByField('company_id', $id);
            $psm = new crmPaymentSettingsModel();
            $psm->exec("DELETE FROM {$psm->getTableName()} WHERE id IN('".join("','", $pm->escape(array_keys($payments)))."')");
        }

        crmCompanyImageHandler::deleteCompanyImages($id);
        if ($company['logo']) {
            $delete_logo = new crmCompanyImageHandler(array(
                'company_id' => $id,
                'type'       => 'logo',
                'ext'        => $company['logo']
            ));
            $delete_logo->deleteImage();
        }
        $cpm = new crmCompanyParamsModel();
        $cpm->deleteByField('company_id', $id);
    }

    protected function getId()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        if (!$id) {
            throw new waException('Empty company ID');
        }
        return $id;
    }
}
