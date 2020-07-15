<?php

class crmSettingsCompaniesSortSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $ids = preg_split('/\s*,\s*/', $this->getRequest()->post('ids', null, waRequest::TYPE_STRING_TRIM));

        $cm = new crmCompanyModel();
        $companies = $cm->getAll();

        if (count($ids) != count($companies)) {
            throw new waException('Invalid data');
        }

        for ($sort=0; $sort<count($ids); $sort++) {
            $cm->updateById($ids[$sort], array('sort' => $sort));
        }
    }
}
