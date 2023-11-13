<?php

class crmDealCloseMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $status_id = ifset($_json, 'status_id', null);
        $lost_id = ifset($_json, 'lost_id', null);
        $lost_text = ifset($_json, 'lost_text', '');
        $force = ifset($_json, 'force', false);
        $deal_id = (int) $this->get('id', true);

        if (empty($status_id)) {
            throw new waAPIException('empty_id', 'Required parameter is missing: status_id', 400);
        } elseif (!in_array($status_id, ['WON', 'LOST', 'won', 'lost'])) {
            throw new waAPIException('unknown_value', 'Unknown value status_id', 400);
        } elseif ($deal_id < 1) {
            throw new waAPIException('not_found', 'Deal not found', 404);
        } elseif (isset($lost_id) && !$this->getDealLostModel()->getById((int) $lost_id)) {
            throw new waAPIException('not_found', 'lost_id not found', 404);
        }

        $deal = $this->getDealModel()->getById($deal_id);
        if (!$deal) {
            throw new waAPIException('not_found', 'Deal not found', 404);
        } elseif ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $shop = new crmShop();
        $status_id = strtoupper($status_id);
        $before_stage = $this->getFunnelStageModel()->getById($deal['stage_id']);
        if ($dialog = $shop->workflowPrepare($deal, $status_id, $before_stage, $force, '2.0')) {
            if (ifset($dialog, 'action_id') || !$force) {
                $this->http_status_code = 409;
                $this->response = $dialog;
                $this->response['dialog_html'] = ifset($dialog, 'html', '');
                unset($this->response['html']);
                return;
            }
        }

        $this->http_status_code = 204;
        $this->response = null;
        crmDeal::close($deal_id, $status_id, $lost_id, $lost_text);
    }
}
