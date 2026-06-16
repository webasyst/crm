<?php

class crmDealMoveMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $stage_id = ifset($_json, 'stage_id', null);
        $force = ifset($_json, 'force', false);
        $with_count = waRequest::get('with_count', false, waRequest::TYPE_INT);

        $deal_ids_raw = $this->get('id', false);
        if (empty($deal_ids_raw)) {
            $deal_ids_raw = ifset($_json, 'id', null);
        }

        if (!isset($stage_id)) {
            throw new waAPIException('empty_id', sprintf_wp('Missing required parameter: “%s”.', 'stage_id'), 400);
        }
        if ($stage_id < 1) {
            throw new waAPIException('invalid_request', _w('Stage not found.'), 400);
        }
        if (empty($deal_ids_raw)) {
            throw new waAPIException('invalid_request', _w('Deal identifier is required.'), 400);
        }

        if (!is_array($deal_ids_raw)) {
            $deal_ids_raw = [$deal_ids_raw];
        }
        $deal_ids = array_map('intval', $deal_ids_raw);
        $deal_ids = array_filter($deal_ids, function ($id) {
            return $id > 0;
        });
        $deal_ids = array_values(array_unique($deal_ids));

        if (empty($deal_ids)) {
            throw new waAPIException('invalid_request', _w('Invalid deal identifiers submitted.'), 400);
        }

        $is_bulk_move = count($deal_ids) > 1;

        $allowed_deal_ids = $this->getCrmRights()->dropUnallowedDeals($deal_ids, [
            'level' => crmRightConfig::RIGHT_DEAL_EDIT,
        ]);
        if (!is_array($allowed_deal_ids)) {
            $allowed_deal_ids = [];
        }
        $allowed_deal_ids = array_map('intval', $allowed_deal_ids);
        $allowed_deal_ids = array_values(array_unique(array_filter($allowed_deal_ids)));

        if (!$allowed_deal_ids) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $deal_model = $this->getDealModel();
        $deals_by_id = $deal_model->getById($allowed_deal_ids);

        if (count($deal_ids) === 1) {
            $only_id = (int) $deal_ids[0];
            if (empty($deals_by_id) || !isset($deals_by_id[$only_id])) {
                throw new waAPIException('not_found', _w('Deal not found'), 404);
            }
        }

        $stage_model = $this->getFunnelStageModel();
        $all_stages = $stage_model->getAll('id');
        $after_stage = ifset($all_stages, $stage_id, []);
        if (!$after_stage) {
            throw new waAPIException('invalid_request', _w('Stage not found.'), 400);
        }
        $funnel_id = ifset($after_stage, 'funnel_id', 0);
        if (!$this->getCrmRights()->funnel($funnel_id)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $deals_to_update = [];
        foreach ($deals_by_id as $d) {
            $before = ifset($all_stages, $d['stage_id'], []);
            if (!$before) {
                continue;
            }
            if ((int) $d['stage_id'] === (int) $after_stage['id'] && (int) $d['funnel_id'] === (int) $after_stage['funnel_id']) {
                continue;
            }
            $deals_to_update[] = $d + ['_before_stage' => $before];
        }

        $this->http_status_code = 204;
        $this->response = null;

        if (!$deals_to_update) {
            if ($with_count) {
                $this->http_status_code = 200;
                $this->response = [
                    'changed_counts' => $this->buildChangedCounts(array_values($deals_by_id), $after_stage),
                ];
            }
            return;
        }

        $shop = new crmShop();
        if (!$is_bulk_move && count($deals_to_update) === 1) {
            $deal = $deals_to_update[0];
            $before_stage = $deal['_before_stage'];
            if ($dialog = $shop->workflowPrepare($deal, $after_stage, $before_stage, $force, '2.0')) {
                if (ifset($dialog, 'action_id') || !$force) {
                    $this->http_status_code = 409;
                    $this->response = $dialog;
                    $this->response['dialog_html'] = ifset($dialog, 'html', '');
                    unset($this->response['html']);
                    return;
                }
            }
        }

        $log_model = $this->getLogModel();
        $funnel_model = $this->getFunnelModel();
        $all_funnels = $funnel_model->getAll('id');

        $ids_to_update = array_map('intval', array_column($deals_to_update, 'id'));
        $update_data = [
            'funnel_id'       => (int) $after_stage['funnel_id'],
            'stage_id'        => (int) $after_stage['id'],
            'update_datetime' => date('Y-m-d H:i:s'),
        ];

        $deal_model->updateByField(['id' => $ids_to_update], $update_data);

        $stage_close_pairs = [];
        $stage_open_pairs = [];
        foreach ($deals_to_update as $deal) {
            $stage_close_pairs[] = [
                'deal_id'  => (int) $deal['id'],
                'stage_id' => (int) $deal['stage_id'],
            ];
            $stage_open_pairs[] = [
                'deal_id'       => (int) $deal['id'],
                'stage_id'      => (int) $after_stage['id'],
            ];
        }
        $deal_stages_model = new crmDealStagesModel();
        $deal_stages_model->closeBulk($stage_close_pairs);
        $deal_stages_model->openBulk($stage_open_pairs);

        $log_rows = [];
        foreach ($deals_to_update as $deal) {
            $before_stage = $deal['_before_stage'];
            if ((int) $deal['funnel_id'] === (int) $after_stage['funnel_id']) {
                $action_id = 'deal_step';
                $before = ifset($before_stage['name']);
                $after = $after_stage['name'];
                $params = [
                    'stage_id_before' => $before_stage['id'],
                    'stage_id_after'  => $after_stage['id'],
                ];
            } else {
                $action_id = 'deal_move';
                $before = ifempty($all_funnels[$deal['funnel_id']]['name'], $deal['funnel_id']).'/'
                    .ifempty($all_stages[$deal['stage_id']]['name'], $deal['stage_id']);
                $after = ifempty($all_funnels[$after_stage['funnel_id']]['name'], $after_stage['funnel_id']).'/'
                    .$after_stage['name'];
                $params = [];
            }
            $log_rows[] = [
                'action'      => $action_id,
                'contact_id'  => $deal['id'] * -1,
                'object_id'   => $deal['id'],
                'before'      => $before,
                'after'       => $after,
                'params'      => $params,
            ];
        }

        $deal_log_map = [];
        $log_model->logBatch($log_rows, $deal_log_map);

        foreach ($deals_to_update as $deal) {
            $deal_before = $deal;
            unset($deal_before['_before_stage']);
            $data = $update_data + [
                'crm_log_id' => (int) ifset($deal_log_map, (int) $deal['id'], 0),
            ];
            $event_data = ['deal' => $data + $deal_before];
            $event_data['crm_log_id'] = ifempty($data, 'crm_log_id', 0);
            $event_data['deal']['before_stage_id'] = (int) $deal_before['stage_id'];
            $event_data['deal']['before_funnel_id'] = (int) $deal_before['funnel_id'];
            /**
             * @event deal_move
             */
            wa('crm')->event('deal_move', $event_data);
        }

        if (sizeof($deals_to_update) === 1) {
            $deal = reset($deals_to_update);
            crmHelper::logAction('deal_step', ['deal_id' => (int) $deal['id']]);
        } else {
            crmHelper::logAction('deals_step', sizeof($deals_to_update));
        }

        if ($is_bulk_move || $with_count) {
            $this->http_status_code = 200;
        }
        if ($is_bulk_move) {
            $this->response = ['moved' => array_map('intval', array_column($deals_to_update, 'id'))];
        }
        if ($with_count) {
            if (!is_array($this->response)) {
                $this->response = [];
            }
            $this->response['changed_counts'] = $this->buildChangedCounts(array_values($deals_by_id), $after_stage);
        }
    }

    /**
     * @param array[] $deals Deals as loaded before the move (each must have funnel_id, stage_id)
     * @param array $after_stage Target stage row
     * @return array
     */
    protected function buildChangedCounts(array $deals, array $after_stage)
    {
        $currency_id = wa()->getSetting('currency');
        $pairs = [];
        foreach ($deals as $d) {
            $k = (int) $d['funnel_id'].':'.(int) $d['stage_id'];
            $pairs[$k] = ['funnel_id' => (int) $d['funnel_id'], 'stage_id' => (int) $d['stage_id']];
        }
        $k = (int) $after_stage['funnel_id'].':'.(int) $after_stage['id'];
        $pairs[$k] = ['funnel_id' => (int) $after_stage['funnel_id'], 'stage_id' => (int) $after_stage['id']];

        uasort($pairs, function ($a, $b) {
            if ($a['funnel_id'] !== $b['funnel_id']) {
                return $a['funnel_id'] - $b['funnel_id'];
            }
            return $a['stage_id'] - $b['stage_id'];
        });

        $deal_model = $this->getDealModel();
        $result = [];
        foreach ($pairs as $pair) {
            list($funnel_count, $funnel_amount) = $deal_model->countOpen(['funnel_id' => $pair['funnel_id']], true);
            list($stage_count, $stage_amount) = $deal_model->countOpen(
                ['funnel_id' => $pair['funnel_id'], 'stage_id' => $pair['stage_id']],
                true
            );
            $result[] = [
                'funnel_id'     => $pair['funnel_id'],
                'stage_id'      => $pair['stage_id'],
                'funnel_count'  => $funnel_count,
                'funnel_amount' => $funnel_amount,
                'stage_count'   => $stage_count,
                'stage_amount'  => $stage_amount,
                'currency_id'   => $currency_id,
            ];
        }
        return $result;
    }
}
