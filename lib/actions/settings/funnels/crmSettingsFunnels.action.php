<?php

class crmSettingsFunnelsAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $gfs = crmConfig::getFunnelBaseStages();

        $funnel_id = waRequest::param('id');

        $funnels = $fsm->withStages($fm->getAllFunnels());
        foreach ($funnels as &$funnel) {
            $funnel = $fm->fixFunnelColors($funnel);
        }
        unset($funnel);

        if ($funnel_id && isset($funnels[$funnel_id])) {
            $funnel = $funnels[$funnel_id];
        } elseif ($funnel_id == 'new') {
            $funnel = array_fill_keys(array_keys($fm->describe()), null);
            $funnel['stages'] = array();
        } else {
            $funnel = reset($funnels);
            $funnel_id = $funnel['id'];
        }
        if ($funnel_id != 'new') {
            $dm = new crmDealModel();
            $counts = $dm->countByStages((int)$funnel_id, crmDealModel::STATUS_OPEN);

            $funnel['deals_count'] = 0;
            foreach ($funnel['stages'] as &$s) {
                $s['deals_count'] = ifset($counts[$s['id']], 0);
                $funnel['deals_count'] += $s['deals_count'];
            }
            unset($s);
        }

        $this->view->assign(array(
            'funnels' => $funnels,
            'funnel'  => $funnel,
            'groups'  => crmHelper::getAvailableGroups('funnel.'.$funnel_id, true),
            'baseStages' => $gfs,
            'deal_fields' => crmDealFields::getAll()
        ));
    }
}
