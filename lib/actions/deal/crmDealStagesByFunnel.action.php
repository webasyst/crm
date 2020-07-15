<?php
/**
 * Returns HTML with <option>s for deal stage selector.
 * Options depend on deal's selected funnel.
 */
class crmDealStagesByFunnelAction extends crmBackendViewAction
{
    public function execute()
    {
        $funnel_id = waRequest::get('id', null, waRequest::TYPE_INT);

        if (!$funnel_id) {
            throw new waException('Empty funnel ID');
        }

        $fm  = new crmFunnelModel();
        $funnel = $fm->getById($funnel_id);
        if (!$funnel) {
            $this->notFound();
        }
        elseif (!$this->getCrmRights()->funnel($funnel)) {
            $this->accessDenied();
        }

        $fsm = new crmFunnelStageModel();
        $stages = $fsm->getStagesByFunnel($funnel);

        $deal_fields = array();
        $fields = crmDealFields::getAll('enabled');
        foreach ($fields as $field_id => $field) {
            $funnels_params = $field->getFunnelsParameters();
            $info['funnels_parameters'] = '';
            if (!empty($funnels_params) && !empty($funnels_params[$funnel['id']])) {
                $info['funnels_parameters'] = $funnels_params[$funnel['id']];
            }
            $deal_fields[$field_id] = $info;
        }

        $this->view->assign(array(
            'stages' => $stages,
            'fields' => $deal_fields
        ));
    }
}
