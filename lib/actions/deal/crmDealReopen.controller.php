<?php

/**
 * !!! TODO: not used?..
 */
class crmDealReopenController extends crmJsonController
{
    public function execute()
    {
        $deal_id = waRequest::post('id', null, waRequest::TYPE_INT);

        $this->validate($deal_id);

        $dm = new crmDealModel();

        $now = date('Y-m-d H:i:s');
        $dm->updateById($deal_id, array(
            'status_id'       => 'OPEN',
            'update_datetime' => $now,
            'closed_datetime' => null,
        ));
        $action_id = 'deal_reopen';
        $this->logAction($action_id, array('deal_id' => $deal_id));
        $lm = new crmLogModel();
        $lm->log($action_id, $deal_id * -1);
    }

    protected function validate($deal_id)
    {
        $dm = new crmDealModel();
        $deal = $dm->getById($deal_id);

        if (!$deal_id || !$deal) {
            throw new waException('Deal not found');
        }
        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }
        if ($deal['status_id'] != 'WON' && $deal['status_id'] != 'LOST') {
            throw new waRightsException();
        }
    }
}
