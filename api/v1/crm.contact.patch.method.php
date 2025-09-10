<?php

class crmContactPatchMethod extends crmApiAbstractMethod
{
    private $data = [];
    private $errors = [];
    private $all_columns = [];
    protected $method = self::METHOD_PATCH;
    private $contact_id;

    public function execute()
    {
        $this->contact_id = (int) $this->get('id');
        $_json = $this->readBodyAsJson();
        $fields_data = (array) ifempty($_json, []);
        $contact = $this->getContact($this->contact_id);
        if (
            !$this->firstValidate($fields_data)
            || !$this->secondValidate($fields_data)
        ) {
            $this->http_status_code = 400;
            $this->response = $this->errors;
            return;
        }
        $this->setData($fields_data);
        if ($errors = $contact->save($this->data, true)) {
            $this->http_status_code = 400;
            $this->response = [
                'error' => 'patch_error',
                'error_description' => _w('Contact updating error.'),
                'error_fields' => $this->errorFormat($errors)
            ];
            return;
        }
        
        $this->getLogModel()->log('contact_edit', $this->contact_id, $this->contact_id);

        $this->setResponse($contact);
    }

    /**
     * @param $contact_id
     * @return waContact
     */
    private function getContact($contact_id)
    {
        if ($contact_id < 1) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        }
        $contact = new waContact($contact_id);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } else if (!$this->getCrmRights()->contactEditable($contact)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        return $contact;
    }

    /**
     * @param $contact waContact
     * @param $fields_data
     * @return false
     */
    private function firstValidate(&$fields_data)
    {
        if (!is_array($fields_data)) {
            $this->errors = [
                'error' => 'invalid_param',
                'error_description' => _w('Please fill in the required fields'),
                'error_fields' => []
            ];
            return false;
        }

        $fields_data = array_filter($fields_data, function ($field_data) {
            return is_array($field_data) && array_key_exists('field', $field_data);
        });

        if (empty($fields_data)) {
            $this->errors = [
                'error' => 'invalid_param',
                'error_description' => _w('Please fill in the required fields'),
                'error_fields' => []
            ];
            return false;
        }

        $error_fields = [];
        /** проверка на заполненность данных */
        foreach ($fields_data as &$field_data) {
            if (!array_key_exists('value', $field_data)) {
                $error_fields[] = [
                    'field' => ifset($field_data, 'field', ''),
                    'value' => '',
                    'code'  => 'invalid_param',
                    'description' => _w('This field is required')
                ];
                continue;
            }
            $field_data += array_fill_keys(['field', 'value', 'ext'], '');
            $field_data['is_composite'] = (is_array($field_data['value']));

            if ($field_data['is_composite']) {
                foreach ($field_data['value'] as &$data_composite) {
                    $data_composite += array_fill_keys(['field', 'value'], '');
                    $this->fieldValidate($field_data, $data_composite, $error_fields);
                }
            } else {
                $this->fieldValidate($field_data, [], $error_fields);
            }
        }
        if (!empty($error_fields)) {
            $this->errors = [
                'error' => 'invalid_param',
                'error_description' => _w('Please fill in the required fields'),
                'error_fields' => $error_fields
            ];
            return false;
        }
        unset($field_data, $data_composite);

        $suitcase = [];
        $this->all_columns = crmContact::getAllColumns('all');
        $this->all_columns += [
            'company_contact_id' => [
                'is_multi'     => false,
                'is_composite' => false,
                'field'        => new waContactTextField('company_contact_id', 'company_contact_id')
            ]
        ];

        /** проверка на присутствие полей в конфиге */
        foreach ($fields_data as $field_data) {
            if (isset($suitcase[$field_data['field']])) {
                $suitcase[$field_data['field']]['v'][] = $field_data['value'];
            } else {
                $suitcase[$field_data['field']]['v'] = [$field_data['value']];
                $suitcase[$field_data['field']]['is_composite'] = $field_data['is_composite'];
            }
            $curr_field = ifset($this->all_columns, $field_data['field'], []);
            if (empty($curr_field)) {
                if ($field_data['is_composite']) {
                    $_errors = array_map(function ($v) use ($field_data) {
                        return [
                            'field' => $field_data['field'].'.'.$v['field'],
                            'value' => $v['value'],
                            'code'  => 'unknown_field',
                            'description' => _w('Unknown field'),
                        ];
                    }, $field_data['value']);
                    $error_fields = array_merge($error_fields, $_errors);
                } else {
                    $error_fields[] = [
                        'field' => $field_data['field'],
                        'value' => $field_data['value'],
                        'code'  => 'unknown_field',
                        'description' => _w('Unknown field'),
                    ];
                }
            } else if ($field_data['is_composite'] && $curr_field['is_composite'] && !empty($curr_field['sub_columns'])) {
                /** проверяем subcolumns */
                foreach ((array) $field_data['value'] as $_val) {
                    if (!isset($curr_field['sub_columns'][$_val['field']])) {
                        $error_fields[] = [
                            'field' => $field_data['field'].'.'.$_val['field'],
                            'value' => $_val['value'],
                            'code'  => 'unknown_subcolumns',
                            'description' => _w('Unknown field'),
                        ];
                    }
                }
            }
        }
        if (!empty($error_fields)) {
            $this->errors = [
                'error' => 'unknown_columns',
                'error_description' => _w('The data contains unknown fields'),
                'error_fields' => $error_fields
            ];
            return false;
        }

        /** проверка на соответствие множественности значений, композитности поля */
        foreach ($suitcase as $name => $values) {
            $curr_field = ifset($this->all_columns, $name, []);
            if (count($values['v']) > 1 && !$curr_field['is_multi']) {
                $_errors = array_map(function ($v) use ($name) {
                    return [
                        'field' => $name,
                        'value' => $v,
                        'code'  => 'not_multiple',
                        'description' => _w('Multiple values set for a single-value field.')
                    ];
                }, $values['v']);
                $error_fields = array_merge($error_fields, $_errors);
            } else if ($values['is_composite'] && !$curr_field['is_composite'] && $name !== 'birthday') {
                foreach ((array) $values['v'][0] as $_f) {
                    $error_fields[] = [
                        'field' => $name.(empty($_f['field']) ? '' : '.'.$_f['field']),
                        'value' => ifset($_f, 'value', $_f),
                        'code'  => 'not_composite',
                        'description' => _w('Not a composite field.')
                    ];
                }
            }
        }
        if ($company_contact_id = ifset($suitcase, 'company_contact_id', 'v', 0, 0)) {
            if (!is_numeric($company_contact_id) || $company_contact_id < 1) {
                $error_fields[] = [
                    'field' => 'company_contact_id',
                    'value' => $company_contact_id,
                    'code' => 'invalid_value',
                    'description' => _w('Field value is not an integer or is negative.')
                ];
            }
        }

        if (!empty($error_fields)) {
            $this->errors = [
                'error' => 'error_conformity',
                'error_description' => _w('Unsupported multiple values provided for a single-value field.'),
                'error_fields' => $error_fields
            ];
            return false;
        }

        return true;
    }

    private function secondValidate($fields_data)
    {
        $error_fields = [];
        foreach ($fields_data as $_data) {
            /** @var waContactField $field_object */
            $field_object = ifset($this->all_columns, $_data['field'], 'field', null);
            $is_composite = ifset($this->all_columns, $_data['field'], 'is_composite', false);
            $description  = null;
            if ($field_object instanceof waContactDateField) {
                $field_object->setParameter('validators', new waDateIsoValidator);
            }
            if ($is_composite || $field_object->isMulti()) {
                if ($description = $field_object->validate((array) $_data['value'], $this->contact_id)) {
                    $description = reset($description);
                }
            } elseif ($field_object->getId() === 'birthday') {
                $birthday = [];
                foreach ($_data['value'] as $_val) {
                    $birthday[$_val['field']] = $_val['value'];
                }
                $_data['value'] = implode('-', $birthday);
                $birthday = $birthday + array_fill_keys($field_object->getParts(), null);
                $description = $field_object->validate(['value' => $birthday], $this->contact_id);
            } else {
                $description = $field_object->validate($_data['value'], $this->contact_id);
            }
            if ($description) {
                $error_fields[] = [
                    'field' => $_data['field'],
                    'value' => $_data['value'],
                    'code'  => 'invalid_value',
                    'description' => $description
                ];
            }
        }
        if (!empty($error_fields)) {
            $this->errors = [
                'error' => 'invalid_param',
                'error_description' => _w('Please correct errors in the data.'),
                'error_fields' => $error_fields
            ];
            return false;
        }

        return true;
    }

    private function fieldValidate(&$data, $data_composite, &$error_fields)
    {
        $data['field'] = trim((string) $data['field']);
        if (empty($data_composite)) {
            $data['value'] = trim((string) $data['value']);
            if ($data['field'] === '') {
                $error_fields[] = [
                    'field' => $data['field'],
                    'value' => $data['value'],
                    'code'  => 'invalid_param',
                    'description' => _w('This field is required')
                ];
            }
        } else {
            $data_composite['field'] = trim((string) $data_composite['field']);
            $data_composite['value'] = trim((string) $data_composite['value']);
            if ($data_composite['field'] === '') {
                $error_fields[] = [
                    'field' => $data['field'].'.'.$data_composite['field'],
                    'value' => $data_composite['value'],
                    'code'  => 'invalid_param',
                    'description' => _w('This field is required')
                ];
            }
        }
    }

    private function setData($fields_data)
    {
        foreach ($fields_data as $_data) {
            $field_is_multi = ifset($this->all_columns, $_data['field'], 'is_multi', false);
            $field_object = ifset($this->all_columns, $_data['field'], 'field', null);
            if ($_data['is_composite']) {
                $composite_value = ($field_object->hasExt() ? ['ext' => $_data['ext']] : []);
                foreach ($_data['value'] as $_val) {
                    $composite_value[$_val['field']] = $_val['value'];
                }
                if ($field_object->getId() === 'birthday') {
                    $this->data[$_data['field']] = $composite_value;
                } else {
                    $this->data[$_data['field']][] = $composite_value;
                }
            } else if ($field_is_multi) {
                $value = ['value' => $_data['value']] + ($field_object->hasExt() ? ['ext' => $_data['ext']] : []);
                if (isset($this->data[$_data['field']])) {
                    $this->data[$_data['field']][] = $value;
                } else {
                    $this->data[$_data['field']] = [$value];
                }
            } else {
                $this->data[$_data['field']] = $_data['value'];
            }
        }
        if (isset($this->data['company_contact_id'])) {
            $company = $this->getContactModel()->getById($this->data['company_contact_id']);
            $this->data['company'] = ifset($company, 'company', '');
            $this->data['company_contact_id'] = ifset($company, 'id', 0);
        }
    }

    private function setResponse($contact)
    {
        $this->response = [];
        if (isset($this->data['name'])) {
            $this->data += array_fill_keys(['firstname', 'middlename', 'lastname'], '');
        } else if (
            isset($this->data['firstname'])
            || isset($this->data['lastname'])
            || isset($this->data['middlename'])
        ) {
            $this->data += ['name' => ''];
        }
        foreach (array_keys($this->data) as $_name) {
            $field_object = ifset($this->all_columns, $_name, 'field', null);
            $is_multi = ifset($this->all_columns, $_name, 'is_multi', false);
            $is_composite = ifset($this->all_columns, $_name, 'is_composite', false);
            $contact_value = $contact->get($_name);

            if ($is_multi || $is_composite) {
                $values = [];
                $field_info = $field_object->getInfo();
                $ext = ifset($field_info, 'ext', []);

                foreach ((array) $contact_value as $contact_val) {
                    if ($is_composite) {
                        $data = [];
                        $sub_columns = ifset($contact_val, 'data', []);
                        foreach ($sub_columns as $sub_name => $sub_val) {
                            $sub_field_object = ifset($this->all_columns, $_name, 'sub_columns', $sub_name, 'field', null);
                            if (!$sub_field_object) {
                                continue;
                            }
                            $data[] = [
                                'id'    => $sub_name,
                                'name'  => $sub_field_object->getName(),
                                'type'  => $this->normalizeFieldType($sub_field_object->getType()),
                                'value' => $this->valueFormat($sub_field_object, $sub_val),
                                'data'  => $sub_val
                            ];
                        }
                    } else {
                        $data = ifset($contact_val, 'value', '');
                        $contact_val['value'] = $this->valueFormat($field_object, $contact_val);
                    }
                    $values[] = [
                        'value'     => ifset($contact_val, 'value', ''),
                        'data'      => $data,
                        'ext'       => ifset($contact_val, 'ext', ''),
                        'ext_value' => ifset($ext, $contact_val['ext'], $contact_val['ext']),
                        'status'    => ifset($contact_val, 'status', null),
                    ];
                }
                $this->response[] = [
                    'id'     => $field_object->getId(),
                    'name'   => $field_object->getName(),
                    'type'   => $this->normalizeFieldType($field_object->getType()),
                    'values' => $values
                ];
            } else {
                $this->response[] = [
                    'id'    => $field_object->getId(),
                    'name'  => $field_object->getName(),
                    'type'  => $this->normalizeFieldType($field_object->getType()),
                    'value' => $this->valueFormat($field_object, $contact_value),
                    'data'  => (string) ifset($contact_value, 'value', $contact_value)
                ];
            }
        }
    }

    private function valueFormat($field_object, $value)
    {
        if (empty($value)) {
            return null;
        }
        switch (get_class($field_object)) {
            case 'waContactCheckboxField':
                $result = ($value ? _ws('Yes') : _ws('No'));
                break;
            case 'waContactDateField':
                $result = waDateTime::format('humandate', $value, 'server');
                break;
            case 'waContactTimezoneField':
                try {
                    $result = $field_object->getOptions($value);
                } catch (Exception $ex) {
                    $result = null;
                }
                break;
            case 'waContactPhoneField':
                $result = (new waContactPhoneFormatter())->format($value);
                break;
            case 'waContactSelectField':
                $result = (new waContactSelectFormatter)->format($value);
                break;
            case 'waContactLocaleField':
                $result = (new waContactLocaleFormatter)->format($value);
                break;
            case 'waContactCountryField':
            case 'waContactRegionField':
                $result = $field_object->format($value, 'value');
                break;
            default:
                $result = ifempty($value, 'value', $value);
        }

        return $result;
    }

    private function errorFormat($errors)
    {
        $error_fields = [];
        foreach ($errors as $_field_name => $_error) {
            if (!isset($this->data[$_field_name])) {
                continue;
            }
            if (is_array($_error)) {
                foreach ($_error as $key => $_val) {
                    $error_fields[] = [
                        'field' => $_field_name,
                        'value' => ifset($this->data, $_field_name, $key, ''),
                        'code'  => 'field_invalid',
                        'description' => ifset($_error, $key, '')
                    ];
                }
            } else {
                $error_fields[] = [
                    'field' => $_field_name,
                    'value' => ifset($this->data, $_field_name, ''),
                    'code'  => 'field_invalid',
                    'description' => ifset($_error, '')
                ];
            }
        }

        return $error_fields;
    }
}
