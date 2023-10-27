<?php

class crmContactUpdateMethod extends crmApiAbstractMethod
{
    private $data = [];
    private $errors = [];
    private $all_columns = [];
    private $required_fields = [];
    private $contact_id;

    protected $method = self::METHOD_PUT;

    public function execute()
    {
        $this->contact_id = (int) $this->get('id');
        $_json = $this->readBodyAsJson();
        $fields_data = (array) ifempty($_json, []);

        if ($this->contact_id < 1) {
            throw new waAPIException('not_found', 'Contact not found', 404);
        }
        $contact = new crmContact($this->contact_id);
        $is_company = !!$contact->get('is_company');
        if (!$contact->exists()) {
            throw new waAPIException('not_found', 'Contact not found', 404);
        } else if (!$this->getCrmRights()->contactEditable($contact)) {
            throw new waAPIException('forbidden', 'Access denied', 403);
        }

        if (
            !$this->firstValidate($fields_data, $is_company)
            || !$this->secondValidate($fields_data)
        ) {
            $this->http_status_code = 400;
            $this->response = $this->errors;
            return;
        }

        $this->setData($fields_data, $is_company);
        if ($errors = $contact->save($this->data, true)) {
            $this->http_status_code = 400;
            $this->response = [
                'error' => 'update_error',
                'error_description' => 'There were errors updating the contact',
                'error_fields' => $this->errorFormat($errors)
            ];
            return;
        }

        $this->http_status_code = 204;
        $this->response = null;
    }

    /**
     * @param $fields_data array
     * @param $is_company bool
     * @return bool
     * @throws waDbException
     * @throws waException
     */
    private function firstValidate(&$fields_data, $is_company)
    {
        $error_fields = [];
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

        if ($is_company) {
            $this->required_fields = ['company' => true];

        } else {
            $one_name_field = wa()->getSetting('one_name_field', '', 'crm');
            $this->required_fields = (empty($one_name_field) ? array_fill_keys(['firstname', 'middlename', 'lastname'], true) : ['name' => true]);
        }

        if (empty($fields_data)) {
            $this->errors = [
                'error' => 'invalid_param',
                'error_description' => _w('Please fill in the required fields'), //'Required field: '.implode(', ', array_keys($this->required_fields)),
                'error_fields' => []
            ];
            return false;
        }

        /** проверка на заполненность данных */
        foreach ($fields_data as &$field_data) {
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
                'error_description' => _w('Please fill in the required fields'), //'Required parameters: fields name',
                'error_fields' => $error_fields
            ];
            return false;
        }
        unset($field_data, $data_composite);

        $suitcase = [];
        /** проверка на присутствие полей в конфиге */
        foreach ($fields_data as $field_data) {
            if (isset($suitcase[$field_data['field']])) {
                $suitcase[$field_data['field']]['v'][] = $field_data['value'];
            } else {
                $suitcase[$field_data['field']]['v'] = [$field_data['value']];
                $suitcase[$field_data['field']]['is_composite'] = $field_data['is_composite'];
            }

            if (isset($this->required_fields[$field_data['field']]) && !empty($field_data['value'])) {
                if (in_array($field_data['field'], ['firstname', 'middlename', 'lastname'])) {
                    unset(
                        $this->required_fields['firstname'],
                        $this->required_fields['middlename'],
                        $this->required_fields['lastname']
                    );
                } else {
                    unset($this->required_fields[$field_data['field']]);
                }
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
        if (!empty($this->required_fields)) {
            $this->errors = [
                'error' => 'invalid_param',
                'error_description' => _w('Please fill in the required fields'),//'Required field: '.implode(', ', array_keys($this->required_fields)),
                'error_fields' => []
            ];
            return false;
        } elseif (!empty($error_fields)) {
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
                        'description' => 'Several values are set for field'
                    ];
                }, $values['v']);
                $error_fields = array_merge($error_fields, $_errors);
            } else if ($values['is_composite'] && !$curr_field['is_composite'] && $name !== 'birthday') {
                foreach ((array) $values['v'][0] as $_f) {
                    $error_fields[] = [
                        'field' => $name.(empty($_f['field']) ? '' : '.'.$_f['field']),
                        'value' => ifset($_f, 'value', $_f),
                        'code'  => 'not_composite',
                        'description' => 'Field is not composite'
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
                    'description' => 'Field value not integer or negative'
                ];
            }
        }

        if (!empty($error_fields)) {
            $this->errors = [
                'error' => 'error_conformity',
                'error_description' => 'Fields is not multiple or composite',
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
                'error_description' => _w('Please correct errors in the data'),
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

    private function setData($fields_data, $is_company)
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
        if (!$is_company) {
            if (isset($this->data['company_contact_id'])) {
                $company = $this->getContactModel()->getById($this->data['company_contact_id']);
                $this->data['company'] = ifset($company, 'company', '');
                $this->data['company_contact_id'] = ifset($company, 'id', 0);
            } elseif (isset($this->data['company'])) {
                $this->data['company_contact_id'] = $this->getCompanyIdByName($this->data['company']);
            }
        }
        if (empty($this->data['name'])) {
            if ($is_company) {
                $this->data['name'] = ifset($this->data, 'company', '');
            } else {
                $this->data['name'] = implode(' ', [
                    ifset($this->data, 'firstname', ''),
                    ifset($this->data, 'middlename', ''),
                    ifset($this->data, 'lastname', '')
                ]);
            }
        }

        $this->data += array_fill_keys(array_keys($this->all_columns), null);
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

    private function getCompanyIdByName($company_name)
    {
        $companies = $this->getContactModel()->getByField([
            'company'    => $company_name,
            'is_company' => 1
        ], true);
        if (count($companies) === 1) {
            return reset($companies)['id'];
        } else {
            $contact_company = new crmContact();
            $res = $contact_company->save([
                'company'       => $company_name,
                'is_company'    => 1,
                'create_method' => 'api',
                'crm_user_id'   => $this->getUser()->getId()
            ]);

            return ($res === 0 ? $contact_company->getId() : 0);
        }
    }
}
