<?php
/**
 * Change existing deal stage. Used on DealId page.
 */
class crmDealMoveController extends crmJsonController
{
    public function execute()
    {
        $deal_id = waRequest::post('deal_id', null, waRequest::TYPE_INT);
        $stage_id = waRequest::post('stage_id', null, waRequest::TYPE_INT);

        $dm = new crmDealModel();
        $sm = new crmFunnelStageModel();

        $deal = $dm->getById($deal_id);
        $before_stage = $sm->getById($deal['stage_id']);
        $after_stage = $sm->getById($stage_id);

        if (!$deal || !$after_stage || $deal['funnel_id'] != $after_stage['funnel_id']) {
            throw new waException('Deal or stage not found');
        }
        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }

        $shop = new crmShop();

        if ($dialog_html = $shop->workflowPrepare($deal, $after_stage, $before_stage, waRequest::post('force_execute'))) {
            // display dialog
            $this->response['dialog_html'] = $dialog_html;
            return;
        } else {
            // reload
            $this->response['dialog_html'] = null;
        }

        $dm->updateById($deal_id, array(
            'update_datetime' => date('Y-m-d H:i:s'),
            'stage_id' => $stage_id,
        ));
        $this->logAction('deal_step', array('deal_id' => $deal_id));
        $lm = new crmLogModel();
        $lm->log(
            'deal_step',
            $deal_id * -1,
            null,
            ifset($before_stage['name']),
            $after_stage['name']
        );
    }
}
