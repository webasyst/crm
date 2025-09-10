<?php

class crmDealListMethod extends crmApiAbstractMethod
{
    use crmDealListTrait;

    const MAX_LIMIT = 500;
    const DEFAULT_LIMIT = 30;
    const SORT_FIELD = [
        'stage_id',
        'create_datetime',
        'reminder_datetime',
        'name',
        'amount',
        'user_name',
        'last_action'
    ];
    const STANDARD_FIELDS = [
        'amount',
        'tags',
        'user',
    ];

    private $deal_all_fields;

    public function execute()
    {
        /** uses in crmDealListTrait */
        $user_id = waRequest::get('user_id', null, waRequest::TYPE_INT);
        $contact_id = waRequest::get('contact_id', null, waRequest::TYPE_INT);
        $search = waRequest::get('search', null, waRequest::TYPE_STRING_TRIM);
        $fields = waRequest::get('fields', [], waRequest::TYPE_ARRAY_TRIM);
        $funnel_id = waRequest::get('funnel', 0, waRequest::TYPE_INT);
        $stage_id = waRequest::get('stage', 0, waRequest::TYPE_INT);
        $tag_id = waRequest::get('tag', 0, waRequest::TYPE_INT);
        $pinned_only = boolval(waRequest::get('pinned_only', 0, waRequest::TYPE_INT));
        $reminder_ids = waRequest::get('reminder', [], waRequest::TYPE_ARRAY_TRIM);
        $sort_field = waRequest::get('sort', '', waRequest::TYPE_STRING_TRIM);
        $userpic_size = abs(waRequest::get('userpic_size', self::USERPIC_SIZE, waRequest::TYPE_INT));
        $limit = waRequest::get('limit', self::DEFAULT_LIMIT, waRequest::TYPE_INT);
        $this->limit = ($limit > self::MAX_LIMIT || $limit < 1 ? self::DEFAULT_LIMIT : $limit);
        $this->user_id = 'all';

        if ($user_id) {
            if ($user_id < 0) {
                throw new waAPIException('not_found', _w('User not found.'), 404);
            } elseif (!(new crmContact($user_id))->exists()) {
                throw new waAPIException('not_found', _w('User not found.'), 404);
            }
            $this->user_id = $user_id;
        } elseif ($user_id === 0) {
            $this->user_id = 0;
        }
        if ($contact_id) {
            if ($contact_id < 0) {
                throw new waAPIException('not_found', _w('Contact not found'), 404);
            } elseif (!(new crmContact($contact_id))->exists()) {
                throw new waAPIException('not_found', _w('Contact not found'), 404);
            }
            $this->list_params['participants'] = [$contact_id];
        }

        $fields = array_filter($fields);

        if ($funnel_id < 0) {
            throw new waAPIException('not_found', _w('Funnel not found.'), 404);
        } elseif ($stage_id < 0) {
            throw new waAPIException('not_found', _w('Stage not found.'), 404);
        } elseif ($tag_id < 0) {
            throw new waAPIException('not_found', _w('Tag not found.'), 404);
        } elseif (!empty($fields) && $_f = array_diff($fields, $this->getConfigureFields(), self::STANDARD_FIELDS)) {
            throw new waAPIException('invalid_field', sprintf_wp('Unknown configured deal fields: %s.', implode(', ', $_f)), 400);
        } elseif (!empty($reminder_ids) && !!array_diff($reminder_ids, ['no', 'burn', 'overdue', 'actual'])) {
            throw new waAPIException('invalid_field', _w('Unknown reminder state.'), 400);
        } elseif (!empty($sort_field) && !in_array($sort_field, self::SORT_FIELD)) {
            throw new waAPIException('invalid_field', _w('Unknown sorting field.'), 400);
        }

        if ($search) {
            $this->list_params['deal_ids'] = $this->dealSearch($search);
        }

        $logs = [];
        $pinned_recent = (in_array('is_pinned', $fields) || $pinned_only) ? $this->getRecent(false) : [];
        $do_get_pinned = in_array('is_pinned', $fields);

        if ($pinned_only) {
            if (isset($this->list_params['deal_ids'])) {
                $this->list_params['deal_ids'] = array_intersect($this->list_params['deal_ids'], array_keys($pinned_recent));
            } else {
                $this->list_params['deal_ids'] = array_keys($pinned_recent);
            }
        }

        $data = $this->prepareData(true, $fields);

        //wa_dump($data);

        $deal_tags = $data['deal_tags'];
        $funnels = ifempty($data, 'funnels', []);
        $deal_params = $this->getDealParams($data['deals'], $fields);
        if (in_array('last_action', $fields)) {
            $log_ids = array_column($data['deals'], 'last_log_id');
            $logs = $this->prepareLastActions($log_ids, $userpic_size);
        }

        $deals = array_map(function ($el) use ($deal_params, $logs, $deal_tags, $funnels, $userpic_size, $pinned_recent, $do_get_pinned) {
            if (!empty($logs[$el['last_log_id']])) {
                $el['last_action'] = $this->filterFields(
                    $logs[$el['last_log_id']],
                    [
                        'id',
                        'create_datetime',
                        'action',
                        'object_type',
                        'object_id',
                        'before',
                        'after',
                        'action_name',
                        'actor',
                        'content',
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
                );
            }
            if (!empty($deal_params[$el['id']])) {
                $el['fields'] = $deal_params[$el['id']];
            }
            if ($do_get_pinned) {
                $el['is_pinned'] = !!ifset($pinned_recent, $el['id'], 'is_pinned', false);
            }
            if (isset($deal_tags[$el['id']])) {
                $el['tags'] = $this->prepareTags($deal_tags[$el['id']]);
            }
            if (isset($el['user']) && $el['user'] instanceof waContact) {
                $el['user'] = $this->prepareContactData($el['user'], $userpic_size);
            }
            if (isset($el['contact']) && $el['contact'] instanceof waContact) {
                $el['contact'] = $this->prepareContactData($el['contact'], $userpic_size);
            }
            if (empty($el['amount'])) {
                $el['amount']        = null;
                $el['currency_id']   = null;
                $el['currency_rate'] = null;
            }
            if (isset($el['shop_order']) && !ifset($el['shop_order']['id'])) {
                unset($el['shop_order']);
            }
            if (!empty($funnels) && !empty($funnels[$el['funnel_id']])) {
                $el['funnel'] = [
                    'id'    => (int) $el['funnel_id'],
                    'name'  => ifempty($funnels, $el['funnel_id'], 'name', ''),
                    'color' => ifempty($funnels, $el['funnel_id'], 'color', ''),
                    'icon'  => ifempty($funnels, $el['funnel_id'], 'icon', 'fas fa-briefcase'),
                ];
                $el['stage'] = [
                    'id'    => (int) $el['stage_id'],
                    'name'  => ifempty($funnels, $el['funnel_id'], 'stages', $el['stage_id'], 'name', ''),
                    'color' => ifempty($funnels, $el['funnel_id'], 'stages', $el['stage_id'], 'color', '')
                ];
            }
            return $el;
        }, array_values($data['deals']));

        $list_params = $data['list_params'];
        $filter = [];
        if (!empty($list_params['funnel_id'])) {
            $filter['funnel_id'] = intval($list_params['funnel_id']);
        }
        if (!empty($list_params['stage_id'])) {
            $filter['stage_id'] = intval($list_params['stage_id']);
        }
        if (!empty($list_params['tag_id'])) {
            $filter['tag_id'] = intval($list_params['tag_id']);
        }
        if (!empty($list_params['reminder_state'])) {
            $filter['reminder_state'] = $list_params['reminder_state'];
        }

        $this->response = [
            'params' => [
                'total_count' => intval($data['total_count']),
                'offset' => intval($list_params['offset']),
                'limit' => intval($list_params['limit']),
                'user_id' => $user_id,
                'search' => $search,
                'pinned_only' => $pinned_only,
                'sort' => $list_params['sort'],
                'asc' => $list_params['order'] === 'asc',
                'page' => floor($list_params['offset'] / $list_params['limit']) +1,
                'filter' => $filter,
                'fields' => $fields
            ],
            'data' => $this->filterData(
                $deals,
                [
                    'id',
                    'create_datetime',
                    'update_datetime',
                    'reminder_datetime',
                    'name',
                    'funnel_id',
                    'stage_id',
                    'funnel',
                    'stage',
                    'status_id',
                    'expected_date',
                    'closed_datetime',
                    'amount',
                    'currency_id',
                    'original_amount',
                    'original_currency_id',
                    'currency_rate',
                    'lost_id',
                    'lost_text',
                    'external_id',
                    'user',
                    'contact',
                    'source',
                    'shop_order',
                    'can_delete',
                    'reminder_state',
                    'reminder_title',
                    'message_unread',
                    'tags',
                    'last_action',
                    'fields',
                    'is_pinned',
                ], [
                    'id'                => 'integer',
                    'create_datetime'   => 'datetime',
                    'update_datetime'   => 'datetime',
                    'reminder_datetime' => 'datetime',
                    'funnel_id'         => 'integer',
                    'stage_id'          => 'integer',
                    'closed_datetime'   => 'datetime',
                    'amount'            => 'float',
                    'original_amount'   => 'float',
                    'currency_rate'     => 'float',
                    'lost_id'           => 'integer',
                    'can_delete'        => 'boolean',
                    'message_unread'    => 'boolean',
                    'is_pinned'         => 'boolean',
                ]
            )
        ];
    }

    protected function searchContacts($search_term)
    {
        if (preg_match('#^\S*@\S*$#', $search_term)) {
            /** передан email */
            return array_column((new waContactEmailsModel)->query("SELECT contact_id FROM wa_contact_emails WHERE email LIKE ?", ["%{$search_term}%"])->fetchAll(), 'contact_id');
        } elseif (preg_match('#^\+?[-\s\d]*\(?[-\s\d]+\)?[-\s\d]+$#', $search_term)) {
            /** передан phone */
            $search_term = preg_replace('/[^\d]+/', '', $search_term);
            return array_column((new waContactDataModel)->query("SELECT contact_id FROM wa_contact_data WHERE field='phone' AND value LIKE ?", ["%{$search_term}%"])->fetchAll(), 'contact_id');
        } else {
            /** передан name */
            return array_column((new waContactModel)->query("SELECT id FROM wa_contact WHERE name LIKE ? OR company LIKE ?", ["%{$search_term}%", "%{$search_term}%"])->fetchAll(), 'id');
        }
    }

    protected function dealSearch($search_term)
    {
        $deal_ids = array_column($this->getDealModel()->query("SELECT id FROM crm_deal WHERE name LIKE ?", ["%{$search_term}%"])->fetchAll(), 'id');
        $contact_ids = $this->searchContacts($search_term);
        if (!empty($contact_ids)) {
            $deal_ids = array_unique(array_merge(
                $deal_ids, 
                array_keys($this->getDealParticipantsModel()->getByField([
                    'role_id' => crmDealParticipantsModel::ROLE_CLIENT,
                    'contact_id' => $contact_ids
                ], 'deal_id'))
            ));
        }
        return $deal_ids;
    }

    protected function prepareContactData(waContact $contact, $userpic_size)
    {
        $data = array_merge(['id' => $contact->getId()], $contact->getCache());
        $data['name'] = waContactNameField::formatName($contact, true);
        $data['name'] = ifempty($data, 'name', '');

        return $this->filterFields($this->prepareUserpic($data, $userpic_size), ['id', 'name', 'userpic']);
    }

    private function getDealAllFields()
    {
        if (empty($this->deal_all_fields)) {
            $this->deal_all_fields = crmDealFields::getAll();
        }
        return $this->deal_all_fields;
    }

    private function getConfigureFields()
    {
        $configure_fields = $this->getDealAllFields() + ['last_action' => '', 'is_pinned' => ''];

        return array_keys($configure_fields);
    }

    private function getDealParams($deals, $fields)
    {
        if (empty($fields)) {
            return [];
        }

        $deal_ids = array_keys($deals);
        $deal_params = $this->getDealParamsModel()->get($deal_ids);
        if (empty($deal_params)) {
            return [];
        }

        $result = [];
        $fields = array_flip($fields);
        $all_requested_fields = array_intersect_key($this->getDealAllFields(), $fields);
        $all_requested_checkbox_fields = array_keys(array_filter($all_requested_fields, function ($field) {
            return $field instanceof crmDealCheckboxField;
        }));

        foreach ($this->getDealParamsModel()->get($deal_ids) as $_deal_id => $_params) {
            $result[$_deal_id] = [];
            foreach ($all_requested_checkbox_fields as $_field_id) {
                $result[$_deal_id][] = [
                    'id'    => strval($_field_id),
                    'value' => empty($_params[$_field_id]) ? _ws('No') : _ws('Yes'),
                ];
                unset($_params[$_field_id]);
            }
            $_params = array_intersect_key($_params, $fields);
            if (empty($_params)) {
                continue;
            }
            foreach ($_params as $_n => $_v) {
                $result[$_deal_id][] = [
                    'id'    => strval($_n),
                    'value' => isset($all_requested_fields[$_n]) ? $all_requested_fields[$_n]->format($_v) : strval($_v)
                ];
            }
        }

        return $result;
    }

    protected function prepareLastActions($log_ids, $userpic_size)
    {
        $lm = $this->getLogModel();
        $log = $lm->getByField(['id' => $log_ids], 'id');
        $log = $lm->explainLog($log, 'deal', null, [
            'add_messenger_sources' => true,
            'handle_message_body'   => true,
        ]);
        $message_ids = array_column(array_filter($log, function ($el) {
            return $el['object_type'] === crmLogModel::OBJECT_TYPE_MESSAGE;
        }), 'object_id');
        $files = $this->getAttachments($message_ids);
        foreach ($log as &$_log) {
            if (isset($files[$_log['object_id']])) {
                $_log['message']['attachments'] = $files[$_log['object_id']];
            }
        }

        return $this->prepareLog($log, [], $userpic_size);
    }

    private function getRecent($all = true)
    {
        return $this->getRecentModel()->select('user_contact_id, ABS(contact_id) deal_id, is_pinned')
            ->where('user_contact_id = ?', $this->getUser()->getId())
            ->where('contact_id < 0'.($all ? '' : ' AND is_pinned = 1'))
            ->order('view_datetime DESC')
            ->fetchAll('deal_id');
    }
}
