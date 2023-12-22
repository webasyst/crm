<?php

/**
 * !!! TODO: not used?..
 */
class crmDealReopenController extends crmJsonController
{
    public function execute()
    {
        $deal_id = waRequest::post('id', null, waRequest::TYPE_INT);

        if (!$deal_id) {
            throw new waException(_w('Deal not found'));
        }

        $dm = $this->getDealModel();
        $deal = $dm->getById($deal_id);
        if (!$deal) {
            throw new waException(_w('Deal not found'));
        } elseif ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        } elseif (!in_array($deal['status_id'], [ crmDealModel::STATUS_LOST, crmDealModel::STATUS_WON ])) {
            throw new waRightsException();
        }

        $lm = new crmLogModel();
        $action_id = 'deal_reopen';
        $now = date('Y-m-d H:i:s');
        $crm_log_id = $lm->log($action_id, $deal_id * -1, $deal_id);
        $dm->updateById($deal_id, [
            'status_id'       => 'OPEN',
            'update_datetime' => $now,
            'closed_datetime' => null,
            'crm_log_id'      => $crm_log_id
        ]);
        $this->logAction($action_id, array('deal_id' => $deal_id));
    }
}
