<?php

class crmFunnelStatsMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $funnel_id = $this->get('id', true);
        $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnel_id, false);
        if (empty($stages)) {
            throw new waAPIException('not_found', _w('Funnel not found.'), 404);
        }
        $currency_id = wa()->getSetting('currency');

        $stages = array_values(array_map(function($it) use ($funnel_id) {
            list($deal_count, $deal_total_amount) = $this->getDealModel()->countOpen(['funnel_id' => $funnel_id, 'stage_id' => $it['id']], true);
            return [
                'id' => intval($it['id']),
                'deal_count' => $deal_count,
                'deal_total_amount' => $deal_total_amount,
            ];
        }, $stages));

        list($deal_count, $deal_total_amount) = $this->getDealModel()->countOpen(['funnel_id' => $funnel_id], true);

        $this->response = [
            'stages' => $stages,
            'deal_count' => $deal_count,
            'deal_total_amount' => $deal_total_amount,
            'currency_id' => $currency_id,
        ];
    }
}
