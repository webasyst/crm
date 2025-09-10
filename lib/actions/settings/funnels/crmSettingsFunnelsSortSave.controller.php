<?php

class crmSettingsFunnelsSortSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $ids = preg_split('/\s*,\s*/', $this->getRequest()->post('ids', null, waRequest::TYPE_STRING_TRIM));

        $fm = new crmFunnelModel();
        $funnels = $fm->getAllFunnels(true);

        if (count($ids) != count($funnels)) {
            throw new waException('Invalid data');
        }
        for ($sort=0; $sort<count($ids); $sort++) {
            if (empty($funnels[$ids[$sort]])) {
                throw new waException('Invalid data');
            }
            $fm->updateById($ids[$sort], array('sort' => $sort));
        }
    }
}
