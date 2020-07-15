<?php

class crmContactSearchDealItemsAction extends crmBackendViewAction
{
    public function execute()
    {
        $item_id = $this->getItemId();
        if ($item_id === crmContactsSearchDealItem::ITEM_ID_FUNNEL) {
            $this->renderFunnelItem();
        } elseif ($item_id === crmContactsSearchDealItem::ITEM_ID_STAGE) {
            $this->renderStageItem();
        } elseif ($item_id === crmContactsSearchDealItem::ITEM_ID_STATUS) {
            $this->renderStatusItem();
        } elseif ($item_id === crmContactsSearchDealItem::ITEM_ID_LOST_REASON) {
            $this->renderLostReason();
        }
    }

    public function renderFunnelItem()
    {
        $this->assign(array(
            'funnels' => $this->getFunnels(),
            'funnel_id' => $this->getFunnelId()
        ));
    }

    public function renderStageItem()
    {
        $this->assign(array(
            'stages' => $this->getStages($this->getFunnelId()),
            'stage_id' => $this->getStageId()
        ));
    }

    public function renderStatusItem()
    {
        $this->assign(array(
            'statuses' => crmDealModel::getAllStatuses(true),
            'status' => $this->getStatus()
        ));
    }

    public function renderLostReason()
    {
        $this->assign(array(
            'lost_reasons' => $this->getLostReasons(),
            'lost_reason_id' => $this->getLostReasonId()
        ));
    }

    protected function assign($assign)
    {
        $this->view->assign(array_merge($assign, array(
            'item_id' => $this->getItemId()
        )));
    }

    protected function getItemId()
    {
        if (isset($this->params['item_id'])) {
            return $this->params['item_id'];
        }
        return $this->getRequest()->request('item_id');
    }

    protected function getFunnelId()
    {
        $funnel_id = (int) ifset($this->params['funnel_id']);
        if ($funnel_id > 0) {
            return $funnel_id;
        }
        return (int) $this->getRequest()->request('funnel_id');
    }

    protected function getStageId()
    {
        return (int) ifset($this->params['stage_id']);
    }

    protected function getStatus()
    {
        return (string) ifset($this->params['status']);
    }

    protected function getLostReasonId()
    {
        return (string) ifset($this->params['lost_reason_id']);
    }

    protected function getFunnels()
    {
        return $this->getFunnelModel()->getAll();
    }

    protected function getStages($funnel_id)
    {
        if ($funnel_id <= 0) {
            return array();
        }
        return $this->getFunnelStageModel()->getStagesByFunnel($funnel_id);
    }

    protected function getLostReasons()
    {
        return $this->getDealLostModel()->getAll();
    }

}
