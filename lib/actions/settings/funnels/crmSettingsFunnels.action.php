<?php

class crmSettingsFunnelsAction extends crmSettingsViewAction
{
    private $icons = [
        'fas fa-briefcase',
        'fas fa-business-time',
        'fas fa-suitcase',
        'fas fa-suitcase-rolling',
        'fas fa-funnel-dollar',
        'fas fa-truck',
        'fas fa-taxi',
        'fas fa-motorcycle',
        'fas fa-gas-pump',
        'fas fa-car-battery',
        'fas fa-bus',
        'fas fa-ship',
        'fas fa-helicopter',
        'fas fa-plane',
        'fas fa-rocket',
        'fas fa-wrench',
        'fas fa-hammer',
        'fas fa-paint-roller',
        'fas fa-screwdriver',
        'fas fa-tools',
        'fas fa-wallet',
        'fas fa-cash-register',
        'fas fa-cart-plus',
        'fas fa-barcode',
        'fas fa-piggy-bank',
        'fas fa-credit-card',
        'fas fa-book',
        'fas fa-landmark',
        'fas fa-headset',
        'fas fa-microphone',
        'fas fa-life-ring',
        'fas fa-skull-crossbones',
        'fas fa-sim-card',
        'fas fa-sd-card',
        'fas fa-microchip',
        'fas fa-microscope',
        'fas fa-camera-retro',
        'fas fa-utensils',
        'fas fa-wheelchair',
        'fas fa-wine-glass',
        'fas fa-wine-bottle',
        'fas fa-mug-hot',
        'fas fa-sun',
        'fas fa-graduation-cap',
        'fas fa-bath',
        'fas fa-bed',
        'fas fa-umbrella-beach',
        'fas fa-spa',
        'fas fa-dice',
        'fas fa-dice-five',
        'fas fa-key',
        'fas fa-campground',
        'fas fa-bomb',
        'fas fa-bug',
        'fas fa-guitar',
        'fas fa-futbol',
        'fas fa-bullseye',
        'fas fa-award',
        'fas fa-city',
        'fas fa-industry',
        'fas fa-box',
        'fas fa-dolly',
        'fas fa-warehouse',
        'fas fa-paw',
        'fas fa-stethoscope',
        'fas fa-syringe',
        'fas fa-tablets',
        'fas fa-flask',
        'fas fa-seedling',
        'fas fa-leaf',
        'fas fa-feather',
        'fas fa-tree',
        'fas fa-fish',
        'fas fa-umbrella',
        'fas fa-traffic-light',
        'fas fa-shoe-prints',
    ];

    private $colors = [
        '#cc5252','#cc8f52','#cccc52','#52cc52','#52cc8f','#52cccc','#528fcc','#5252cc','#8f52cc','#cc52cc','#cc528f'
    ];

    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $gfs = crmConfig::getFunnelBaseStages();

        $funnel_id = waRequest::param('id');

        $funnels = $fsm->withStages($fm->getAllFunnels(true));
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
            'deal_fields' => crmDealFields::getAll(),
            'icons' => $this->icons,
            'colors' => $this->colors,
        ));
    }
}
