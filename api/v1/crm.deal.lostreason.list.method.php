<?php

class crmDealLostreasonListMethod extends crmApiAbstractMethod
{
    public function execute()
    {
        $funnel_id = $this->get('funnel_id');
        if (!is_numeric($funnel_id) || $funnel_id < 0) {
            throw new waAPIException('invalid_param', _w('Invalid funnel ID.'), 400);
        }
        $funnel_id = (int) $funnel_id;
        if (!$this->getCrmRights()->funnel($funnel_id)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $lost_list = $this->getDealLostModel()
            ->select('*')
            ->where("funnel_id IN (0, $funnel_id)")
            ->order('sort ASC')
            ->fetchAll();

        $this->response = [
            'required'     => (bool) wa()->getSetting('lost_reason_require'),
            'allow_custom' => (bool) wa()->getSetting('lost_reason_freeform'),
            'lostreasons'  => $this->filterData(
                $lost_list,
                [
                    'id',
                    'name'
                ], [
                    'id' => 'integer'
                ]
            )
        ];
    }
}
