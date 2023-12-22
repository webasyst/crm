<?php
/**
 * Closes a deal. Accepts submit from DealCloseDialog from DealId page.
 */
class crmDealCloseController extends crmJsonController
{
    public function execute()
    {
        $deal_id = waRequest::post('id', null, waRequest::TYPE_INT);
        $action = waRequest::post('action', null, waRequest::TYPE_STRING_TRIM);
        $lost_id = waRequest::post('lost_id', null, waRequest::TYPE_INT);
        $lost_text = waRequest::post('lost_text', null, waRequest::TYPE_STRING_TRIM);

        if (!$deal_id) {
            throw new waException(_w('Deal not found'));
        }

        $deal = $this->getDealModel()->getById($deal_id);
        if (!$deal) {
            throw new waException(_w('Deal not found'));
        } elseif ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }
        if ($action == crmDealModel::STATUS_LOST) {
            $lost_reason_require = wa()->getSetting('lost_reason_require');
            if ($lost_reason_require && !$lost_id && !$lost_text) {
                $this->errors['lost_id'] = _w('This field required');
                return;
            }
        }

        $sm = new crmFunnelStageModel();
        $before_stage = $sm->getById($deal['stage_id']);
        $shop = new crmShop();
        if ($dialog_html = $shop->workflowPrepare($deal, $action, $before_stage, waRequest::post('force_execute'))) {
            // display dialog
            $this->response['dialog_html'] = $dialog_html;
            return;
        } else {
            // reload
            $this->response['dialog_html'] = null;
        }

        crmDeal::close($deal_id, $action, $lost_id, $lost_text);
    }
}
