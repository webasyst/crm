<?php
class crmSettingsPaymentSortController extends waJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $ids = $this->getRequest()->post('ids', null, waRequest::TYPE_ARRAY_TRIM);
        $company_id = waRequest::post('company_id', null, waRequest::TYPE_INT);
        if (!$company_id) {
            throw new waException('Company not found');
        }
        $pm = new crmPaymentModel();
        $instances = $pm->select('*')->where('company_id = '.$company_id)->fetchAll('id');

        for ($sort=0; $sort<count($ids); $sort++) {
            if (empty($instances[$ids[$sort]])) {
                throw new waException('Invalid data');
            }
            $pm->updateById($ids[$sort], array('sort' => $sort));
        }
    }
}
