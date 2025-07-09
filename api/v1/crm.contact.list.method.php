<?php

class crmContactListMethod extends crmApiAbstractMethod
{
    const MAX_LIMIT = 500;
    const EXT_FIELDS = [
        'tags',
        'online_status',
        'last_action',
        'acl',
        'is_pinned',
        'is_editable',
    ];
    const EXCLUSION_FIELDS = [
        'acl',
        'online_status',
        'is_pinned',
        'is_editable',
    ];

    /** @var crmContactsCollection */
    private $collection;
    private $sort;
    private $fields = [];
    private $columns = [];
    private $all_fields = [];
    protected $available_sorts = [
        'last_action' => ['crm_last_log_datetime', 'DESC'],
        'name' => ['name', 'ASC'],
        'create_datetime' => ['create_datetime', 'DESC'],
        'last_datetime' => ['last_datetime', 'DESC'],
    ];

    public function execute()
    {
        $hash = waRequest::get('hash', '', waRequest::TYPE_STRING_TRIM);
        $field_names = waRequest::get('fields', [], waRequest::TYPE_ARRAY_TRIM);
        $sort_field = waRequest::get('sort', 'last_action', waRequest::TYPE_STRING_TRIM);
        $asc = waRequest::get('asc', null, waRequest::TYPE_INT);
        $context_contact_id = waRequest::get('context_contact_id', 0, waRequest::TYPE_STRING_TRIM);
        $context_contact_id = (is_numeric($context_contact_id) ? $context_contact_id : 0);
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $limit = waRequest::get('limit', $this->getConfig()->getContactsPerPage(), waRequest::TYPE_INT);
        $limit = min($limit, self::MAX_LIMIT);
        $userpic_size = abs(waRequest::get('userpic_size', self::USERPIC_SIZE, waRequest::TYPE_INT));

        if (!empty($hash) && $error = $this->hashValidate($hash)) {
            throw new waAPIException('invalid_hash', $error, 400);
        }
        $this->all_fields = crmContact::getAllColumns('all_api');
        $field_names = $this->fieldsFilter($field_names);
        $this->setAvailableSort($sort_field, $asc);
        $fields = $this->getFields($field_names);

        if ($hash === 'recent') {
            $recent = $this->getRecent();
            $collection = $this->getCollection('id/'.implode(',', array_keys($recent)));
        } else {
            $collection = $this->getCollection($hash);
        }

        $total_count = $collection->count();
        if ($context_contact_id) {
            $contacts = $this->getContextContact($collection, $fields, $context_contact_id, $offset, $limit);
        } else {
            $contacts = $collection->getContacts(join(',', $fields), $offset, $limit);
        }

        $log = [];
        $creators = [];
        $apps = [];
        $adhoc_group = [];
        $pinned_recent = [];
        $do_get_pinned = false;
        $handle_last_action = in_array('crm_last_log_id', $fields);
        if ($handle_last_action) {
            $log_ids = array_column($contacts, 'crm_last_log_id');
            $log = $this->prepareLastActions($log_ids, $userpic_size);
            $creator_ids = array_column(array_filter($contacts, function ($el) use ($log) {
                return empty($el['crm_last_log_id']) || !isset($log[$el['crm_last_log_id']]);
            }), 'create_contact_id');
            if (!empty($creator_ids)) {
                $creators = $this->getContactsMicrolist($creator_ids);
                $creators = array_reduce($creators, function ($result, $el) {
                    $result[$el['id']] = $el;
                    return $result;
                }, []);
            }
            $apps = wa()->getApps();
        }

        if (in_array('crm_vault_id', $fields)) {
            $adhoc_group = $this->getAdhocGroupModel()->query('
                SELECT adhoc_id
                FROM crm_adhoc_group
                GROUP BY adhoc_id
                HAVING COUNT(*) = 1
            ')->fetchAll('adhoc_id');
        }
        if (in_array('is_pinned', $fields)) {
            $pinned_recent = $this->getRecent(false);
            $do_get_pinned = true;
        }

        $contacts = array_map(function ($el) use ($log, $creators, $apps, $handle_last_action, $adhoc_group, $pinned_recent, $do_get_pinned) {
            $el['fields'] = [];
            $disallow_fields = self::EXT_FIELDS;
            foreach ($this->fields as $_field_name) {
                if (isset($el[$_field_name]) && !in_array($_field_name, $disallow_fields)) {
                    if (strpos($_field_name, ':')) {
                        $_f = explode(':', $_field_name);
                        $_f_name = array_shift($_f);
                        if (isset($el[$_f_name])) {
                            $el[$_field_name] = $el[$_f_name];
                            $el['fields'][] = $this->fieldFormat($_field_name, $el[$_field_name]);
                        }
                    } else {
                        $el['fields'][] = $this->fieldFormat($_field_name, $el[$_field_name]);
                    }
                }
            }
            if (isset($el['_online_status'])) {
                $el['online_status'] = (in_array($el['_online_status'], ['online', 'idle']) ? 'online' : 'offline');
            }
            if ($do_get_pinned) {
                $el['is_pinned'] = !!ifset($pinned_recent, $el['id'], 'is_pinned', false);
            }
            if (isset($el['crm_vault_id'])) {
                if ($el['crm_vault_id'] == 0) {
                    $el['acl'] = 'all';
                } else if ($el['crm_vault_id'] > 0) {
                    $el['acl'] = 'vault';
                    $el['vault_id'] = (int) $el['crm_vault_id'];
                } else if ($el['crm_vault_id'] < 0) {
                    $el['acl'] = (empty($adhoc_group[abs($el['crm_vault_id'])]) ? 'group' : 'own');
                }
            }
            $el['is_banned'] = ($el['is_user'] == -1);
            if ($handle_last_action) {
                if (ifset($el['crm_last_log_id']) && isset($log[$el['crm_last_log_id']])) {
                    $el['last_action'] = $this->filterFields($log[$el['crm_last_log_id']],
                        ['id', 'create_datetime', 'action', 'object_type', 'object_id', 'before', 'after', 'action_name', 'actor', 'content', 'deal', 'message', 'invoice', 'reminder', 'call', 'file', 'order', 'order_log_item', 'icon'],
                        ['id' => 'integer', 'object_id' => 'integer', 'create_datetime' => 'datetime']
                    );
                } elseif ($el['create_datetime']) {
                    $content = [];
                    $content_str = '';
                    $icon = [
                        'fa' => 'info',
                        'color' => '#AAAAAA',
                    ];
                    if (!empty($apps[$el['create_app_id']])) {
                        $icon = $this->getAppIcon($apps[$el['create_app_id']]);
                    }
                    if ($el['create_method'] && $el['create_app_id'] != 'crm') {
                        $content[] = $el['create_method'];
                    }
                    if (!empty($content)) {
                        $content_str = '('.join(', ', $content).')';
                    }
                    $el['last_action'] = [
                        'id' => 0,
                        'create_datetime' => $this->formatDatetimeToISO8601($el['create_datetime']),
                        'actor_contact_id' => intval($el['create_contact_id']),
                        'action' => null,
                        'object_id' => intval($el['id']),
                        'object_type' => 'CONTACT',
                        'action_name' => _w('added contact'),
                        'content' => $content_str,
                        'actor' => ifset($creators[$el['create_contact_id']]),
                        'icon' => $icon,
                    ];
                }
            }
            return $el;
        }, $contacts);

        $contacts = $this->prepareContactsList(
            $contacts,
            [
                'id',
                'create_datetime',
                'last_datetime',
                'name',
                'company',
                'is_company',
                'jobtitle',
                'company_contact_id',
                'userpic',
                'online_status',
                'tags',
                'last_action',
                'acl',
                'vault_id',
                'fields',
                'is_pinned',
                'is_banned',
                'is_editable',
            ],
            $userpic_size,
            $this->sort['key'] === 'name',
        );

        $this->response = [
            'params' => [
                'title' => $this->getTitle($collection, $hash),
                'hash'  => $hash,
                'sort'  => [
                    'field' => $this->sort['key'],
                    'asc'   => $this->sort['asc'],
                ],
                'offset'      => $offset,
                'limit'       => $limit,
                'total_count' => $total_count,
                'fields'      => $this->fields,
                'columns'     => $this->columns,
            ],
            'data' => $contacts,
        ];
    }

    private function hashValidate($hash)
    {
        $hash_available = [
            'id',
            'segment',
            'tag',
            'vault',
            'responsible',
            'crmSearch',
            'import',
            'recent'
        ];
        $split = explode('/', $hash);
        if (!in_array($split[0], $hash_available)) {
            if ($split[0] === 'recent') {
                return null;
            } elseif (!isset($split[0], $split[1])) {
                return _w('Invalid hash.');
            }
        } elseif (strpos($hash, 'import/') !== false) {
            /** exp: import/2023-05-20 12:00:30 */
            if (empty($split[1])) {
                return _w('Invalid hash: unknown date.');
            }
            $date = date_parse_from_format('Y-m-d H:i:s', $split[1]);
            if (!empty($date['errors'])) {
                return _w('Invalid hash: unknown date format (YYYY-mm-dd HH:ii:ss).');
            } elseif (!empty($date['warnings'])) {
                return _w('Invalid hash: incorrect date.');
            }
        }

        return null;
    }

    private function fieldsFilter($field_names = [])
    {
        $_fields = [];
        foreach ($field_names as $field_name) {
            if (strpos($field_name, ':')) {
                $_f = explode(':', $field_name);
                $_n = array_shift($_f);
                $_sub_n = array_shift($_f);
                if (
                    empty($this->all_fields[$_n])
                    || empty($this->all_fields[$_n]['sub_columns'][$_sub_n])
                ) {
                    continue;
                }
            } elseif (
                empty($this->all_fields[$field_name])
                && !in_array($field_name, self::EXT_FIELDS)
            ) {
                continue;
            }
            $_fields[] = $field_name;
        }

        return $_fields;
    }

    private function setAvailableSort($sort_field, $asc)
    {
        foreach ($this->all_fields as $_column) {
            if ($_column['is_sortable']) {
                $order = 'ASC';
                if (!$asc && isset($_column['field']) && $_column['field'] instanceof waContactDateField) {
                    $order = 'DESC';
                }
                if ($_column['is_composite'] && !empty($_column['sub_columns'])) {
                    foreach ($_column['sub_columns'] as $_name => $_data) {
                        if ($_data['is_sortable']) {
                            $this->available_sorts[$_column['id'].':'.$_name] = [$_name, $order];
                        }
                    }
                }
                $this->available_sorts[$_column['id']] = [$_column['id'], $order];
            }
        }
        if (empty($this->available_sorts[$sort_field])) {
            $sort_field = 'last_action';
        }
        $this->sort = [
            'key'   => $sort_field,
            'field' => ifempty($this->available_sorts, $sort_field, 0, null),
            'asc'   => !empty($asc)
        ];
    }

    protected function getFields($field_names)
    {
        $fields = [];
        $columns = [];
        foreach ($field_names as $_name) {
            if (strpos($_name, ':')) {
                $_f = explode(':', $_name);
                $_n = array_shift($_f);
                $_sub_n = array_shift($_f);
                $col_name = ifset($this->all_fields, $_n, 'name', $_n).': '.ifset($this->all_fields, $_n, 'sub_columns', $_sub_n, 'name', $_sub_n);
            } elseif ($_name === 'tags') {
                $col_name = _w('Tags');
            } elseif ($_name === 'last_action') {
                $col_name = _w('Last action');
            } else {
                $col_name = ifset($this->all_fields, $_name, 'name', $_name);
            }
            $fields[] = $_name;
            $columns[] = [
                'id'   => $_name,
                'name' => $col_name
            ];
        }
        $this->fields = $fields;
        $this->columns = array_values(array_filter($columns, function ($_c) {
            return !in_array($_c['id'], self::EXCLUSION_FIELDS);
        }));
        $fields = array_map(function ($el) {
            if ($el == 'online_status') {
                $el = '_online_status';
            } else if ($el == 'last_action') {
                $el = 'crm_last_log_id';
            } else if ($el == 'acl') {
                $el = 'crm_vault_id';
            }
            return $el;
        }, $fields);

        return array_merge(
            [
                'id',
                'name',
                'firstname',
                'middlename',
                'lastname',
                'create_datetime',
                'last_datetime',
                'photo',
                'create_contact_id',
                'create_app_id',
                'create_method',
                'is_user',
                'company',
                'is_company',
            ],
            $fields
        );
    }

    protected function getCollection($hash)
    {
        if ($this->collection instanceof waContactsCollection) {
            return $this->collection;
        }

        $options = [
            'check_rights' => true,
            'transform_phone_prefix' => 'all_domains',
            'full_email_info' => true,
        ];
        $order_by = [$this->sort['field'], ($this->sort['asc'] ? 'ASC' : 'DESC')];
        $split = explode('/', $hash);
        if (isset($split[0], $split[1]) && $split[0] === 'responsible') {
            if ($split[1] === 'me') {
                $hash = 'search/crm_user_id='.wa()->getUser()->getId();
            } elseif ($split[1] === 'no') {
                $hash = 'search/crm_user_id?=NULL';
            } else {
                $hash = 'search/crm_user_id='.intval($split[1]);
            }
        }

        // Does the ordering require a special join?
        $order_join_table = null;
        $order_join_table_on = '';
        $order_join_table_field = null;

        $cm = $this->getContactModel();
        $is_horizontal_field = $cm->fieldExists($order_by[0]);
        if (!$is_horizontal_field) {
            if (!empty($this->available_sorts[$this->sort['key']])) {
                $_name = $this->sort['field'];
                if (strpos($this->sort['key'], ':')) {
                    // composite field exp. address:city
                    $_f = explode(':', $this->sort['key']);
                    $_name = array_shift($_f);
                    $order_by[0] = $this->sort['key'];
                }
                if ($this->all_fields[$_name]['field']->getStorage() instanceof waContactDataStorage) {
                    $order_join_table = 'wa_contact_data';
                    $order_join_table_field = 'value';
                    $order_join_table_on = " AND :table.field='".$cm->escape($order_by[0])."'";
                } elseif ($this->all_fields[$order_by[0]]['field']->getStorage() instanceof waContactEmailStorage) {
                    $order_join_table = 'wa_contact_emails';
                    $order_join_table_field = 'email';
                }
            }
        }

        if ($order_join_table) {
            $options['update_count_ignore'] = true;
        }

        $this->collection = new crmContactsCollection($hash, $options);
        if ($order_join_table) {
            $collection1 = $this->collection;
            $collection2 = clone $collection1;
            $this->collection = new crmContactsCompositeCollection(array(
                $collection1, $collection2
            ));

            // First collection contains contacts with specified field set
            $table_alias = $collection1->addJoin(array(
                'table' => $order_join_table,
                'on' => 'c.id=:table.contact_id AND :table.sort=0 AND :table.`'.$order_join_table_field."`<>'' ".$order_join_table_on,
            ));
            $collection1->orderBy('~'.$table_alias.'.'.$order_join_table_field, $order_by[1]);

            // Second collection contains contacts with specified field not set
            $collection2->addLeftJoin(array(
                'table' => $order_join_table,
                'on' => 'c.id=:table.contact_id AND :table.sort=0 AND :table.`'.$order_join_table_field."`<>'' ".$order_join_table_on,
                'where' => ':table.contact_id IS NULL',
            ));
        } else {
            $this->collection->orderBy($order_by[0], $order_by[1]);
        }

        return $this->collection;
    }

    protected function prepareLastActions($log_ids, $userpic_size)
    {
        $lm = new crmLogModel();
        $log = $lm->getByField(['id' => $log_ids], 'id');
        $log = $lm->explainLog($log, 'contact', null, [
            'add_messenger_sources' => true,
            'handle_message_body' => true,
        ]);

        $messages = array_column($log, 'message');
        if (!empty($messages)) {
            $messages = $this->prepareMessagesForLog($messages);
            $log = array_map(function ($el) use ($messages) {
                if ($el['object_type'] == crmLogModel::OBJECT_TYPE_MESSAGE && isset($messages[$el['object_id']])) {
                    $el['message'] = $messages[$el['object_id']];
                }
                return $el;
            }, $log);
        }

        return $this->prepareLog($log, [], $userpic_size);
    }

    /**
     * @param crmContactsCollection $collection
     * @param $fields
     * @param $offset
     * @param $limit
     * @return mixed
     * @throws waException
     */
    private function getContextContact($collection, $fields, $context_contact_id, &$offset, $limit = self::MAX_LIMIT)
    {
        $fields = join(',', $fields);

        if (empty($offset)) {
            $sort_field = ($this->sort['field'] === 'crm_last_log_datetime' ? 'IFNULL(crm_last_log_datetime, create_datetime)' : $this->sort['field']);
            $asc_desc = ($this->sort['asc'] ? 'ASC' : 'DESC');
            $sub_sql  = "SELECT @i:=@i+1 npp, id FROM wa_contact, (SELECT @i:=0) x";
            $sub_sql .= " ORDER BY $sort_field $asc_desc";
            $collection_chunk = clone $collection;
            $collection_chunk->addField('npp', 'npp');
            $collection_chunk->addLeftJoin(
                "($sub_sql)",
                'c.id = :table.id'
            );
            $collection_chunk->addWhere('c.id = '.$context_contact_id);
            $chunk = $collection_chunk->getContacts('id', $offset, $limit);
            $chunk = reset($chunk);
            $offset = (int) floor(ifempty($chunk, 'npp', 0) / $limit) * $limit;
        }

        return $collection->getContacts($fields, $offset, $limit);
    }

    private function fieldFormat($name, $value)
    {
        if (strpos($name, ':')) {
            $_f = explode(':', $name);
            $name = array_shift($_f);
            $sub_name = array_shift($_f);
        }

        /** @var waContactField $field_obj */
        $field_obj = ifset($this->all_fields, $name, 'field', null);
        $is_multi = ifset($this->all_fields, $name, 'is_multi', false);
        if ($field_obj === null) {
            return [];
        }

        $_result = [];
        $value = ($is_multi ? (array) $value : [$value]);
        foreach ($value as $_val) {
            if (isset($sub_name)) {
                $sub_field_obj = $field_obj->getFields($sub_name);
                $_result[] = $this->valueFormat($sub_field_obj, $_val);
            } else {
                $_result[] = $this->valueFormat($field_obj, $_val);
            }
        }
        $result = [
            'id' => $name.(isset($sub_name) ? ":$sub_name" : '')
        ];
        if ($is_multi) {
            $result['value'] = $_result;
        } else {
            $result['value'] = reset($_result);
        }

        return $result;
    }

    private function valueFormat($field_obj, $value)
    {
        if ($field_obj instanceof waContactCompositeField) {
            $result = $field_obj->format($value, 'value');
        } elseif ($field_obj instanceof waContactPhoneField) {
            $result = (empty($value['value']) ? null : $field_obj->format($value['value'], 'value'));
        } elseif ($field_obj instanceof waContactEmailField) {
            $result = ifset($value, 'email', null);
        } elseif ($field_obj instanceof waContactCheckboxField) {
            $result = ($value ? _ws('Yes') : _ws('No'));
        } elseif ($field_obj instanceof waContactCountryField) {
            $value = ifset($value, 'data', 'country', null);
            $result = $field_obj->format($value, 'value');
        } elseif ($field_obj instanceof waContactSelectField) {
            try {
                if (is_array($value)) {
                    $value = ifset($value, 'data', $field_obj->getId(), null);
                }
                $result = $field_obj->getOptions($value);
            } catch (Exception $ex) {
                $result = $value;
            }
            if (is_array($result)) {
                $result = $value;
            }
        } elseif ($field_obj instanceof waContactDateField) {
            $result = $field_obj->format($value, 'value');
        } elseif ($field_obj instanceof waContactBirthdayField) {
            $result = (new waContactBirthdayLocalFormatter)->format($value);
        } elseif ($field_obj instanceof waContactRegionField) {
            $_val = ifset($value, 'data', 'region', null);
            $value = ifset($value, 'data', []);
            $result = $field_obj->format($_val, 'value', $value);
        } elseif ($field_obj instanceof waContactStringField) {
            if (is_string($value)) {
                $result = $value;
            } elseif (isset($value['value'])) {
                $result = $value['value'];
            } else {
                $result = ifset($value, 'data', $field_obj->getId(), null);
            }
        } elseif ($field_obj instanceof waContactConditionalField) {
            $result = ifset($value, 'data', $field_obj->getId(), null);
        } else {
            $result = $value;
        }

        return $result;
    }

    /**
     * @param crmContactsCollection $collection
     * @return string
     * @throws waException
     */
    private function getTitle($collection, $hash)
    {
        $split = explode('/', $hash);
        if (isset($split[0], $split[1]) && $split[0] === 'responsible') {
            if ($split[1] === 'me') {
                $owner = new waContact(wa()->getUser()->getId());
                return _w('Responsible').': '.$owner['name'];
            } elseif ($split[1] === 'no') {
                return _w('No responsible');
            } else {
                $owner = new waContact($split[1]);
                if (!$owner->exists()) {
                    throw new waAPIException('responsible_not_found', sprintf_wp('Responsible user not found: %s.', $split[1]), 404);
                }
                return _w('Responsible').': '.$owner['name'];
            }
        } elseif (isset($split[0]) && $split[0] === 'import') {
            return _w('Import contacts');
        } elseif (isset($split[0]) && $split[0] === 'recent') {
            return _w('Recent & favorite');
        }

        return $collection->getTitle();
    }

    /**
     * @param $all bool
     * @return array
     */
    private function getRecent($all = true)
    {
        return $this->getRecentModel()->select('user_contact_id, contact_id, is_pinned')
            ->where('user_contact_id = ?', $this->getUser()->getId())
            ->where('contact_id > 0'.($all ? '' : ' AND is_pinned = 1'))
            ->order('view_datetime DESC')
            ->fetchAll('contact_id');
    }
}
