<?php

class crmFunnelListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    protected $with_count;

    public function execute()
    {
        $funnels = $this->getFunnelModel()->getAllFunnels();
        $funnels = $this->getFunnelStageModel()->withStages($funnels);
        //$stored_funnel_id = wa()->getUser()->getSettings('crm', 'deal_funnel_id');
        
        $this->with_count = waRequest::get('with_count', false, waRequest::TYPE_INT);

        $this->response = array_values(array_map(function($el) {
            $fid = $el['id'];
            $el['stages'] = array_values(array_map(function($it) use ($fid) {
                if ($this->with_count) {
                    $it['deal_count'] = $this->getDealModel()->countOpen(['funnel_id' => $fid, 'stage_id' => $it['id']]);
                }
                $it['id'] = intval($it['id']);
                $it['number'] = intval($it['number']);
                $it['funnel_id'] = intval($it['funnel_id']);
                if ($it['limit_hours'] !== null) {
                    $it['limit_hours'] = intval($it['limit_hours']);
                }
                return $it;
            }, $el['stages']));
            if ($this->with_count) {
                $el['deal_count'] = $this->getDealModel()->countOpen(['funnel_id' => $el['id']]);
            }

            $el['id'] = intval($el['id']);
            $el['sort'] = intval($el['sort']);
            return $el;
        }, $funnels));
    }
}