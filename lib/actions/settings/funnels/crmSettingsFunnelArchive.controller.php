<?php

class crmSettingsFunnelArchiveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $funnel_id = $this->getRequest()->post('id', null, waRequest::TYPE_INT);
        if (!$funnel_id) {
            throw new waException(404);
        }

        $fm = new crmFunnelModel();

        $funnel = $fm->getById($funnel_id);
        if (!$funnel) {
            throw new waException(404);
        }
        $fm->updateById($funnel_id, ['is_archived' => 1]);
    }
}
