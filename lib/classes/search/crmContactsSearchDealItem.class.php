<?php

class crmContactsSearchDealItem
{
    const ITEM_ID_FUNNEL = 'crm.deal_participants.funnel';
    const ITEM_ID_STAGE  = 'crm.deal_participants.stage';
    const ITEM_ID_STATUS = 'crm.deal_participants.status';
    const ITEM_ID_LOST_REASON = 'crm.deal_participants.lost_reason';

    protected $options;

    public function __construct($options = array())
    {
        $this->options = $options;
    }

    public function getHtml()
    {
        return crmHelper::renderViewAction(
            new crmContactSearchDealItemsAction(array(
                'item_id' => $this->getItemId(),
                'funnel_id' => $this->getFunnelId(),
                'stage_id' => $this->getStageId(),
                'status' => $this->getStatus(),
                'reason_lost_id' => $this->getLostReasonId()
            ))
        );
    }

    protected function getFunnelId()
    {
        $conds = $this->getConds();
        $funnel_id = (int) ifset($conds['funnel']['val']);
        if ($funnel_id > 0) {
            return $funnel_id;
        }
        return (int) ifset($conds['crm']['deal_participants']['funnel']['val']);
    }

    protected function getStageId()
    {
        $conds = $this->getConds();
        $stage_id = (int) ifset($conds['stage']['val']);
        if ($stage_id > 0) {
            return $stage_id;
        }
        return (int) ifset($conds['crm']['deal_participants']['stage']['val']);
    }

    protected function getStatus()
    {
        $conds = $this->getConds();
        $status = (string) ifset($conds['status']['val']);
        if (!in_array($status, crmDealModel::getAllStatuses())) {
            $status = '';
        }
        if (!$status) {
            $status = ifset($conds['crm']['deal_participants']['status']['val']);
        }
        if (!in_array($status, crmDealModel::getAllStatuses())) {
            $status = '';
        }
        return $status;
    }

    protected function getLostReasonId()
    {
        $conds = $this->getConds();
        $reason_id = (string) ifset($conds['lost_reason']['val']);
        if (!$reason_id) {
            $reason_id = ifset($conds['crm']['deal_participants']['lost_reason']['val']);
        }
        return $reason_id;
    }

    protected function getItemId()
    {
        return $this->options['item_id'];
    }

    public function where($val_item = '')
    {
        $val = '';
        if (is_array($val_item)) {
            $val = ifset($val_item['val']);
        } elseif ($val_item) {
            $val = (string) $val_item;
        }
        $m = new waModel();
        $val = $m->escape($val);

        if (!$val) {
            return '';
        }

        $item_id = $this->getItemId();
        if ($item_id === self::ITEM_ID_FUNNEL) {
            return ":deal.funnel_id = '{$val}'";
        } elseif ($item_id === self::ITEM_ID_STAGE) {
            return ":deal.stage_id = '{$val}'";
        } elseif ($item_id === self::ITEM_ID_STATUS) {
            if (in_array($val, crmDealModel::getAllStatuses())) {
                return ":deal.status_id = '{$val}'";
            }
        } elseif ($item_id === self::ITEM_ID_LOST_REASON) {
            return ":deal.lost_id = '{$val}'";
        }
    }

    public function getTitle()
    {
        $title = '';
        $item_id = $this->getItemId();
        if ($item_id === self::ITEM_ID_FUNNEL) {
            $title = $this->getFunnelName();
        } elseif ($item_id === self::ITEM_ID_STAGE) {
            $title = $this->getStageName();
        } elseif ($item_id === self::ITEM_ID_STATUS) {
            $title = crmDealModel::getStatusName($this->getStatus());
        } elseif ($item_id === self::ITEM_ID_LOST_REASON) {
            $m = new crmDealLostModel();
            $lost_reason_id = $this->getLostReasonId();
            $reason = $m->getById($lost_reason_id);
            if (!$reason) {
                $title = $lost_reason_id;
            } else {
                $title = $reason['name'];
            }
        }
        return $title;
    }

    protected function getFunnelName()
    {
        $funnel_id = $this->getFunnelId();
        if ($funnel_id <= 0) {
            return '';
        }
        $fm = new crmFunnelModel();
        $item = $fm->getById($funnel_id);
        if (!$item) {
            return '';
        }
        return $item['name'];
    }

    protected function getStageName()
    {
        $stage_id = $this->getStageId();
        if ($stage_id <= 0) {
            return '';
        }
        $sm = new crmFunnelStageModel();
        $item = $sm->getById($stage_id);
        if (!$item) {
            return '';
        }
        return $item['name'];
    }

    protected function getConds()
    {
        return ifset($this->options['conds'], array());
    }
}
