<?php

class crmDealDeleteController extends crmJsonController
{
    public function execute()
    {
        $deal_ids = [];
        $deal_id = waRequest::post('id');
        if (is_scalar($deal_id)) {
            $deal_ids = [$deal_id];
        } elseif (is_array($deal_id)) {
            $deal_ids = $deal_id;
        } else {
            throw new waException('Deal not found');
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

        $current_user_id = $this->getUser()->getId();

        $deals = $this->getDealModel()->getById($ids);
        $deals = $this->getCrmRights()->dropUnallowedDeals($deals, [
            'level' => crmRightConfig::RIGHT_DEAL_ALL
        ]);

        foreach ($deals as $idx => $deal) {
            $is_not_allowed = $current_user_id != $deal['user_contact_id'] || $deal['closed_datetime'];
            if ($is_not_allowed) {
                unset($deals[$idx]);
            }
        }

        return waUtils::getFieldValues($deals, 'id');
    }
}
