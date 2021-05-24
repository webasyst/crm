<?php
/**
 * Dialog HTML to confirm closure of a deal. Opens from DealId page.
 */
class crmDealCloseDialogAction extends crmBackendViewAction
{
    public function execute()
    {
        $deal_id = waRequest::post('id', null, waRequest::TYPE_INT);
        if ($this->getCrmRights()->deal($deal_id) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }

        $dm = new crmDealModel();
        $dlm = new crmDealLostModel();

        $deal = $dm->getById($deal_id);
        $funnel_id = $deal['funnel_id'];

        $this->view->assign(array(
            'deal'                 => $dm->getById($deal_id),
            'reasons'              => $dlm->select('*')->where("funnel_id IN (0, {$funnel_id})")->order('sort')->fetchAll('id'),
            'lost_reason_require'  => wa()->getSetting('lost_reason_require'),
            'lost_reason_freeform' => wa()->getSetting('lost_reason_freeform'),
        ));
    }
}
