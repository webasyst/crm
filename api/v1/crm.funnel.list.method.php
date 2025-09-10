<?php

class crmFunnelListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    protected $with_count;

    public function execute()
    {
        $this->with_count = waRequest::get('with_count', false, waRequest::TYPE_INT);

        $funnels = $this->getFunnelModel()->getAllFunnels(true);
        $funnels = $this->getFunnelStageModel()->withStages($funnels);
        $currency_id = $this->with_count ? wa()->getSetting('currency') : null;
        $unpinned_funnels = wa()->getUser()->getSettings('crm', 'unpinned_funnels');
        $unpinned_funnels = empty($unpinned_funnels) ? [] : explode(',', $unpinned_funnels);

        $this->response = array_values(array_map(function($el) use ($currency_id, $unpinned_funnels) {
            $fid = $el['id'];
            $el['stages'] = array_values(array_map(function($it) use ($currency_id, $fid) {
                if ($this->with_count) {
                    list($deal_count, $deal_total_amount) = $this->getDealModel()->countOpen(['funnel_id' => $fid, 'stage_id' => $it['id']], true);
                    $it['deal_count'] = $deal_count;
                    $it['deal_total_amount'] = $deal_total_amount;
                    $it['currency_id'] = $currency_id;
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
                list($deal_count, $deal_total_amount) = $this->getDealModel()->countOpen(['funnel_id' => $fid], true);
                $el['deal_count'] = $deal_count;
                $el['deal_total_amount'] = $deal_total_amount;
                $el['currency_id'] = $currency_id;
            }

            $el['id'] = intval($el['id']);
            $el['sort'] = intval($el['sort']);
            $el['icon'] = $el['icon'] ?: 'fas fa-briefcase';
            $el['is_archived'] = boolval($el['is_archived']);
            $el['is_pinned'] = !$el['is_archived'] && !in_array($el['id'], $unpinned_funnels);
            return $el;
        }, $funnels));
    }
}