<?php

class crmDealDeleteController extends crmJsonController
{
    public function execute()
    {
        $deal_id = waRequest::post('id');
        if (is_scalar($deal_id)) {
            $deal_ids = [$deal_id];
        } elseif (is_array($deal_id)) {
            $deal_ids = $deal_id;
        } else {
            throw new waException(_w('Deal not found'));
        }

        $deal_ids = $this->dropUnallowed($deal_ids);

        $this->getDealModel()->delete($deal_ids, [
            'reset' => ['message', 'conversation']
        ]);
    }
    protected function dropUnallowed($ids)
    {
        $rights = $this->getCrmRights();
        if ($rights->isAdmin()) {
            return $ids;
        }
        
        $deals = $this->getDealModel()->getById($ids);
        $deals = $this->getCrmRights()->dropUnallowedDeals($deals, [
            'level' => crmRightConfig::RIGHT_DEAL_ALL
        ]);

        return waUtils::getFieldValues($deals, 'id');
    }
}
