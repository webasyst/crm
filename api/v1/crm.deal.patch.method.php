<?php

class crmDealPatchMethod extends crmApiAbstractMethod
{
    const ACCEPT_FIELD = [
        'name'          => 'crmDealStringField',
        'description'   => 'crmDealTextField',
        'expected_date' => 'crmDealDateField',
        'amount'        => 'crmDealNumberField',
        'currency_id'   => 'crmDealSelectField'
    ];

    protected $method = self::METHOD_PATCH;
    private $deal_db_fields;
    private $errors = [];
    private $all_columns = [];

    public function execute()
    {
        $fields_data = $this->readBodyAsJson();
        $deal_id = (int) $this->get('id', true);

        if ($deal_id < 1) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        } elseif (empty($fields_data)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameters: %s.', 'field, value'), 400);
        } elseif (!$deal = $this->getDealModel()->getDeal($deal_id, false, true)) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        } elseif (!$this->getCrmRights()->deal($deal)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } elseif (!$this->validateFields($fields_data)) {
            $this->http_status_code = 400;
            $this->response = $this->errors;
            return;
        }

        try {
            $params = ifset($fields_data, 'params', []);
            $fields_data['params'] = $params + ifset($deal, 'params', []);
            $this->getDealModel()->update($deal_id, $fields_data + $deal, $deal);
            unset($fields_data['params']);
            $resp_db = $this->setData($fields_data);
            $resp_params = $this->setData($params);

            $desc = [];
            if (!empty($fields_data['description'])) {
                $sanitizer = new crmHtmlSanitizer();
                $desc[] = [
                    'id'    => 'description_sanitized',
                    'name'  => 'description_sanitized',
                    'type'  => 'text',
                    'value' => $sanitizer->sanitize($fields_data['description']),
                    'data'  => $sanitizer->sanitize($fields_data['description'])
                ];
                $desc[] = [
                    'id'    => 'description_plain',
                    'name'  => 'description_plain',
                    'type'  => 'text',
                    'value' => $sanitizer->toPlainText($fields_data['description']),
                    'data'  => $sanitizer->toPlainText($fields_data['description'])
                ];
            }

            $this->response = array_merge($resp_db, $resp_params, $desc);
        } catch (waDbException $db_exception) {
            throw new waAPIException('error_db', $db_exception->getMessage(), 500);
        }
    }

    private function validateFields(&$data)
    {
        $_error_fields = [];
        $permission_empty = [
            'amount',
            'currency_id',
            'expected_date'
        ];
        $vertical_fields = crmDealFields::getAll();
        for ($i = count($data) - 1; $i >= 0; $i--) {
            $data[$i] += ['value' => null];
            if (
                !array_key_exists('field', $data[$i])
                || empty($data[$i]['field'])
            ) {
                if (
                    empty($vertical_fields[$data[$i]['field']])
                    && (!empty($data[$i]['field']) && !in_array($data[$i]['field'], $permission_empty))
                ) {
                    $_error_fields[] = [
                        'field' => ifset($data, $i, 'field', ''),
                        'value' => ifset($data, $i, 'value', ''),
                        'code'  => 'invalid_param',
                        'description' => sprintf_wp('Missing required parameters: %s.', sprintf_wp('“%s” and “%s”', 'field', 'value'))
                    ];
                    continue;
                }
            }

            $field_name = trim($data[$i]['field']);
            if (isset($data[$field_name])) {
                $_error_fields[] = [
                    'field' => $field_name,
                    'value' => ifset($data, $i, 'value', ''),
                    'code'  => 'field_duplicate',
                    'description' => _w('Duplicate field name.')
                ];
            } else {
                $data[$field_name] = $data[$i]['value'];
            }
            unset($data[$i]);
        }
        if (!empty($_error_fields)) {
            $this->errors = [
                'error' => 'invalid_field',
                'error_description' => _w('Invalid fields found.'),
                'error_fields' => $_error_fields
            ];
            return false;
        }

        $params = [];
        $this->all_columns = $this->acceptField() + $vertical_fields;
        foreach ($data as $_name => $_value) {
            /** @var crmDealField $curr_field */
            $curr_field = ifset($this->all_columns, $_name, null);
            if ($curr_field instanceof crmDealDateField || $curr_field instanceof waContactDateField) {
                $curr_field->setParameter('validators', new waDateIsoValidator);
            }
            if (empty($curr_field)) {
                $_error_fields[] = [
                    'field' => $_name,
                    'value' => $_value,
                    'code'  => 'field_unacceptable',
                    'description' => _w('Unacceptable field.')
                ];
                continue;
            } elseif ($this->acceptField($_name)) {
                /** accept field validate */
               if ($_name === 'expected_date') {
                   $data[$_name] = (empty($_value) ? $_value : date('Y-m-d', strtotime($_value)));
               } elseif ($_name === 'amount') {
                   $data[$_name] = (empty($_value) ? $_value : str_replace(',', '.', $_value));
                   if ($data[$_name] > 99999999999.9999) {
                       // decimal(15, 4) mysql
                       $_error_fields[] = [
                           'field' => 'amount',
                           'value' => $data[$_name],
                           'code'  => 'amount_out',
                           'description' => _w('Out of range value')
                       ];
                   }
                   $data[$_name] = preg_replace('#(\.\d{1,4})\d*#', '$1', $data[$_name]);
               }
            } else {
                /** params validate */
                $params[$_name] = $_value;
                unset($data[$_name]);
            }
            if (!empty($_value) && $err = $curr_field->validate($_value)) {
                $_error_fields[] = [
                    'field' => $_name,
                    'value' => $_value,
                    'code'  => 'invalid_value',
                    'description' => (is_array($err) ? reset($err) : $err)
                ];
            }
        }
        $data['params'] = $params;

        if (!empty($_error_fields)) {
            $this->errors = [
                'error' => 'invalid_field',
                'error_description' => _w('Invalid fields found.'),
                'error_fields' => $_error_fields
            ];
        }

        return empty($this->errors);
    }

    /**
     * Обертка для полей БД сделки
     * @param $name
     * @return array|null
     */
    private function acceptField($name = null)
    {
        if (!isset($this->deal_db_fields)) {
            $this->deal_db_fields = [];
            foreach (self::ACCEPT_FIELD as $_name => $_class) {
                if ($_name === 'currency_id') {
                    $currencies = $this->getCurrencyModel()->getAll('code', true);
                    $currencies = array_keys($currencies);
                    $options['options'] = array_combine($currencies, $currencies);
                    $this->deal_db_fields[$_name] = new $_class($_name, $_name, $options);
                } else {
                    $this->deal_db_fields[$_name] = new $_class($_name, $_name);
                }
            }
        }

        return (empty($name) ? $this->deal_db_fields : ifset($this->deal_db_fields, $name, null));
    }

    private function setData($data = [])
    {
        $result = [];
        foreach ($data as $_name => $_value) {
            /** @var crmDealField $field_obj */
            $field_obj = ifset($this->all_columns, $_name, null);
            $result[] = [
                'id'    => $field_obj->getId(),
                'name'  => $field_obj->getName(),
                'type'  => $this->normalizeFieldType($field_obj->getType()),
                'value' => $this->valueFormat($field_obj, $_value),
                'data'  => (!empty($_value) && $field_obj->getType() == 'Date' ? waDateTime::format('Y-m-d', $_value, 'server') : $_value)
            ];
        }

        return $result;
    }

    private function valueFormat($field_object, $value)
    {
        if ($field_object instanceof crmDealCheckboxField) {
            return empty($value) ? _ws('No') : _ws('Yes');
        }

        if (empty($value)) {
            return null;
        }

        return $field_object->format($value);
    }
}
