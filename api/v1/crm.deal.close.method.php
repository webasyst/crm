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

        $deal_ids_raw = $this->get('id', false);
        if (empty($deal_ids_raw)) {
            $deal_ids_raw = ifset($_json, 'id', null);
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

        $is_bulk_close = count($deal_ids) > 1;

        if (empty($status_id)) {
            throw new waAPIException('invalid_request', sprintf_wp('Missing required parameter: “%s”.', 'status_id'), 400);
        }
        $status_id = strtoupper($status_id);
        if (!in_array($status_id, [crmDealModel::STATUS_LOST, crmDealModel::STATUS_WON], true)) {
            throw new waAPIException('unknown_value', sprintf_wp('Unknown “%s” value.', 'status_id'), 400);
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

        $deals_by_id = $this->getDealModel()->getById($allowed_deal_ids);

        if (count($deal_ids) === 1) {
            $only_id = (int) $deal_ids[0];
            if (empty($deals_by_id) || !isset($deals_by_id[$only_id])) {
                throw new waAPIException('not_found', _w('Deal not found'), 404);
            }
        }

        if (!$is_bulk_close && count($allowed_deal_ids) === 1) {
            $deal = reset($deals_by_id);
            $shop = new crmShop();
            $before_stage = $this->getFunnelStageModel()->getById($deal['stage_id']);
            if ($dialog = $shop->workflowPrepare($deal, $status_id, $before_stage, $force, '2.0')) {
                if (ifset($dialog, 'action_id', null) || !$force) {
                    $this->http_status_code = 409;
                    $this->response = $dialog;
                    $this->response['dialog_html'] = ifset($dialog, 'html', '');
                    unset($this->response['html']);
                    return;
                }
            }
        }

        $resolved_lost_text = $lost_text;
        if ($status_id === crmDealModel::STATUS_LOST && !empty($lost_id) && empty($resolved_lost_text)) {
            $lost_row = $this->getDealLostModel()->getById((int) $lost_id);
            if ($lost_row) {
                $resolved_lost_text = $lost_row['name'];
            }
        }

        $now = date('Y-m-d H:i:s');
        $lost_id_update = $status_id === crmDealModel::STATUS_WON ? null : (!empty($lost_id) ? (int) $lost_id : null);
        $lost_text_update = $status_id === crmDealModel::STATUS_WON ? null : $resolved_lost_text;
        $update_data = [
            'status_id'         => $status_id,
            'closed_datetime'   => $now,
            'update_datetime'   => $now,
            'reminder_datetime' => null,
            'lost_id'           => $lost_id_update,
            'lost_text'         => $lost_text_update,
        ];
        $this->getDealModel()->updateByField(['id' => $allowed_deal_ids], $update_data);

        $stage_close_pairs = [];
        foreach ($allowed_deal_ids as $deal_id) {
            $d = ifset($deals_by_id, $deal_id, null);
            if ($d && ifset($d, 'status_id', null) === crmDealModel::STATUS_OPEN) {
                $stage_close_pairs[] = [
                    'deal_id'  => (int) $deal_id,
                    'stage_id' => (int) $d['stage_id'],
                ];
            }
        }
        if ($stage_close_pairs) {
            (new crmDealStagesModel())->closeBulk($stage_close_pairs);
        }

        $reminder_model = $this->getReminderModel();
        $neg_in = implode(',', array_map(function ($id) {
            return (int) (-(int) $id);
        }, $allowed_deal_ids));
        $reminder_model->exec(
            "UPDATE {$reminder_model->getTableName()} SET complete_datetime = s:now WHERE complete_datetime IS NULL AND create_datetime < s:now AND contact_id IN (".$neg_in.")",
            ['now' => $now]
        );

        $action_id = $status_id === crmDealModel::STATUS_WON ? 'deal_won' : 'deal_lost';
        $log_rows = [];
        foreach ($allowed_deal_ids as $deal_id) {
            $log_rows[] = [
                'action'     => $action_id,
                'contact_id' => -((int) $deal_id),
                'object_id'  => (int) $deal_id,
                'before'     => null,
                'after'      => null,
                'params'     => [],
            ];
        }

        $deal_log_map = [];
        $this->getLogModel()->logBatch($log_rows, $deal_log_map);

        $event_name = 'deal_'.strtolower($status_id);
        foreach ($allowed_deal_ids as $deal_id) {
            $deal_before = ifset($deals_by_id, $deal_id, null);
            if (!$deal_before || ifset($deal_before, 'status_id', null) !== crmDealModel::STATUS_OPEN) {
                continue;
            }
            $data = $update_data + [
                'crm_log_id' => (int) ifset($deal_log_map, (int) $deal['id'], 0),
            ];
            
            $event_data = ['deal' => $data + $deal_before];
            $event_data['crm_log_id'] = ifempty($data, 'crm_log_id', 0);
            /**
             * @event deal_won | deal_lost
             */
            wa('crm')->event($event_name, $event_data);
        }

        if (sizeof($allowed_deal_ids) === 1) {
            crmHelper::logAction($action_id, ['deal_id' => (int) reset($allowed_deal_ids)]);
        } else {
            crmHelper::logAction(str_replace('deal_', 'deals_', $action_id), sizeof($allowed_deal_ids));
        }

        if ($is_bulk_close) {
            $this->http_status_code = 200;
            $this->response = ['closed' => array_map('intval', $allowed_deal_ids)];
        } else {
            $this->http_status_code = 204;
            $this->response = null;
        }
    }
}
