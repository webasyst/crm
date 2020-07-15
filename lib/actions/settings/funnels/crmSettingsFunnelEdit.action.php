<?php

/*
 * @deprecated
 */
class crmSettingsFunnelEditAction extends waViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $funnel = array(
            'id' => null,
            'name' => null,
        );
        $stages = array();

        $funnel_id = $this->getRequest()->post('id', null, waRequest::TYPE_INT);

        $fm = new crmFunnelModel();
        if ($funnel_id > 0) {
            $funnel_data = $fm->getById($funnel_id);
            if ($funnel_data) {
                $funnel = $funnel_data;
                $funnel = $fm->fixFunnelColors($funnel);
            }
        }

        if ($funnel_id) {

            $fsm = new crmFunnelStageModel();
            $dm = new crmDealModel();
            
            $funnel['deals_count'] = $dm->select('COUNT(*) cnt')->where('funnel_id=' . (int)$funnel['id'])->fetchField('cnt');
            $stages = $fsm->getStagesByFunnel($funnel_id);

            foreach ($stages as &$s) {
                $s['deals_count'] = $dm->select(
                    'COUNT(*) cnt')->where('funnel_id=' . (int)$funnel['id'] . ' AND stage_id=' . (int)$s['id']
                )->fetchField('cnt');
            }
            unset($s);

        }

        $this->view->assign(array(
            'funnel' => $funnel,
            'stages' => $stages,
        ));
    }
}
