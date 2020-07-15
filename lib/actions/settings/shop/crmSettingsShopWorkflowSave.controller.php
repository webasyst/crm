<?php

class crmSettingsShopWorkflowSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $funnel_id = waRequest::post('funnel_id', null, waRequest::TYPE_INT);
        if (!$funnel_id) {
            throw new waException('Funnel not found');
        }

        $asm = new waAppSettingsModel();
        /*
        foreach (waRequest::post('crm_stages', array(), waRequest::TYPE_ARRAY_TRIM) as $stage_id => $action_id) {
            $name = 'stage:'.$stage_id.'_'.$funnel_id;
            if ($action_id) {
                $asm->set('crm', $name, $action_id);
            } else {
                $asm->deleteByField(array('app_id' => 'crm', 'name' => $name));
            }
        }
        */
        foreach (waRequest::post('shop_actions', array(), waRequest::TYPE_ARRAY_TRIM) as $action_id => $stage_id) {
            $name = 'shop:'.$action_id.'_'.$funnel_id;
            if ($stage_id) {
                $asm->set('crm', $name, $stage_id);
            } else {
                $asm->deleteByField(array('app_id' => 'crm', 'name' => $name));
            }
        }
    }
}
