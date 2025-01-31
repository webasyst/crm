<?php

class crmFieldListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $scope = $this->get('scope', true);
        if (!in_array($scope, ['person', 'company', 'deal', 'contact'])) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid “%s” value.', 'scope'), 400);
        }

        $response = [];
        $one_name_field = wa()->getSetting('one_name_field', '', 'crm');
        if (empty($one_name_field) || $scope == 'company') {
            $columns_unset = [
                'name'
            ];
        } else {
            $columns_unset = [
                'firstname',
                'middlename',
                'lastname'
            ];
        }
        if ($scope === 'deal') {
            $columns = crmDealFields::getAll();
        } elseif ($scope === 'contact') {
            $columns = waContactFields::getAll() + waContactFields::getAll('company');
        } else {
            $columns = waContactFields::getAll($scope);
        }
        $columns = array_diff_key($columns, array_fill_keys($columns_unset, ''));

        /** @var waContactField $column_object */
        foreach ($columns as $column_id => $column_object) {
            $column_info = $column_object->getInfo();
            $type = $this->normalizeFieldType($column_object);
            if ($type === 'hidden' || ($scope != 'deal' && $column_object->isHidden())) {
                continue;
            }
            $is_multi = ifset($column_info, 'multi', false);
            $_response = [
                'id'          => (string) $column_id,
                'name'        => trim(ifset($column_info, 'name', '')),
                'type'        => $type,
                'is_multi'    => $is_multi,
                'is_unique'   => ifset($column_info, 'unique', false),
                'is_required' => ifset($column_info, 'required', false)
            ];

            if (in_array($type, ['select', 'radio'])) {
                $options = ifset($column_info, 'options', []);
                foreach ($options as $opt => $option) {
                    $_response['option_values'][] = [
                        'id'    => (string) $opt,
                        'value' => (string) $option
                    ];
                }
                unset($options, $opt, $option);
            }
            if ($is_multi) {
                $exts = ifset($column_info, 'ext', []);
                foreach ($exts as $key_ext => $ext) {
                    $_response['ext'][] = [
                        'id'    => $key_ext,
                        'value' => $ext
                    ];
                }
            }
            if ($type === 'composite') {
                $sub_fields = ifset($column_info, 'fields', []);
                foreach ($sub_fields as $sub_name => $sub_field) {
                    $sub_column_object = $column_object->getFields($sub_name);
                    $sub_type = $this->normalizeFieldType($sub_column_object);
                    if ($sub_column_object->isHidden() || $sub_type === 'hidden') {
                        continue;
                    }
                    $fields = [
                        'id'   => (string) $sub_name,
                        'name' => trim(ifset($sub_field, 'name', '')),
                        'type' => $sub_type
                    ];
                    if ($o_order = ifset($sub_field, 'oOrder', [])) {
                        $o_index = array_search('', $o_order, true);
                        if ($o_index === false) {
                            $o_order = [];
                        } else {
                            array_splice($o_order, $o_index);
                        }
                    }
                    if ($options = ifempty($sub_field, 'options', [])) {
                        unset($options['']);
                        $_options = array_intersect_key($options, array_flip($o_order));
                        $_options += (empty($o_order) ? [] : ['' => '']) + $options;
                        foreach ($_options as $opt => $option) {
                            $fields['option_values'][] = [
                                'id'    => (string) $opt,
                                'value' => (string) $option
                            ];
                        }
                    }
                    $_response['fields'][] = $fields;
                }
                if ($column_id === 'birthday') {
                    $_response['fields'] = [
                        ['id' => 'year', 'name' => _w('Year'), 'type' => 'number']
                    ];
                    /** @var waContactBirthdayField $column_object */
                    $months = $column_object->getMonths();
                    $_response['fields'][] = [
                        'id'            => 'month',
                        'name'          => _w('Month'),
                        'type'          => 'select',
                        'option_values' => array_map(function ($_month, $_id) {
                            return ['id' => (string) $_id, 'value' => (string) $_month];
                        }, $months, array_keys($months))
                    ];

                    $days = array_map('strval', $column_object->getDays());
                    $_response['fields'][] = [
                        'id'            => 'day',
                        'name'          => _w('Day'),
                        'type'          => 'select',
                        'option_values' => array_map(function ($_day) {
                            return ['id' => (string) $_day, 'value' => (string) $_day];
                        }, $days)
                    ];
                }
            }
            $response[] = $_response;
        }

        $this->response = $response;
    }

    /**
     * @param waContactField $column_object
     * @return string
     */
    protected function normalizeFieldType($column_object)
    {
        $column_type = $column_object->getType();
        $type = parent::normalizeFieldType($column_type);
        if ($type === 'string') {
            if ($column_object->getParameter('input_height') > 1) {
                $type = 'text';
            }
        } elseif ($type === 'address') {
            $type = 'composite';
        } else if (
            $column_object instanceof waContactRadioSelectField
            || $column_object instanceof crmDealRadioField
            || $column_object instanceof waContactBranchField
        ) {
            $type = 'radio';
        } else if ($column_object instanceof waContactSelectField || $column_object instanceof crmDealSelectField) {
            $type = 'select';
        }

        return $type;
    }
}
