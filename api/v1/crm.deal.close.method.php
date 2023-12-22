<?php

class crmDealCloseMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $status_id = ifset($_json, 'status_id', null);
        $lost_id = ifset($_json, 'lost_id', null);
        $lost_text = ifset($_json, 'lost_text', null);
        $force = ifset($_json, 'force', false);
        $deal_id = (int) $this->get('id', true);

        if (empty($status_id)) {
            throw new waAPIException('invalid_request', sprintf_wp('Missing required parameter: “%s”.', 'status_id'), 400);
        }
        $status_id = strtoupper($status_id);
        if (!in_array($status_id, [ crmDealModel::STATUS_LOST, crmDealModel::STATUS_WON ])) {
            throw new waAPIException('unknown_value', sprintf_wp('Unknown “%s” value.', 'status_id'), 400);
        }
        if ($deal_id < 1) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        }
        if ($status_id === crmDealModel::STATUS_LOST) {
            if (!empty($lost_id) && !$this->getDealLostModel()->getById((int) $lost_id)) {
                throw new waAPIException('unknown_value', sprintf_wp('Unknown “%s” value.', 'lost_id'), 400);
            }
            if (!empty($lost_id)) {
                $lost_text = null;
            }
            if (!empty($lost_text) && !wa()->getSetting('lost_reason_freeform')) {
                throw new waAPIException('unknown_value', _w('The lost reason must be selected only from the available options.'), 400);
            }
            if (empty($lost_id) && empty($lost_text) && (bool) wa()->getSetting('lost_reason_require')) {
                throw new waAPIException('invalid_request', _w('Lost reason required.'), 400);
            }
        } else {
            $lost_id = null;
            $lost_text = null;
        }

        $deal = $this->getDealModel()->getById($deal_id);
        if (!$deal) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        }
        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
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
