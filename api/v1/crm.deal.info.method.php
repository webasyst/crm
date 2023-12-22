<?php

class crmDealInfoMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    protected $deal;

    public function execute($deal_id = null)
    {
        $deal_id = abs($deal_id ?: $this->get('id', true));
        $userpic_size = abs(waRequest::get('userpic_size', self::USERPIC_SIZE, waRequest::TYPE_INT));
        $this->deal = $this->getDealModel()->getDeal($deal_id, true, true);
        
        if (empty($this->deal)) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        }

        $deal_access_level = $this->getCrmRights()->deal($this->deal);
        if ($deal_access_level === crmRightConfig::RIGHT_DEAL_NONE) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        if ($magic_source_email = crmHelper::getMagicSourceEmail($this->deal)) {
            $this->deal['email'] = $magic_source_email;
        }
        $this->deal['is_pinned'] = $this->isPinned($deal_id);

        if (isset($this->deal['fields']['source'])) {
            $source = [
                'id' => intval($this->deal['source_id']),
                'type' => $this->deal['fields']['source']['type'],
                'name' => $this->deal['fields']['source']['value'],
            ];
            if (!empty($this->deal['fields']['source']['provider'])) {
                $source['provider'] = $this->deal['fields']['source']['provider'];
            }
            if (!empty($this->deal['fields']['source']['icon'])) {
                $source['icon_url'] = $this->deal['fields']['source']['icon'];
            }
            $this->deal['source'] = $source;
        }
        unset($this->deal['source_id']);

        $this->deal['fields'] = $this->getDealFields();
        unset($this->deal['params']);

        $this->deal['tags'] = $this->getTags();

        if (!empty($this->deal['participants'])) {
            $contacts = $this->getContacts();
            $this->deal['users'] = $this->getDealUsers($contacts, $userpic_size);
            $this->deal['contacts'] = $this->getDealContacts($contacts, $userpic_size, !empty($order));
        }

        $funnel_rights_value = $this->getCrmRights()->funnel($this->deal['funnel_id']);
        $can_edit_deal = ($deal_access_level > crmRightConfig::RIGHT_DEAL_VIEW);
        $can_delete = ($deal_access_level === crmRightConfig::RIGHT_DEAL_ALL);
        $can_manage_responsible = ($this->deal['user_contact_id'] == $this->getUser()->getId()
            || $funnel_rights_value > 2
            || !$this->deal['contacts']['user'] && $funnel_rights_value > 0);

        $this->deal['rights'] = [
            'can_edit'               => $can_edit_deal,
            'can_delete'             => $can_delete,
            'can_manage_responsible' => $can_manage_responsible,
            'has_access_to_funnel'   => $funnel_rights_value > crmRightConfig::RIGHT_FUNNEL_NONE
        ];

        $this->getRecentModel()->update($deal_id * -1);

        $this->response = $this->filterFields(
            $this->deal,
            [
                'id',
                'creator_contact_id',
                'create_datetime',
                'update_datetime',
                'reminder_datetime',
                'funnel_id',
                'stage_id',
                'status_id',
                'name',
                'description',
                'description_sanitized',
                'description_plain',
                'expected_date',
                'closed_datetime',
                'amount',
                'currency_id',
                'currency_rate',
                'contact_id',
                'user_contact_id',
                'lost_id',
                'lost_text',
                'external_id',
                'last_message_id',
                'email',
                'is_pinned',
                'rights',
                'users',
                'contacts',
                'fields',
                'tags',
                'source',
            ], [
                'id' => 'integer',
                'creator_contact_id' => 'integer',
                'create_datetime' => 'datetime',
                'update_datetime' => 'datetime',
                'reminder_datetime' => 'datetime',
                'funnel_id' => 'integer',
                'stage_id' => 'integer',
                'closed_datetime' => 'datetime',
                'amount' => 'float',
                'currency_rate' => 'float',
                'contact_id' => 'integer',
                'user_contact_id' => 'integer',
                'lost_id' => 'integer',
                'last_message_id' => 'integer',
                'is_pinned' => 'boolean'
            ]
        );
    }

    private function getContacts()
    {
        $participants = array_combine(
            array_column($this->deal['participants'], 'contact_id'),
            $this->deal['participants']
        );
        $ids = array_keys($participants);
        $collection = new crmContactsCollection('/id/'.join(',', $ids), [
            'check_rights' => true,
            'transform_phone_prefix' => 'all_domains'
        ]);
        $contacts = $collection->getContacts('*,phone,address,email.*', 0, 10);

        return array_map(function ($_contact) use ($participants) {
            $_contact['assigned_at'] = $_contact['create_datetime'];
            $_contact += ifset($participants, $_contact['id'], []);
            return $_contact;
        }, $contacts);
    }

    private function getDealUsers($contacts, $userpic_size)
    {
        $deal_users = [];
        foreach ($contacts as $_contact) {
            if ($_contact['role_id'] === crmDealParticipantsModel::ROLE_USER) {
                $_contact = $this->prepareUserpic($_contact, $userpic_size);
                $_contact['name'] = waContactNameField::formatName($_contact, true);
                $deal_users[] = [
                    'label'       => $_contact['label'],
                    'assigned_at' => $this->formatDatetimeToISO8601($_contact['assigned_at']),
                    'contact'     => $this->filterFields($_contact, ['id', 'name', 'userpic'])
                ];
            }
        }

        return $deal_users;
    }

    private function getDealContacts($contacts, $userpic_size, $has_order)
    {
        $deal_clients = [];
        $address_obj = waContactFields::get('address');
        $counters = crmDeal::getDealPageContactCounters(ifset($contacts, $this->deal['contact_id'], []), $contacts, $has_order);
        $counters_deal = ifset($counters, 'deal_counters', []);
        $counters_shop = ifset($counters, 'order_counters', []);
        foreach ($contacts as $id => $_contact) {
            if ($_contact['role_id'] === crmDealParticipantsModel::ROLE_CLIENT) {
                $_contact['name'] = waContactNameField::formatName($_contact, true);
                if (!!ifempty($_contact, 'is_company', '')) {
                    $_contact['company'] = '';
                }
                $_contact = $this->prepareUserpic($_contact, $userpic_size);
                if (isset($_contact['phone'])) {
                    $_contact['phone'] = $this->addFormattedPhoneValues($_contact['phone']);
                }
                if (isset($_contact['email'])) {
                    $_contact['email'] = $this->addFormattedEmailValues($_contact['email']);
                }
                if (!empty($_contact['address'])) {
                    $short_address = $this->formatAddress($_contact['address']);
                    $_contact['address'] = array_map(function ($_address) use ($address_obj) {
                        return [
                            'value'   => ifempty($_address, 'value', ''),
                            'map_url' => $this->getUrlMap(
                                $address_obj->format($_address, 'value'),
                                ifset($_address, 'data', 'lng', null),
                                ifset($_address, 'data', 'lat', null)
                            )
                        ];
                    }, $short_address);
                }
                $deal_clients[] = [
                    'label'       => $_contact['label'],
                    'assigned_at' => $this->formatDatetimeToISO8601($_contact['assigned_at']),
                    'counters'    => [
                        'deal'       => ifset($counters_deal, $id, null),
                        'shop_order' => ifset($counters_shop, $id, null)
                    ],
                    'contact' => $this->filterFields(
                        $_contact,
                        ['id', 'name', 'userpic', 'jobtitle', 'company', 'company_contact_id', 'address', 'email', 'phone'],
                        ['company_contact_id' => 'integer']
                    )
                ];
            }
        }

        return $deal_clients;
    }

    private function getDealFields()
    {
        $fields = [];
        foreach ($this->deal['fields'] as $key => $_val) {
            $field = $this->deal['fields'][$key];
            if (isset($this->deal['params'][$key])) {
                $fields[] = [
                    'id' => $field['id'],
                    'name' => $field['name'],
                    'type' => $this->normalizeFieldType($field['type']),
                    'data' => ifset($this->deal, 'params', $key, ''),
                    'value' => $field['value_formatted'],
                ];
            } elseif ($this->normalizeFieldType($_val['type']) == 'checkbox') {
                $fields[] = [
                    'id' => $field['id'],
                    'name' => $field['name'],
                    'type' => $this->normalizeFieldType($field['type']),
                    'data' => ifset($this->deal, 'params', $key, ''),
                    'value' => ($field['value_formatted'] ? _ws('Yes') : _ws('No'))
                ];
            }
        }
        return $fields;
    }

    protected function getTags()
    {
        return $this->prepareTags((new crmTagModel)->getByContact(-1 * $this->deal['id'], false));
    }

    /**
     * @param $deal_id
     * @return bool
     * @throws waException
     */
    private function isPinned($deal_id)
    {
        if (empty($deal_id)) {
            return false;
        }
        $recent = $this->getRecentModel()->getByField([
            'user_contact_id' => $this->getUser()->getId(),
            'contact_id'      => -$deal_id
        ]);

        return !!ifset($recent, 'is_pinned', 0);
    }

    /**
     * @param $addresses
     * @return array
     * @throws waException
     */
    private function formatAddress($addresses)
    {
        $address_fields = waContactFields::get('address')->getFields();
        foreach ((array) $addresses as $key => $_address) {
            $value = [];
            foreach ($address_fields as $field) {
                if ($field instanceof waContactHiddenField) {
                    continue;
                }

                /** @var $field waContactField */
                $f_id = $field->getId();
                if (isset($_address['data'][$f_id])) {
                    if ($f_id === 'country') {
                        $tmp = trim($field->format($_address['data'][$f_id], 'value', $_address['data']));
                    } elseif ($f_id === 'region') {
                        $tmp = trim($field->format($_address['data'][$f_id], '', $_address['data']));
                    } else {
                        $tmp = (string) $_address['data'][$f_id];
                    }
                    if (!in_array($f_id, ['country', 'region', 'zip', 'street', 'city'])) {
                        if ($field instanceof waContactSelectField) {
                            try {
                                $tmp = $field->getOptions($tmp);
                            } catch (Exception $e) {
                                //
                            }
                        }
                        $tmp = $field->getName().' '.$tmp;
                    }
                    $value[$f_id] = $tmp;
                }
            }

            $addresses[$key]['value'] = implode(', ', array_filter($value, 'strlen'));
        }

        return $addresses;
    }
}
