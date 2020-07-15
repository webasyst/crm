<?php

class crmSettingsFunnelDeleteController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $funnel_id = $this->getRequest()->post('id', null, waRequest::TYPE_INT);

        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $dm = new crmDealModel();

        $funnel = $fm->getById($funnel_id);
        $deals = $dm->getByField(array(
            'funnel_id' => $funnel_id,
            'status_id' => crmDealModel::STATUS_OPEN
        ));
        if (!$funnel_id || !$funnel || $deals) {
            throw new waRightsException();
        }
        $fm->deleteById($funnel_id);
        $fsm->deleteByField('funnel_id', $funnel_id);
    }
}
