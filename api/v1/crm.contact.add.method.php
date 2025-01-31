<?php

class crmContactAddMethod extends crmApiAbstractMethod
{
    private $contact;
    private $data = [];
    private $errors = [];
    private $all_columns = [];
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $fields_data = (array) ifempty($_json, []);

        $this->contact = new crmContact();
        if (!$this->validate($fields_data) || $errors = $this->contact->validate($this->data)) {
            $this->http_status_code = 400;
            $this->response = [
                'error' => 'invalid_param',
                'error_description' => _w('Please correct errors in the data.'),
                'error_fields' => array_merge(
                    $this->errors,
                    (empty($errors) ? [] : $this->errorFormat($errors))
                )
            ];
            return;
        }

        if ($errors = $this->contact->save($this->data, true)) {
            $this->http_status_code = 400;
            $this->response = [
                'error' => 'save_error',
                'error_description' => _w('Contact saving error.'),
                'error_fields' => $this->errorFormat($errors)
            ];
            return;
        } elseif ($phones = $this->contact->get('phone')) {
            $numbers = array_column($phones, 'value');
            if (!empty($numbers)) {
                $this->getCallModel()->exec("
                    UPDATE crm_call
                    SET client_contact_id = i:cont_id
                    WHERE client_contact_id IS NULL AND plugin_client_number IN (s:numbers)
                ", [
                    'cont_id' => $this->contact->getId(),
                    'numbers' => $numbers
                ]);
            }
        }

        $this->http_status_code = 201;
        $this->response = $this->contact->getId();
    }

    private function validate($fields_data)
    {
        $is_company = false;
        foreach ($fields_data as $_field_data) {
            if ($_field_data['field'] == 'is_company' && $_field_data['value']) {
                $is_company = true;
                break;
            }
        }
        if ($is_company) {
            $required = ['company'];
        } else {
            $one_name_field = wa()->getSetting('one_name_field', '', 'crm');
            $required = (empty($one_name_field) ? ['firstname', 'middlename', 'lastname'] : ['name']);
        }

        /** проверка на заполненность данных */
        for ($i = count($fields_data) - 1; $i >= 0; $i--) {
            $field_data = &$fields_data[$i];
            $field_data += array_fill_keys(['field', 'value', 'ext'], '');
            $field_data['is_composite'] = (is_array($field_data['value']));

            if (!empty($required) && in_array($field_data['field'], $required) && trim((string) $field_data['value']) !== '') {
                unset($required);
            }
            if ($field_data['is_composite']) {
                for ($j = count($field_data['value']) - 1; $j >= 0; $j--) {
                    $field_data['value'][$j] += array_fill_keys(['field', 'value'], '');
                    if (!$this->fieldValidate($field_data['value'][$j], $this->errors)) {
                        // удаляем из поля с ошибками из списка
                        unset($fields_data[$i]['value'][$j]);
                    }
                }
                $fields_data[$i]['value'] = array_values($fields_data[$i]['value']);
            } else {
                if (!$this->fieldValidate($field_data, $this->errors)) {
                    // удаляем из поля с ошибками из списка
                    unset($fields_data[$i]);
                }
            }
        }
        if (!empty($required)) {
            foreach ($required as $_req) {
                if ($_req !== 'middlename') {
                    $this->errors[] = [
                        'field' => $_req,
                        'value' => '',
                        'code' => 'invalid_param',
                        'description' => _w('This field is required')
                    ];
                }
            }
        }
        $fields_data = array_values($fields_data);
        unset($field_data, $i, $j);

        $suitcase = [];
        $this->all_columns = crmContact::getAllColumns('all_api');
        $this->all_columns += [
            'is_company' => [
                'is_multi'     => false,
                'is_composite' => false,
                'field'        => new waContactCheckboxField('is_company', 'is_company')
            ],
            'company_contact_id' => [
                'is_multi'     => false,
                'is_composite' => false,
                'field'        => new waContactTextField('company_contact_id', 'company_contact_id')
            ]
        ];

        /** проверка на присутствие полей в конфиге */
        for ($i = count($fields_data) - 1; $i >= 0; $i--) {
            $field_data = &$fields_data[$i];
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
                    $this->errors = array_merge($this->errors, $_errors);
                    unset($fields_data[$i]);
                } else {
                    $this->errors[] = [
                        'field' => $field_data['field'],
                        'value' => $field_data['value'],
                        'code'  => 'unknown_field',
                        'description' => _w('Unknown field'),
                    ];
                    unset($fields_data[$i]);
                }
                continue;
            } else if ($field_data['is_composite'] && $curr_field['is_composite'] && !empty($curr_field['sub_columns'])) {
                /** проверяем subcolumns */
                for ($j = count($field_data['value']) - 1; $j >= 0; $j--) {
                    if (!isset($curr_field['sub_columns'][$field_data['value'][$j]['field']])) {
                        $this->errors[] = [
                            'field' => $field_data['field'].'.'.$field_data['value'][$j]['field'],
                            'value' => $field_data['value'][$j]['value'],
                            'code'  => 'unknown_subcolumns',
                            'description' => _w('Unknown field'),
                        ];
                        unset($field_data['value'][$j]);
                    }
                }
                $field_data['value'] = array_values($field_data['value']);
                continue;
            }
            if (isset($suitcase[$field_data['field']])) {
                $suitcase[$field_data['field']]['v'][] = $field_data['value'];
            } else {
                $suitcase[$field_data['field']]['v'] = [$field_data['value']];
                $suitcase[$field_data['field']]['is_composite'] = $field_data['is_composite'];
            }
        }
        $fields_data = array_values($fields_data);

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
                $this->errors = array_merge($this->errors, $_errors);
            } else if ($values['is_composite'] !== $curr_field['is_composite'] && $name !== 'birthday') {
                foreach ((array) $values['v'][0] as $_f) {
                    $this->errors[] = [
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
                $this->errors[] = [
                    'field' => 'company_contact_id',
                    'value' => $company_contact_id,
                    'code' => 'invalid_value',
                    'description' => _w('Field value is not an integer or is negative.')
                ];
            }
        }

        foreach ($fields_data as $_field) {
            /** @var waContactField $curr_field */
            $curr_field_obj = ifset($this->all_columns, $_field['field'], 'field', []);
            if ($curr_field_obj instanceof waContactDateField) {
                $curr_field_obj->setParameter('validators', new waDateIsoValidator);
                if ($description = $curr_field_obj->validate($_field['value'])) {
                    $this->errors[] = [
                        'field' => $_field['field'],
                        'value' => $_field['value'],
                        'code' => 'invalid_value',
                        'description' => $description
                    ];
                }
            }
        }

        if (empty($this->errors)) {
            $this->setData($fields_data, $is_company);
            if (!empty($one_name_field)) {
                $this->contact->set('name', ifempty($this->data, 'name', ''));
            }
        }

        return empty($this->errors);
    }

    private function errorFormat($errors)
    {
        $error_fields = [];
        foreach ($this->data as $_name => $_data) {
            $description = null;
            if (isset($errors[$_name])) {
                if (is_array($_data)) {
                    foreach ($_data as $k => $_val) {
                        if (isset($errors[$_name][$k])) {
                            $value = ifempty($_val, 'value', '');
                            $description = $errors[$_name][$k];
                        } elseif (isset($errors[$_name])) {
                            $value = ifempty($_val, '');
                            $description = $errors[$_name];
                        }
                    }
                } else {
                    $value = $_data;
                    $description = $errors[$_name];
                }
                unset($errors[$_name]);
            }
            if ($description) {
                $error_fields[] = [
                    'field' => $_name,
                    'value' => $value,
                    'code'  => 'invalid_value',
                    'description' => $description
                ];
            }
        }
        if ($errors) {
            foreach ($errors as $_n => $_err) {
                $error_fields[] = [
                    'field' => $_n,
                    'value' => '',
                    'code'  => 'invalid_value',
                    'description' => $_err
                ];
            }
        }

        return $error_fields;
    }

    private function fieldValidate(&$data, &$error_fields)
    {
        $data['field'] = trim((string) $data['field']);
        $data['value'] = trim((string) $data['value']);
        if ($data['field'] === '') {
            $error_fields[] = [
                'field' => $data['field'],
                'value' => $data['value'],
                'code'  => 'invalid_param',
                'description' => sprintf_wp('Missing required parameter: “%s”.', _w('field identifier'))
            ];
            return false;
        }

        return true;
    }

    private function setData($fields_data, $is_company)
    {
        foreach ($fields_data as $_data) {
            if (!isset($_data['value'])) {
                continue;
            }
            $field_is_multi = ifset($this->all_columns, $_data['field'], 'is_multi', false);
            $field_object = ifset($this->all_columns, $_data['field'], 'field', null);
            if ($_data['is_composite']) {
                $composite_value = ($field_object->hasExt() ? ['ext' => $_data['ext']] : []);
                foreach ($_data['value'] as $_val) {
                    if (isset($_val['value'])) {
                        $composite_value[$_val['field']] = $_val['value'];
                    }
                }
                if ($_data['field'] === 'birthday') {
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

        $this->data['crm_user_id'] = $this->getUser()->getId();
        if ($is_company) {
            $this->data['name'] = ifset($this->data, 'company', '');
        } else {
            $one_name_field = wa()->getSetting('one_name_field', '', 'crm');
            if ($one_name_field) {
                $name = explode(' ', $this->data['name']);
                if (count($name) > 1) {
                    $name_order = waContactNameField::getNameOrder();
                    foreach ($name_order as $_part) {
                        $_name = (string) array_shift($name);
                        $this->data[$_part] = $_name;
                    }
                } else {
                    $this->data['firstname'] = (string) reset($name);
                }
            } else {
                $this->data['name'] = waContactNameField::formatName($this->data, true);
            }
            if (isset($this->data['company'])) {
                $this->data['company_contact_id'] = $this->getCompanyIdByName($this->data['company']);
            } elseif (isset($this->data['company_contact_id'])) {
                $this->data['company'] = $this->getCompanyNameById($this->data['company_contact_id']);
            }
        }
        if (!empty($this->data['is_company'])) {
            $this->data['is_company'] = 1;
        }
    }

    private function getCompanyIdByName($company_name)
    {
        $companies = $this->getContactModel()->getByField([
            'company'    => $company_name,
            'is_company' => 1
        ], true);
        if (count($companies) === 1) {
            return reset($companies)['id'];
        } else {
            $crm_contact = new crmContact;
            $res = $crm_contact->save([
                'company'       => $company_name,
                'is_company'    => 1,
                'create_method' => 'api',
                'crm_user_id'   => $this->getUser()->getId()
            ]);

            return ($res === 0 ? $crm_contact->getId() : 0);
        }
    }

    private function getCompanyNameById($company_id)
    {
        $company = $this->getContactModel()->getById($company_id);

        return ifset($company, 'name', '');
    }
}
