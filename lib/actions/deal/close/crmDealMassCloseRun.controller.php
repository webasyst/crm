<?php

class crmDealMassCloseRunController extends crmJsonController
{
    public function execute()
    {
        $deal_ids = waRequest::request('deal_ids', array(), waRequest::TYPE_ARRAY_INT);
        $action = waRequest::post('action', null, waRequest::TYPE_STRING_TRIM);
        $lost_id = waRequest::post('lost_id', null, waRequest::TYPE_INT);
        $lost_text = waRequest::post('lost_text', null, waRequest::TYPE_STRING_TRIM);

        $deal_ids = $this->dropUnallowed($deal_ids);

        if (!$this->validate($deal_ids, $action, $lost_id, $lost_text)) {
            return;
        }

        // Run
        $this->close($deal_ids, $action, $lost_id, $lost_text);
    }

    /**
     * Close given deals.
     * @param array $deal_ids
     * @return void
     * @throws waException
     */
    protected function close($deal_ids, $action, $lost_id, $lost_text)
    {
        foreach ($deal_ids as $deal_id) {
            crmDeal::close($deal_id, $action, $lost_id, $lost_text);
        }
    }

    protected function validate($deal_ids, $action, $lost_id, $lost_text)
    {
        $this->errors = array();

        if (!$deal_ids) {
            throw new waException('Deals not found');
        }
        if ($action == crmDealModel::STATUS_LOST) {
            $lost_reason_require = wa()->getSetting('lost_reason_require');
            if ($lost_reason_require && !$lost_id && !$lost_text) {
                $this->errors[] = array('name' => 'lost_id', 'value' => _w('This field required'));
                return false;
            }
        }
        return true;
    }

    /**
     * @param int[] $ids
     * @return int[]
     * @throws waDbException
     * @throws waException
     */
    protected function dropUnallowed($ids)
    {
        return $this->getCrmRights()->dropUnallowedDeals($ids, [
            'level' => crmRightConfig::RIGHT_DEAL_ALL
        ]);
    }
}
