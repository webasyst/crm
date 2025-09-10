<?php

class crmHistoryMethod extends crmApiAbstractMethod
{
    const MAX_LIMIT = 500;
    const DEFAULT_LIMIT = 30;

    public function execute()
    {
        $contact_id = waRequest::get('contact_id', null, waRequest::TYPE_INT);
        $user_id = waRequest::get('user_id', null, waRequest::TYPE_INT);
        $deal_id = waRequest::get('deal_id', null, waRequest::TYPE_INT);
        $min_id = abs(waRequest::get('min_id', 0, waRequest::TYPE_INT));
        $max_id = abs(waRequest::get('max_id', 0, waRequest::TYPE_INT));
        $limit = waRequest::get('limit', self::DEFAULT_LIMIT, waRequest::TYPE_INT);
        $limit = ($limit > self::MAX_LIMIT || $limit <= 0 ? self::DEFAULT_LIMIT : $limit);
        $userpic_size = abs(waRequest::get('userpic_size', self::USERPIC_SIZE, waRequest::TYPE_INT));
        $filters = waRequest::get('filters', [], waRequest::TYPE_ARRAY_TRIM);

        if (!empty($contact_id)) {
            if ($contact_id < 1) {
                throw new waAPIException('not_found', _w('Contact not found'), 404);
            }
            $contact = new crmContact($contact_id);
            if (!$contact->exists()) {
                throw new waAPIException('not_found', _w('Contact not found'), 404);
            }
            $crm_vault_id = $contact['crm_vault_id'];
            if (!$this->getCrmRights()->contactVaultId($crm_vault_id)) {
                throw new waAPIException('forbidden', _w('Access denied'), 403);
            }
        }
        if (!empty($user_id)) {
            if ($user_id < 1) {
                throw new waAPIException('not_found', _w('User not found.'), 404);
            }
            $rights_model = new waContactRightsModel();
            if (!$rights_model->get($user_id, $this->getAppId(), 'backend')) {
                throw new waAPIException('not_found', _w('User not found.'), 404);
            }
        }
        $deal = null;
        if (!empty($deal_id)) {
            if ($deal_id < 1) {
                throw new waAPIException('not_found', _w('Deal not found'), 404);
            }
            $deal = $this->getDealModel()->getById($deal_id);
            if (!$deal) {
                throw new waAPIException('not_found', _w('Deal not found'), 404);
            } elseif (!$this->getCrmRights()->deal($deal_id)) {
                throw new waAPIException('forbidden', _w('Access denied'), 403);
            }
        }

        // THIS ARRAY USED BY LogTimeline Tooo!!!!
        $filter_actions = $this->_getFilterActions();
        $filters = $this->_getFilters($filter_actions, $filters);
        $filters += (empty($user_id) ? [] : ['actor_contact_id' => $user_id]);
        $lm = new crmLogModel();
        $id = $deal_id ? $deal_id * -1 : $contact_id;
        list($log, $min_id_log, $count) = $lm->getLog($id, $filters, $min_id, $max_id, $limit, [
            'add_messenger_sources' => true,
            'handle_message_body'   => true,
            'do_not_exclude_reminder_add' => true,
        ]);

        $ids = array_keys($log);
        if ($min_id === 0 && array_pop($ids) == $min_id_log) { // last block (first record is reached)
            if (!empty($contact)) {
                if (!empty($contact['create_datetime'])) {
                    $apps = wa()->getApps();
                    $content = [];
                    if (!empty($apps[$contact['create_app_id']])) {
                        $content[] = _w('app').': '.$apps[$contact['create_app_id']]['name'];
                    }
                    if ($contact['create_method'] && $contact['create_app_id'] != 'crm') {
                        $content[] = _w('method').': '.$contact['create_method'];
                    }
                    $log_record = [
                        'id' => 0,
                        'create_datetime' => $contact['create_datetime'],
                        'actor_contact_id' => $contact['create_contact_id'],
                        'action' => null,
                        'action_name' => _w('added contact'),
                        'content' => join(', ', $content),
                        'object_type' => crmLogModel::OBJECT_TYPE_CONTACT,
                        'object_id' => $contact['id'],
                        'actor' => $this->newContact($contact['create_contact_id']),
                    ];
                    if (!empty($apps[$contact['create_app_id']])) {
                        $log_record['create_app_id'] = $contact['create_app_id'];
                    }
                    $log[] = $log_record;
                }
            } elseif (!empty($deal)) {
                $log[] = array(
                    'id' => 0,
                    'create_datetime' => $deal['create_datetime'],
                    'actor_contact_id' => $deal['creator_contact_id'],
                    'action' => null,
                    'action_name' => _w('added deal'),
                    'object_type' => crmLogModel::OBJECT_TYPE_DEAL,
                    'object_id' => $deal['id'],
                    'actor' => $this->newContact($deal['creator_contact_id']),
                );
            }
        }

        // Get conversations data
        $conversations = [];
        $messages = array_column($log, 'message');
        if (!empty($messages)) {
            $conversation_ids = array_unique(array_column($messages, 'conversation_id'));
            $conversations = $this->getConversationModel()->getByField(['id' => $conversation_ids], 'id');
            $conversation_participants = $lm->query("
                SELECT DISTINCT cm.creator_contact_id AS id, cm.conversation_id, wc.name, wc.photo
                FROM crm_message cm
                JOIN wa_contact wc ON cm.creator_contact_id = wc.id
                WHERE cm.conversation_id IN (i:conversation_ids)
                GROUP BY cm.creator_contact_id, cm.conversation_id;
            ", ['conversation_ids' => $conversation_ids])->fetchAll();
            foreach ($conversation_participants as $participant) {
                $p_id = $participant['conversation_id'];
                unset($participant['conversation_id']);
                $conversations[$p_id]['participants'][] = $participant;
            }
            $messages = $this->prepareMessagesForLog($messages);
            $log = array_map(function ($el) use ($messages) {
                if ($el['object_type'] == crmLogModel::OBJECT_TYPE_MESSAGE && isset($messages[$el['object_id']])) {
                    $el['message'] = $messages[$el['object_id']];
                }
                return $el;
            }, $log);
        }

        // Explain log params
        $log = array_map(function ($l) use ($lm) {
            if (ifset($l['params'], false)) {
                $params = json_decode($l['params'], true);
                if ($params) {
                    $funnel_id_before = 0;
                    if (isset($params['stage_id_before']) && isset($lm->stages[$params['stage_id_before']])) {
                        $l['stage_before'] = $lm->stages[$params['stage_id_before']];
                        $funnel_id_before = $l['stage_before']['funnel_id'];
                        $l['stage_before'] = $this->filterFields($l['stage_before'], ['id', 'name', 'color'], ['id' => 'integer']);
                        unset($l['before']);
                    }
                    if (isset($params['stage_id_after']) && isset($lm->stages[$params['stage_id_after']])) {
                        $l['stage_after'] = $lm->stages[$params['stage_id_after']];
                        if ($funnel_id_before && $funnel_id_before != $l['stage_after']['funnel_id']) {
                            $l['action_name'] = _w('changed funnel');
                        }
                        $l['stage_after'] = $this->filterFields($l['stage_after'], ['id', 'name', 'color'], ['id' => 'integer']);
                        unset($l['after']);
                    }
                    if ($l['action'] == 'deal_edit') {
                        if (count($params) === 1) {
                            $l['before'] = ifempty($params, 0, 'before', null);
                            $l['after'] = ifempty($params, 0, 'after', null);
                            $f_name = ifset($params, 0, 'field', null);
                            if ($_name = crmDealFields::get($f_name)) {
                                if ($_name->getType() == 'Checkbox') {
                                    $l['before'] = empty($l['before']) ? _ws('No') : _ws('Yes');
                                    $l['after'] = empty($l['after']) ? _ws('No') : _ws('Yes');
                                }
                                $_name = $_name->getName();
                            } else {
                                $_fld = [
                                    'user_contact_id' => _w('Owner'),
                                    'amount' => _w('Estimated amount'),
                                    'currency_id' => _w('Currency'),
                                    'expected_date' => _w('Estimated close date')
                                ];
                                $_name = ifset($_fld, $f_name, '');
                                if ($f_name == 'amount') {
                                    if (ifempty($l['before'])) {
                                        $l['before'] = waLocale::format($l['before'], false);
                                    }
                                    if (ifempty($l['after'])) {
                                        $l['after'] = waLocale::format($l['after'], false);
                                    }
                                }
                            }
                            $_an = '';
                            if (empty($l['before'])) {
                                $_an = sprintf(_w('%s added'), $_name);
                            } elseif (empty($l['after'])) {
                                $_an = sprintf(_w('%s removed'), $_name);
                            } else {
                                $_an = sprintf(_w('%s changed'), $_name);
                            }
                            $l['action_name'] = mb_strtoupper(mb_substr($_an, 0, 1)) . mb_strtolower(mb_substr($_an, 1, mb_strlen($_an)));
                        } else {
                            foreach ($params as $_param) {
                                if (ifset($_param, 'field', null) == 'amount') {
                                    $_amount = $_param;
                                } elseif (ifset($_param, 'field', null) == 'currency_id') {
                                    $_currency_id = $_param;
                                }
                            }
                            if (isset($_amount, $_currency_id)) {
                                $l['after'] = $l['before'] = null;
                                if (ifempty($_amount, 'before', null)) {
                                    $l['before'] = waCurrency::format('%{s}', $_amount['before'], ifempty($_currency_id, 'before', null));
                                }
                                if (ifempty($_amount, 'after', null)) {
                                    $l['after'] = waCurrency::format('%{s}', $_amount['after'], ifempty($_currency_id, 'after', null));
                                }
                                $_an = '';
                                if (empty($l['before'])) {
                                    $_an = sprintf(_w('%s added'), _w('Estimated amount'));
                                } elseif (empty($l['after'])) {
                                    $_an = sprintf(_w('%s removed'), _w('Estimated amount'));
                                } else {
                                    $_an = sprintf(_w('%s changed'), _w('Estimated amount'));
                                }
                                $l['action_name'] = mb_strtoupper(mb_substr($_an, 0, 1)) . mb_strtolower(mb_substr($_an, 1, mb_strlen($_an)));
                            }
                        }
                    }
                }
            }
            return $l;
        }, $log);

        $log = $this->prepareLog($log, $conversations, $userpic_size, $deal);

        $this->response = array(
            'count' => ($count < $limit ? count($log) : $count),
            'log'   => $this->filterData(
                $log,
                [
                    'id',
                    'create_datetime',
                    'action',
                    'object_type',
                    'object_id',
                    'before',
                    'after',
                    'stage_before',
                    'stage_after',
                    'action_name',
                    'actor',
                    'content',
                    'contact',
                    'deal',
                    'message',
                    'invoice',
                    'reminder',
                    'call',
                    'file',
                    'order',
                    'order_log_item',
                    'icon'
                ], [
                    'id' => 'integer',
                    'object_id' => 'integer',
                    'create_datetime' => 'datetime'
                ]
            )
        );
    }

    private function _getFilterActions()
    {
        $filter_actions = array(
            'all' => array(
                'id'   => 'all',
                'name' => _w('All types'),
            ),
        );
        foreach (wa('crm')->getConfig()->getLogType() as $action => $data) {
            $filter_actions[$action] = array('id' => $action) + $data;
        }

        return $filter_actions;
    }

    private function _getFilters($filter_actions, $_filters)
    {
        return array(
            'reminders' => array(
                'id' => 'reminders',
                'name' => _w('Reminders'),
                'color' => !empty($filter_actions["reminder"]["color"]) ? $filter_actions["reminder"]["color"] : false,
                'is_active' => !$_filters || in_array('reminders', $_filters)
            ),
            'notes' => array(
                'id' => 'notes',
                'name' => _w('Notes'),
                'color' => !empty($filter_actions["note"]["color"]) ? $filter_actions["note"]["color"] : false,
                'is_active' => !$_filters || in_array('notes', $_filters)
            ),
            'files' => array(
                'id' => 'files',
                'name' => _w('Files'),
                'color' => !empty($filter_actions["file"]["color"]) ? $filter_actions["file"]["color"] : false,
                'is_active' => !$_filters || in_array('files', $_filters)
            ),
            'invoices' => array(
                'id' => 'invoices',
                'name' => _w('Invoices'),
                'color' => !empty($filter_actions["invoice"]["color"]) ? $filter_actions["invoice"]["color"] : false,
                'is_active' => !$_filters || in_array('invoices', $_filters)
            ),
            'deals' => array(
                'id' => 'deals',
                'name' => _w('Deals'),
                'color' => !empty($filter_actions["deal"]["color"]) ? $filter_actions["deal"]["color"] : false,
                'is_active' => !$_filters || in_array('deals', $_filters)
            ),
            'contacts' => array(
                'id' => 'contacts',
                'name' => _w('Contacts'),
                'color' => !empty($filter_actions["contact"]["color"]) ? $filter_actions["contact"]["color"] : false,
                'is_active' => !$_filters || in_array('contacts', $_filters)
            ),
            'messages' => array(
                'id' => 'messages',
                'name' => _w('Messages'),
                'color' => !empty($filter_actions["message"]["color"]) ? $filter_actions["message"]["color"] : false,
                'is_active' => !$_filters || in_array('messages', $_filters)
            ),
            'calls' => array(
                'id' => 'calls',
                'name' => _w('Calls'),
                'color' => !empty($filter_actions["call"]["color"]) ? $filter_actions["call"]["color"] : false,
                'is_active' => !$_filters || in_array('calls', $_filters)
            ),
            'order_log' => array(
                'id' => 'order_log',
                'name' => _w('Orders'),
                'color' => !empty($filter_actions["order_log"]["color"]) ? $filter_actions["order_log"]["color"] : false,
                'is_active' => !$_filters || in_array('order_log', $_filters) || in_array('orders', $_filters)
            )
        );
    }

    /**
     * Get contact object (even if contact not exists)
     * BUT please don't save it
     *
     * @param int|array $contact ID or data
     * @return array
     * @throws waException
     */
    protected function newContact($contact, $do_filter = true)
    {
        $contact_id = null;
        if ($contact instanceof waContact) {
            $contact_id = $contact['id'];
        }

        if (wa_is_int($contact) && $contact > 0) {
            $contact_id = $contact;
        } elseif (isset($contact['id']) && wa_is_int($contact['id']) && $contact['id'] > 0) {
            $contact_id = $contact['id'];
        }

        if (empty($contact_id)) {
            return null;
        }

        $wa_contact = (new waContactModel)->getById($contact_id);
        if (empty($wa_contact)) {
            $wa_contact = [
                'id' => $contact_id,
                'name' => sprintf_wp("Contact with ID %s doesn't exist", $contact_id),
            ];
        }
        if ($do_filter) {
            $wa_contact = $this->filterFields($wa_contact, ['id', 'name', 'photo', 'is_company']);
        }
        return $wa_contact;
    }
}
