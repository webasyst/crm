<?php

class crmSettingsShopWorkflowAction extends crmSettingsShopAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $this->checkShopExists();

        $funnels = $this->getFunnels();
        $funnel = $this->getFunnel($funnels);

        $asm = new waAppSettingsModel();
        $settings = $asm->select('name, value')->where("app_id = 'crm' AND name LIKE 'shop:%'")->fetchAll('name', true);

        $this->view->assign(array(
            'supported'    => crmShop::isIntegrationSupported(crmShop::INTEGRATION_SYNC_WORKFLOW_FUNNELS),
            'min_version'  => crmShop::getIntegrationMinVersion(crmShop::INTEGRATION_SYNC_WORKFLOW_FUNNELS),
            'funnel'       => $funnel,
            'funnels'      => $funnels,
            'shop_actions' => $this->getShopActions(),
            'settings'     => $settings,
        ));
    }

    protected function getFunnels()
    {
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        $funnels = $fm->getAllFunnels();
        foreach ($funnels as &$f) {
            $f['stages'] = $fsm->getStagesByFunnel($f);
            $f['stages']['won'] = array('name' => _w('Won'));
            $f['stages']['lost'] = array('name' => _w('Lost'));
        }
        unset($f);

        return $funnels;
    }

    protected function getFunnel($funnels) {
        $funnel_id = waRequest::request('funnel_id', 0, waRequest::TYPE_INT);

        if ($funnel_id && !empty($funnels[$funnel_id])) {
            $funnel = $funnels[$funnel_id];
        } else {
            $funnel = reset($funnels);
        }

        return $funnel;
    }

    protected function getShopActions($static_only = false)
    {
        wa('shop', true);
        $workflow = new shopWorkflow();
        $actions = $workflow->getAvailableActions();
        wa('crm', true);

        if (!$static_only) {
            $exclude_actions = array(
                'create',
                'edit',
                'editcode',
                //'editshippingdetails',
                //'comment',
                'callback',
                'settle',
                //'message'
            );
            foreach ($exclude_actions as $a) {
                if (array_key_exists($a, $actions)) {
                    unset($actions[$a]);
                }
            }
            return $actions;
        }

        $include_actions = array(
            'process',
            'pay',
            'ship',
            'refund',
            'delete',
            'complete'
        );
        $out = array();
        foreach ($include_actions as $a) {
            if (array_key_exists($a, $actions)) {
                $out[$a] = $actions[$a];
            }
        }
        return $out;
    }
}
