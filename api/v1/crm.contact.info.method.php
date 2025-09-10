<?php

class crmContactInfoMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $contact_id = $this->get('id', true);
        if (!is_numeric($contact_id) || $contact_id < 1) {
            throw new waAPIException('invalid_param', _w('Invalid contact ID.'), 400);
        }

        $contact = new waContact($contact_id);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        }

        $rights = $this->getCrmRights();
        if (!$rights->contact($contact)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $fields = $this->getContactData($contact);
        $data = $this->formatData($fields);
        $main_fields = ['name', 'title', 'firstname', 'middlename', 'lastname', 'jobtitle', 'company', 'company_contact_id', 'sex', 'birthday', 'locale', 'timezone', 'email', 'phone', 'address', 'url', 'im', 'socialnetwork', 'about'];
        $main = [];
        foreach ($main_fields as $field_id) {
            $field = $this->getField($field_id, $fields);
            if (empty($field)) {
                if (in_array($field_id, ['email', 'phone', 'address', 'url', 'im', 'socialnetwork'])) {
                    $main[$field_id] = [];
                } elseif ($field_id === 'name') {
                    $main[$field_id] = '('._w('no name').')';
                } else {
                    $main[$field_id] = null;
                }
                continue;
            }
            if ($field_id === 'birthday') {
                $main[$field_id] = [
                    'value' => ifset($field['value'], null),
                    'date_parts' => $this->filterFields(
                        ifset($field['date_parts'], null), 
                        ['year', 'month', 'day'], 
                        ['year' => 'integer', 'month' => 'integer', 'day' => 'integer']
                    ),
                ];
            } elseif ($field_id === 'company_contact_id') {
                $main[$field_id] = intval($field['value']);
            } elseif (isset($field['value'])) {
                $main[$field_id] = $field['value'];
            } elseif (isset($field['values'])) {
                $main[$field_id] = $field['values'];
            }
        }

        $create_app_id = $contact->get('create_app_id');
        $create_app = null;
        if (!empty($create_app_id)) {
            $apps = wa()->getApps();
            if (!empty($apps[$create_app_id])) {
                $create_app = $apps[$create_app_id]['name'];
            }
        }
        $main['create_app'] = $create_app;

        $fields = array_filter($fields, function ($el) use ($main_fields) {
            return !in_array($el['id'], $main_fields);
        });

        if (isset($main['address'])) {
            $main['address'] = array_map(function ($el) {
                $res = [];
                foreach ($el['data'] as $idx => $field) {
                    if (in_array($field['id'], ['country', 'region', 'zip', 'city', 'street', 'lng', 'lat'])) {
                        if (in_array($field['id'], ['country', 'region'])) {
                            $res[$field['id']] = $field['data'];
                            $res[$field['id'].'_value'] = $field['value'];
                        } else {
                            $res[$field['id']] = $field['value'];
                        }
                        unset($el['data'][$idx]);
                    }
                }
                $el['aux_data'] = array_values($el['data']);
                $el['data'] = $res;
                $el['map_url'] = $this->getUrlMap(
                    ifset($el, 'value', null),
                    ifset($res, 'lng', null),
                    ifset($res, 'lat', null)
                );
                return $el;
            }, ifempty($main['address'], []));
        }

        $this->response = [
            'id'          => intval($contact_id),
            'main'        => $main,
            'data'        => $data,
            'fields'      => array_values($fields),
            'details'     => [
                'create_datetime'    => $this->formatDatetimeToISO8601($contact->get('create_datetime')),
                'create_app_id'      => $create_app_id,
                'create_method'      => $contact->get('create_method'),
                'create_contact_id'  => intval($contact->get('create_contact_id')),
                'is_company'         => !empty($contact->get('is_company')),
                'company_contact_id' => intval($contact->get('company_contact_id')),
                'is_user'            => !empty($contact->get('is_user')) && $contact->get('is_user') != -1,
                'is_banned'          => ($contact->get('is_user') == -1),
                'login'              => $contact->get('login'),
                'sex'                => $contact->get('sex'),
                'has_password'       => !empty($contact->get('password')),
                'last_datetime'      => $this->formatDatetimeToISO8601($contact->get('last_datetime')),
                'locale'             => $contact->get('locale'),
                'timezone'           => $contact->get('timezone'),
            ],
            'is_editable' => $rights->contactEditable($contact),
            'is_pinned'   => $this->isPinned($contact_id),
            'segments'    => $this->getSegments($contact_id),
            'tags'        => $this->getTags($contact_id),
            'userpic'     => [
                'thumb' => $this->getDataResourceUrl($contact->getPhoto2x(waRequest::get('userpic_size', 96, waRequest::TYPE_INT))),
            ],
            'creator'     => null,
            'responsible' => null,
        ];

        if (boolval($contact['photo'])) {
            $this->response['userpic']['original'] = $this->getDataResourceUrl($contact->getPhoto2x('original'));
            $this->response['userpic']['original_crop'] = $this->getDataResourceUrl($contact->getPhoto2x('original_crop'));
        }

        // Count company employees
        if ($contact['is_company']) {
            $this->response['employees_count'] = (new waContactModel)->countByField(['company_contact_id' => $contact_id]);
        }

        if ($contact['crm_vault_id'] > 0) {
            $this->response['vault'] = $this->filterFields(
                (new crmVaultModel)->getById($contact['crm_vault_id']), 
                ['id', 'name', 'color', 'create_datetime', 'count'],
                ['id' => 'integer', 'count' => 'integer', 'create_datetime' => 'datetime']
            );
        } else {
            $owner_ids = (new crmAdhocGroupModel)->getByGroup(-$contact['crm_vault_id']);
            if (!empty($owner_ids)) {
                $this->response['owners'] = $this->getContactsMicrolist($owner_ids);
            }
        }

        // Add creator & responsible currently assigned to this contact
        $aux_contact_ids = [];
        if ($contact['create_contact_id'] > 0) {
            $aux_contact_ids[] = $contact['create_contact_id'];
        }
        if ($contact['crm_user_id'] > 0) {
            $aux_contact_ids[] = $contact['crm_user_id'];
        }
        if (!empty($aux_contact_ids)) {
            $aux_contacts = $this->getContactsMicrolist($aux_contact_ids);
            if (!empty($aux_contacts)) {
                foreach($aux_contacts as $c) {
                    if ($c['id'] == $contact['create_contact_id']) {
                        $this->response['creator'] = $c;
                    }
                    if ($c['id'] == $contact['crm_user_id']) {
                        $this->response['responsible'] = $c;
                    }
                }
            }
        }

        if (waRequest::get('with_counters', false)) {
            $deal_ids = array_keys($this->getDealParticipantsModel()->getByField([
                'contact_id' => $contact_id, 
                'role_id' => crmDealParticipantsModel::ROLE_CLIENT
            ], 'deal_id'));
            $condition_ids = array_map(function($deal_id) {
                return $deal_id * -1;
            }, $deal_ids);
            $condition_ids[] = $contact_id;

            $this->response['counters'] = [
                [
                    'name' => 'deals',
                    'value' => count($deal_ids),
                ],
                [
                    'name' => 'reminders',
                    'value' => (int) $this->getReminderModel()->countByField([
                        'contact_id' => $contact_id,
                        'complete_datetime' => null,
                    ]),
                ],
                [
                    'name' => 'invoices',
                    'value' => (int) $this->getInvoiceModel()->countByField('contact_id', $contact_id),
                ],
                [
                    'name' => 'calls',
                    'value' => (int) $this->getCallModel()->countByField('client_contact_id', $contact_id),
                ],
                [
                    'name' => 'notes',
                    'value' => (int) $this->getNoteModel()->countByField('contact_id', $contact_id),
                ],
                [
                    'name' => 'files',
                    'value' => (int) $this->getFileModel()->countByField('contact_id', $condition_ids),
                ],
                [
                    'name' => 'messages',
                    'value' => (int) $this->getMessageModel()->select('COUNT(*)')->where('contact_id = '.(int)$contact_id.' AND conversation_id IS NOT NULL')->fetchField(),
                ],
            ];
            if ($contact['is_company']) {
                $this->response['counters'][] = [
                    'name' => 'employees',
                    'value' => (int) $this->getContactModel()->countByField('company_contact_id', $contact_id),
                ];
            }
        }

        // Update list of recently viewed contacts
        $this->getRecentModel()->update($contact_id);
    }

    protected function getContactData(waContact $contact, array $fields = [])
    {
        $result = [];
        if (empty($fields)) {
            // if no fields specified get all fields
            $fields = waContactFields::getInfo($contact['is_company'] ? 'company' : 'person', true);
        }
        $all_columns = crmContact::getAllColumns();
        $phone_formatter = new waContactPhoneFormatter();

        foreach ($fields as $field) {
            $field_object = ifset($all_columns[$field['id']]['field']);

            $field = [
                'values' => [],
                'data'   => [],
             ] + $field;
            switch ($field['id']) {
                case 'address':
                    $data = $contact->get($field['id']);
                    $data = $this->formatAddress($data);
                    break;
                case 'name':
                    $data = waContactNameField::formatName($contact, true);
                    break;
                default:
                    $data = $contact->get($field['id']);
            }
            if (empty($field['multi'])) {
                $data = array($data);
            }
            foreach ($data as $row) {
                if (!$row && $row !== '0') {
                    if ($field_object instanceof waContactCheckboxField) {
                        $row = '';
                    } else {
                        continue;
                    }
                }

                if (!is_array($row)) {
                    $value = (string)$row;
                } else {
                    if (isset($row['value']) && !is_array($row['value'])) {
                        $value = $row['value'];
                    } else {
                        // Don't know how to show unexpected data format :(
                        if (SystemConfig::isDebug()) {
                            $value = json_encode($row);
                        } else {
                            continue;
                        }
                    }
                }

                // Do not show fields with no value set
                if ($value === '' && !($field_object instanceof waContactCheckboxField)) {
                    continue;
                }

                if (!empty($field['options'][$value])) {
                    // Option label for select-based fields
                    $field['values'][] = $field['options'][$value];
                    if ($field_object instanceof waContactRadioSelectField) {
                        $field['type'] = 'Radio';
                    }
                } elseif ($field_object instanceof waContactDateField) {
                    try {
                        $date_formatted = waDateTime::format('humandate', $value, 'server');
                        $field['values'][] = $date_formatted;
                        $row = [
                            'data' => $value,
                            'value' => $date_formatted,
                        ];
                    } catch (waException $ex) {
                        continue;
                    }
                } elseif ($field_object instanceof waContactPhoneField) {
                    $phone_formatted = $phone_formatter->format($value);
                    $field['values'][] = $phone_formatted;
                    $row['data'] = $this->doPhonePrefix($row['value']);
                    $row['value'] = $phone_formatted;
                } elseif ($field_object instanceof waContactBirthdayField) {
                    $field['values'][] = $value;
                } elseif (is_array($row) && !($field_object instanceof waContactCompositeField)) {
                    $field['values'][] = $value;
                    $row['data'] = $row['value'];
                } else {
                    $field['values'][] = $value;
                }

                if (is_array($row)) {
                    if (isset($row['ext']) && isset($field['ext'])) {
                        if (isset($field['ext'][$row['ext']])) {
                            $row['ext_value'] = $field['ext'][$row['ext']];
                        } else {
                            $row['ext_value'] = $row['ext'];
                        }
                    } else {
                        unset($row['ext']);
                    }

                    if (isset($row['data']) && is_array($row['data']) && isset($field['fields'])) {
                        $row_data = [];
                        foreach($field['fields'] as $sub_field) {
                            $sub_field_value = ifset($row, 'data', $sub_field['id'], null);
                            if (!isset($sub_field_value)) {
                                continue;
                            }
                            $sub_field_object = $field_object->getFields($sub_field['id']);
                            if ($sub_field_object instanceof waContactBranchField || $sub_field_object instanceof waContactRadioSelectField) {
                                $sub_field['type'] = 'Radio';
                            }
                            $sub_field_data = [
                                'id' => $sub_field['id'],
                                'name' => trim($sub_field['name']),
                                'type' => $this->normalizeFieldType($sub_field['type']),
                            ];

                            if (ifset($sub_field['options']) && isset($sub_field['options'][$sub_field_value])) {
                                $sub_field_data['value'] = $sub_field['options'][$sub_field_value];
                                $sub_field_data['data'] = $sub_field_value;
                            } elseif ($field_object instanceof waContactAddressField 
                                && ifset($all_columns, $field['id'], 'sub_columns', $sub_field['id'], 'field', null)
                                && $all_columns[$field['id']]['sub_columns'][$sub_field['id']]['field'] instanceof waContactRegionField
                            ) {
                                $region_field = $all_columns[$field['id']]['sub_columns'][$sub_field['id']]['field'];
                                $sub_field_data['value'] = $region_field->format($sub_field_value, null, $row['data']);
                                $sub_field_data['data'] = $sub_field_value;
                            } else {
                                $sub_field_data['value'] = $sub_field_value;
                                $sub_field_data['data'] = $sub_field_value;
                            }
                            $row_data[] = $sub_field_data;
                        }
                        $row['data'] = $row_data;
                    }
                }

                $field['data'][] = $row;
            }

            if (empty($field['values'])) {
                continue;
            }
            $data_field = [
                'id' => $field['id'],
                'name' => trim($field['name']),
                'type' => $this->normalizeFieldType($field['type']),
            ];

            if ($field['multi']) {
                $data_field['values'] = $field['data'];
            } elseif ($field_object instanceof waContactCheckboxField ) {
                $data_field['data'] = $field['values'][0];
                $data_field['value'] = boolval($field['values'][0]) ? _ws('Yes') : _ws('No');
            } elseif ($field['values'][0] === $field['data'][0]) {
                $data_field['data'] = $field['data'][0];
                $data_field['value'] = $field['data'][0];
            } elseif ($field_object instanceof waContactBirthdayField) {
                $data_field['value'] = $field['values'][0];
                $data_field['date_parts'] = $field['data'][0]['data'];
            } elseif ($field_object instanceof waContactCompositeField) {
                $data_field['value'] = $field['values'][0];
                $data_field['data'] = $field['data'][0]['data'];
            } elseif (isset($field['data'][0]['value']) && $field['values'][0] === $field['data'][0]['value']) {
                $data_field['value'] = $field['values'][0];
                if (isset($field['data'][0]['data'])) {
                    $data_field['data'] = $field['data'][0]['data'];
                } else {
                    $data_field['data'] = $field['data'][0];
                }
            } else {
                $data_field['value'] = $field['values'][0];
                $data_field['data'] = $field['data'][0];
            }
            $result[] = $data_field;
        }
        return $result;
    }

    protected function getField($field_id, array $fields) {
        foreach($fields as $field) {
            if ($field['id'] === $field_id) {
                return $field;
            }
        }
        return null;
    }

    protected function getTags($contact_id)
    {
        return $this->prepareTags((new crmTagModel)->getByContact($contact_id));
    }

    protected function getSegments($contact_id)
    {
        return $this->prepareSegments((new crmSegmentModel)->getByContact($contact_id));
    }

    private function formatData($fields)
    {
        $result = [];
        $one_name_field = wa()->getSetting('one_name_field', '', 'crm');
        $continue_fields = (empty($one_name_field) ? ['name'] : ['firstname', 'lastname', 'middlename']);
        foreach ($fields as $_val) {
            $_name = ifset($_val, 'id', '');
            if (in_array($_name, $continue_fields)) {
                continue;
            }
            $value = ifset($_val, 'values', null);
            $value = (is_null($value) ? ifset($_val, 'data', '') : $value);
            if ($_name === 'birthday') {
                $val = [];
                foreach (ifset($_val, 'date_parts', []) as $d_name => $d_val) {
                    $val[] = [
                        'field' => $d_name,
                        'value' => $d_val
                    ];
                }
                $result[] = [
                    'field' => $_name,
                    'value' => $val
                ];
                continue;
            }
            foreach ((array) $value as $_v) {
                if (isset($_v['data']) && is_array($_v['data'])) {
                    $val = [];
                    foreach ($_v['data'] as $_composite) {
                        $val[] = [
                            'field' => ifset($_composite, 'id', ''),
                            'value' => ifset($_composite, 'data', null)
                        ];
                    }
                } else {
                    $val = ifset($_v, 'data', $_v);
                }
                $res = [
                    'field' => $_name,
                    'value' => $val
                ];
                if (isset($_v['ext'])) {
                    $res['ext'] = $_v['ext'];
                }
                $result[] = $res;
            }
        }

        return $result;
    }

    /**
     * @param $contact_id
     * @return bool
     * @throws waException
     */
    private function isPinned($contact_id)
    {
        $recent = $this->getRecentModel()->getByField([
            'user_contact_id' => $this->getUser()->getId(),
            'contact_id'      => $contact_id
        ]);

        return !!ifset($recent, 'is_pinned', 0);
    }

    /**
     * @param $addresses
     * @return array
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
                        $tmp = trim($field->getName()).' '.$tmp;
                    }
                    $value[$f_id] = $tmp;
                }
            }

            $addresses[$key]['value'] = implode(', ', array_filter($value, 'strlen'));
        }

        return $addresses;
    }
}
