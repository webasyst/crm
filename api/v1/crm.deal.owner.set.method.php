<?php

class crmDealOwnerSetMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        if (!is_array($_json)) {
            $_json = [];
        }

        $user_contact_id = (int) ifset($_json, 'user_contact_id', 0);
        $deal_ids_raw = ifset($_json, 'deal_id', null);
        $label_raw = trim((string) ifset($_json, 'label', ''));
        $label = ($label_raw !== '') ? $label_raw : null;
        $keep_current_user = !empty(ifset($_json, 'keep_current_user', false));

        if ($user_contact_id < 1) {
            throw new waAPIException('invalid_request', sprintf_wp('Missing or invalid “%s” parameter value.', 'user_contact_id'), 400);
        }

        if ($deal_ids_raw === null || $deal_ids_raw === '') {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'deal_id'), 400);
        }
        if (!is_array($deal_ids_raw)) {
            $deal_ids_raw = [$deal_ids_raw];
        }
        $deal_ids = array_map('intval', $deal_ids_raw);
        $deal_ids = array_values(array_unique(array_filter($deal_ids, function ($id) {
            return $id > 0;
        })));

        if (!$deal_ids) {
            throw new waAPIException('invalid_request', _w('Invalid deal identifiers submitted.'), 400);
        }

        $is_single = count($deal_ids) === 1;

        $new_user = new crmContact($user_contact_id);
        if (!$new_user->exists()) {
            throw new waAPIException('invalid_request', sprintf_wp('Invalid “%s” value.', 'user_contact_id'), 400);
        }
        if (!$this->getCrmRights()->contact($new_user)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $new_user_rights = new crmRights(['contact' => $new_user]);

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

        $unallowed_deals = array_values(array_diff($deal_ids, $allowed_deal_ids));

        $deals = $this->getDealModel()->getById($allowed_deal_ids);
        if ($is_single) {
            $only_id = (int) $deal_ids[0];
            if (!in_array($only_id, $allowed_deal_ids, true)) {
                throw new waAPIException('forbidden', _w('Access denied'), 403);
            }
            if (empty($deals) || !isset($deals[$only_id])) {
                throw new waAPIException('not_found', _w('Deal not found'), 404);
            }
        }

        $accessable_deals = [];
        $unaccessable_deals = [];
        foreach ($allowed_deal_ids as $id) {
            if (!isset($deals[$id])) {
                continue;
            }
            $d = $deals[$id];
            if ($new_user_rights->funnel($d['funnel_id'])) {
                $accessable_deals[$id] = $d;
            } else {
                $unaccessable_deals[] = $id;
            }
        }
        $unaccessable_deals = array_values(array_unique($unaccessable_deals));

        if ($is_single && !$accessable_deals) {
            throw new waAPIException('not_available', _w('The specified user does not have access to the deal funnel.'), 400);
        }

        $deal_model = $this->getDealModel();
        $participants_model = $this->getDealParticipantsModel();
        $log_model = $this->getLogModel();
        $conversation_model = $this->getConversationModel();

        $accessable_ids = array_keys($accessable_deals);
        $parts_by_deal = $this->loadParticipantsByDealId($participants_model, $accessable_ids);

        $label_only_ids = [];
        $transfer_rows = [];
        $skipped_deals = [];

        foreach ($accessable_deals as $deal_id => $deal) {
            $deal_id = (int) $deal_id;
            $participants = ifset($parts_by_deal, $deal_id, []);
            $participants = $this->appendSyntheticParticipants($deal_id, $deal, $participants);

            $deal_full = $deal + ['participants' => $participants];

            $deal_access_level = $this->getCrmRights()->deal($deal_full);
            if ($deal_access_level <= crmRightConfig::RIGHT_DEAL_VIEW) {
                $skipped_deals[] = $deal_id;
                continue;
            }

            $funnel_rights = $this->getCrmRights()->funnel($deal_full['funnel_id']);
            if (
                $funnel_rights < crmRightConfig::RIGHT_FUNNEL_ALL
                && (int) $deal_full['user_contact_id'] !== (int) wa()->getUser()->getId()
                && ($deal_full['user_contact_id'] || $funnel_rights < crmRightConfig::RIGHT_FUNNEL_OWN)
            ) {
                $skipped_deals[] = $deal_id;
                continue;
            }

            $old_user = (int) $deal_full['user_contact_id'];
            if ($old_user === $user_contact_id) {
                $label_only_ids[] = $deal_id;
            } else {
                $transfer_rows[] = ['deal_id' => $deal_id, 'old_user' => $old_user];
            }
        }

        $label_only_ids = array_values(array_unique($label_only_ids));
        $skipped_deals = array_values(array_unique(array_filter($skipped_deals)));

        $now = date('Y-m-d H:i:s');
        $new_user_name = $new_user->getName();

        if ($label_only_ids) {
            $this->bulkReplaceUserParticipants($participants_model, $label_only_ids, $user_contact_id, $label, $now);
        }

        if ($transfer_rows) {
            $transfer_deal_ids = array_values(array_unique(array_map(function ($r) {
                return (int) $r['deal_id'];
            }, $transfer_rows)));

            $deal_model->updateByField(['id' => $transfer_deal_ids], ['user_contact_id' => $user_contact_id]);

            $delete_conds = [];
            foreach ($transfer_rows as $row) {
                $old = (int) $row['old_user'];
                if ($old < 1) {
                    continue;
                }
                $delete_conds[] = '(deal_id='.(int) $row['deal_id'].' AND contact_id='.$old
                    ." AND role_id='".crmDealParticipantsModel::ROLE_USER."')";
            }
            if ($delete_conds) {
                $participants_model->exec(
                    'DELETE FROM '.$participants_model->getTableName().' WHERE '.implode(' OR ', $delete_conds)
                );
            }

            $this->bulkReplaceUserParticipants($participants_model, $transfer_deal_ids, $user_contact_id, $label, $now);

            if ($keep_current_user) {
                $keep_rows = [];
                foreach ($transfer_rows as $row) {
                    $old = (int) $row['old_user'];
                    if ($old < 1 || $old === $user_contact_id) {
                        continue;
                    }
                    $keep_rows[] = [
                        'deal_id'    => (int) $row['deal_id'],
                        'contact_id' => $old,
                        'role_id'    => crmDealParticipantsModel::ROLE_USER,
                        'label'      => null,
                    ];
                }
                if ($keep_rows) {
                    $participants_model->multipleInsert($keep_rows);
                }
            }

            $old_ids_for_names = [];
            foreach ($transfer_rows as $row) {
                $ou = (int) $row['old_user'];
                if ($ou > 0) {
                    $old_ids_for_names[$ou] = true;
                }
            }
            $old_ids_for_names = array_keys($old_ids_for_names);
            $before_names = $old_ids_for_names
                ? (new waContactModel())->getName($old_ids_for_names)
                : [];

            $log_rows = [];
            foreach ($transfer_rows as $row) {
                $deal_id = (int) $row['deal_id'];
                $old_user = (int) $row['old_user'];
                $before_user_name = '';
                if ($old_user > 0) {
                    $before_user_name = isset($before_names[$old_user]) && $before_names[$old_user] !== ''
                        ? $before_names[$old_user]
                        : "deleted contact_id={$old_user}";
                }
                $log_rows[] = [
                    'action'     => 'deal_transfer',
                    'contact_id' => $deal_id * -1,
                    'object_id'  => $deal_id,
                    'before'     => $before_user_name,
                    'after'      => $new_user_name,
                    'params'     => [
                        'user_id_before' => $old_user > 0 ? $old_user : 0,
                        'user_id_after'  => $user_contact_id,
                    ],
                ];
            }
            if ($log_rows && (!$log_model->logBatch($log_rows))) {
                throw new waAPIException('server_error', _w('Failed to save the deal log.'), 500);
            }

            $conversation_model->updateByField(
                ['deal_id' => $transfer_deal_ids, 'is_closed' => 0],
                ['user_contact_id' => $user_contact_id]
            );
        }

        $transfer_updated_ids = $transfer_rows
            ? array_values(array_unique(array_map('intval', array_column($transfer_rows, 'deal_id'))))
            : [];
        $updated_ids = array_values(array_unique(array_merge($label_only_ids, $transfer_updated_ids)));

        if ($updated_ids) {
            if (count($updated_ids) === 1) {
                crmHelper::logAction('deal_transfer', ['deal_id' => (int) reset($updated_ids)]);
            } else {
                crmHelper::logAction('deals_transfer', count($updated_ids));
            }
        }

        $this->http_status_code = 204;
        $this->response = null;

        $response = [];
        if ($unallowed_deals) {
            $response['unallowed_deals'] = $unallowed_deals;
        }
        if ($unaccessable_deals) {
            $response['unaccessable_deals'] = $unaccessable_deals;
        }
        if ($skipped_deals) {
            $response['skipped_deals'] = $skipped_deals;
        }
        if ($response) {
            $this->http_status_code = 200;
            $response['updated'] = $updated_ids;
            $this->response = $response;
        }
    }

    /**
     * @param int[] $deal_ids
     * @return array<int, array>
     */
    private function loadParticipantsByDealId(crmDealParticipantsModel $participants_model, array $deal_ids)
    {
        if (!$deal_ids) {
            return [];
        }
        $rows = $participants_model->getByField('deal_id', $deal_ids, true);
        if (!is_array($rows)) {
            return [];
        }
        $by_deal = [];
        foreach ($rows as $p) {
            $did = (int) $p['deal_id'];
            if (!isset($by_deal[$did])) {
                $by_deal[$did] = [];
            }
            $by_deal[$did][] = $p;
        }
        return $by_deal;
    }

    /**
     * Mirrors crmDealModel::getDeal() participant normalization without DB writes.
     *
     * @param int $deal_id
     * @param array $deal Row from crm_deal
     * @param array $participants
     * @return array
     */
    private function appendSyntheticParticipants($deal_id, $deal, array $participants)
    {
        $create_dt = ifset($deal, 'create_datetime', date('Y-m-d H:i:s'));

        if (!empty($deal['contact_id']) && !in_array((int) $deal['contact_id'], array_map('intval', array_column($participants, 'contact_id')), true)) {
            $participants[] = [
                'deal_id'         => $deal_id,
                'contact_id'      => (int) $deal['contact_id'],
                'role_id'         => crmDealParticipantsModel::ROLE_CLIENT,
                'label'           => '',
                'create_datetime' => $create_dt,
            ];
        }
        if (!empty($deal['user_contact_id']) && !in_array((int) $deal['user_contact_id'], array_map('intval', array_column($participants, 'contact_id')), true)) {
            $participants[] = [
                'deal_id'         => $deal_id,
                'contact_id'      => (int) $deal['user_contact_id'],
                'role_id'         => crmDealParticipantsModel::ROLE_USER,
                'label'           => '',
                'create_datetime' => $create_dt,
            ];
        }
        return $participants;
    }

    /**
     * @param int[] $deal_ids
     */
    private function bulkReplaceUserParticipants(
        crmDealParticipantsModel $model,
        array $deal_ids,
        $user_contact_id,
        $label,
        $now
    ) {
        if (!$deal_ids) {
            return;
        }
        $label_sql = $label === null ? 'NULL' : "'".$model->escape($label)."'";
        $role = $model->escape(crmDealParticipantsModel::ROLE_USER);
        $now_sql = "'".$model->escape($now)."'";
        $values = [];
        foreach ($deal_ids as $did) {
            $values[] = '('.(int) $did.', '.(int) $user_contact_id.", '".$role."', ".$label_sql.', '.$now_sql.')';
        }
        $sql = 'REPLACE INTO '.$model->getTableName()
            .' (deal_id, contact_id, role_id, label, create_datetime) VALUES '.implode(',', $values);
        $model->exec($sql);
    }
}
