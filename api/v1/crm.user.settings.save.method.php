<?php

class crmUserSettingsSaveMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_PATCH;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $fields_data = (array) ifempty($_json, []);

        if (empty($fields_data)) {
            throw new waAPIException(
                'empty_param',
                sprintf_wp(
                    'Missing required parameter: %s.',
                    sprintf(
                        '%s, %s, %s or %s',
                        sprintf_wp('“%s”', 'contact_list_columns'),
                        sprintf_wp('“%s”', 'contact_list_sort'),
                        sprintf_wp('“%s”', 'deal_list_filter'),
                        sprintf_wp('“%s”', 'deal_list_sort')
                    )
                ),
                400
            );
        }

        $contact_list_columns = ifset($fields_data, 'contact_list_columns', []);
        $contact_list_sort = ifset($fields_data, 'contact_list_sort', []);
        $deal_list_filter = ifset($fields_data, 'deal_list_filter', []);
        $deal_list_sort = ifset($fields_data, 'deal_list_sort', []);
        if ($errors = array_merge(
            $this->validateColumns($contact_list_columns),
            $this->validateSort($contact_list_sort),
            $this->validateDealFilter($deal_list_filter),
            $this->validateDealSort($deal_list_sort),
        )) {
            throw new waAPIException('empty_param', implode(' ', $errors), 400);
        }

        try {
            $this->saveSettings(
                $contact_list_columns,
                $contact_list_sort,
                $deal_list_filter,
                $deal_list_sort
            );
        } catch (waDbException $db_exception) {
            throw new waAPIException('error_db', $db_exception->getMessage(), 500);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }

    private function validateColumns($data)
    {
        $errors = [];
        if (!empty($data)) {
            foreach ($data as $_data) {
                if (!isset($_data['field'])) {
                    $errors[_w('Empty field.')] = 1;
                } elseif (!isset($_data['width'])) {
                    $errors[_w('Empty width.')] = 1;
                } elseif (!in_array($_data['width'], ['s', 'm', 'l'])) {
                    $errors[sprintf_wp('Unknown width value: %s.', $_data['width'])] = 1;
                }
            }
        }

        return array_keys($errors);
    }

    private function validateSort($data)
    {
        $errors = [];
        if (!empty($data) && !isset($data['field'])) {
            $errors[] = _w('Empty contacts sorting field.');
        }

        return $errors;
    }

    private function validateDealFilter($data)
    {
        $errors = [];
        $required_fields = [
            'funnel_id',
            'stage_id',
            'tag_id',
            'user_id'
        ];
        if (!empty($data)) {
            $diff = array_diff($required_fields, array_keys($data));
            if (!empty($diff)) {
                $errors[] = sprintf_wp('Empty required fields: %s.', implode(', ', $diff));
            } elseif (!is_numeric($data['stage_id']) && !in_array(strtolower($data['stage_id']), ['', 'null', 'won', 'lost'])) {
                $errors[] = sprintf_wp('Unknown “%s” value.', 'stage_id');
            }
        }

        return $errors;
    }

    private function validateDealSort($data)
    {
        $errors = [];
        $deal_sort_enum = [
            'stage_id',
            'create_datetime',
            'reminder_datetime',
            'name',
            'amount',
            'user_name',
            'last_action'
        ];
        if (!empty($data)) {
            if (!isset($data['field'])) {
                $errors[] = _w('Empty deal sorting field.');
            } elseif (!in_array($data['field'], $deal_sort_enum)) {
                $errors[] = _w('Empty deal sorting field.');
            }
        }

        return $errors;
    }

    private function saveSettings($contact_list_columns, $contact_list_sort, $deal_list_filter, $deal_list_sort)
    {
        $settings = [];
        $csm = new waContactSettingsModel();

        if (!empty($contact_list_columns)) {
            $_list_columns = [];
            foreach ($contact_list_columns as $sort => $_column) {
                $_list_columns[$_column['field']] = [
                    'sort'  => $sort,
                    'width' => $_column['width'],
                    'off'   => 0
                ];
            }
            $settings['contact_list_columns'] = json_encode($_list_columns);
        }
        if (!empty($contact_list_sort)) {
            $_list_sort = $csm->getOne($this->getUser()->getId(), $this->getAppId(), 'contacts_action_params');
            $_list_sort = empty($_list_sort) ? [] : waUtils::jsonDecode($_list_sort, true);
            $_list_sort['raw_sort'] = [
                $contact_list_sort['field'],
                ($contact_list_sort['asc'] ? 'ASC' : 'DESC')
            ];
            $settings['contacts_action_params'] = json_encode($_list_sort);
        }
        if (!empty($deal_list_filter)) {
            foreach ($deal_list_filter as $_name => &$_value) {
                if ($_name === 'stage_id' && in_array(strtolower($_value), ['won', 'lost'])) {
                    continue;
                }
                $_value = (int) $_value;
                if (empty($_value) || $_value < 0) {
                    $_value = '';
                }
            }
            $settings += [
                'deal_funnel_id' => $deal_list_filter['funnel_id'],
                'deal_stage_id'  => $deal_list_filter['stage_id'],
                'deal_tag_id'    => $deal_list_filter['tag_id'],
                'deal_user_id'   => $deal_list_filter['user_id']
            ];
        }
        if (!empty($deal_list_sort)) {
            $settings['deal_list_sort'] = $deal_list_sort['field'].' '.($deal_list_sort['asc'] ? '1' : '0');
        }

        return $settings ? $csm->set(
            $this->getUser()->getId(),
            $this->getAppId(),
            $settings
        ) : false;
    }
}
