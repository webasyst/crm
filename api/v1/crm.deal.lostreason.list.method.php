<?php

class crmDealLostreasonListMethod extends crmApiAbstractMethod
{
    public function execute()
    {
        $funnel_id = (int) $this->get('funnel_id');

        if ($funnel_id < 0) {
            throw new waAPIException('not_found', _w('Funnel not found.'), 404);
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
