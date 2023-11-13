<?php

class crmDealMoveMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $stage_id = ifset($_json, 'stage_id', null);
        $force = ifset($_json, 'force', false);
        $deal_id = (int) $this->get('id', true);

        if (!isset($stage_id)) {
            throw new waAPIException('empty_id', 'Required parameter is missing: stage_id', 400);
        } elseif ($stage_id < 1) {
            throw new waAPIException('not_found', 'Stage not found', 404);
        } elseif ($deal_id < 1) {
            throw new waAPIException('not_found', 'Deal not found', 404);
        }

        $deal_model = $this->getDealModel();
        $deal = $deal_model->getById($deal_id);
        if (!$deal) {
            throw new waAPIException('not_found', 'Deal not found', 404);
        } elseif ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $this->http_status_code = 204;
        $this->response = null;
        $stage_model = $this->getFunnelStageModel();
        $stages = $stage_model->getById([$deal['stage_id'], $stage_id]);
        $before_stage = ifset($stages, $deal['stage_id'], []);
        $after_stage = ifset($stages, $stage_id, []);
        if (!$after_stage) {
            throw new waAPIException('not_found', 'Stage not found', 404);
        } elseif ($before_stage['id'] == $after_stage['id']) {
            return;
        }

        $shop = new crmShop();
        if ($dialog = $shop->workflowPrepare($deal, $after_stage, $before_stage, $force, '2.0')) {
            if (ifset($dialog, 'action_id') || !$force) {
                $this->http_status_code = 409;
                $this->response = $dialog;
                $this->response['dialog_html'] = ifset($dialog, 'html', '');
                unset($this->response['html']);
                return;
            }
        }

        $deal_model->updateById(
            $deal_id,
            ['stage_id' => $stage_id, 'update_datetime' => date('Y-m-d H:i:s')]
            + ($deal['funnel_id'] != $after_stage['funnel_id'] ? ['funnel_id' => $after_stage['funnel_id']] : [])
        );
        $this->getLogModel()->log(
            'deal_step',
            $deal_id * -1,
            $deal_id,
            ifset($before_stage['name']),
            $after_stage['name'],
            null,
            ['stage_id_before' => $before_stage['id'], 'stage_id_after' => $after_stage['id']]
        );
    }
}
