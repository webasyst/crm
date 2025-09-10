<?php

class crmFrontendFormHeadlessController extends waJsonController
{
    public function execute()
    {
        $id = $this->getId();
        if (empty($id)) {
            $this->notFound();
        }

        if ($origin = waRequest::server('HTTP_ORIGIN')) {
            $this->getResponse()
                ->addHeader('Access-Control-Allow-Origin', $origin)
                ->addHeader('Access-Control-Allow-Headers', 'Content-Type')
                ->addHeader('Vary', 'Origin');
        }

        $this->getResponse()->addHeader('Content-type', 'application/json');

        $form = new crmForm($id);
        if (!$form->exists()) {
            $this->notFound(_w('Form not found'));
            return;
        }

        $this->getResponse()->sendHeaders();

        $form_info = $form->getInfo();
        $fields = waContactFields::getAll() + waContactFields::getAll('company') + crmDealFields::getAll();

        //wa_dump($form);

        $this->response = [
            'params' => [
                'action_url' => wa()->getRouteUrl('crm/frontend/formSubmit', []),
                'max_width' => intval(ifset($form_info['params']['max_width'], ifset($form_info['params']['width'], 600))),
                'fields_space' => intval(ifset($form_info['params']['fields_space'], 16)),
                'caption_space' => intval(ifset($form_info['params']['caption_space'], 8)),
                'caption_width' => intval(ifset($form_info['params']['caption_width'], 20)),
                'after_submit' => $form_info['params']['after_submit'],
                'html_after_submit' => $form_info['params']['html_after_submit'],
            ],
            'fields' => array_map(function ($field) use ($fields) {
                $result = [
                    'id' => $field['id'],
                    'uid' => $field['uid'],
                    'required' => boolval($field['required']),
                    'captionplace' => $field['captionplace'],
                    'caption' => $field['caption'],
                    'placeholder' => $field['placeholder'],
                ];
                if ($field['id'] === '!agreement_checkbox') {
                    $result['label'] = $field['html_label'];
                    $result['caption'] = '';
                    $result['required'] = true;
                    $result['type'] = 'checkbox';
                }
                if ($field['id'] === '!deal_description') {
                    $result['redactor'] = boolval($field['redactor']);
                }
                if ($field['id'] === '!paragraph') {
                    $result['text'] = $field['text'];
                    $result['caption'] = '';
                }

                if (!isset($fields[$field['id']])) {
                    return $result;
                }

                $field_object = $fields[$field['id']];
                $field_info = $field_object->getInfo();
                $type = $this->normalizeFieldType($field_object);

                $result['type'] = $type;

                if (in_array($type, ['select', 'radio'])) {
                    $options = ifset($field_info, 'options', []);
                    foreach ($options as $opt => $option) {
                        $result['option_values'][] = [
                            'id'    => (string) $opt,
                            'value' => (string) $option
                        ];
                    }
                    unset($options, $opt, $option);
                }

                if ($type === 'checkbox') {
                    $result['label'] = $result['caption'];
                    $result['caption'] = '';
                }

                if ($type === 'composite') {
                    if (isset($field['subfield_captionplace'])) {
                        $result['subfield_captionplace'] = $field['subfield_captionplace'];
                    }
                    $result['fields'] = [];
                    $sub_fields = ifset($field_info, 'fields', []);
                    foreach ($sub_fields as $sub_name => $sub_field) {
                        $sub_column_object = $field_object->getFields($sub_name);
                        $sub_type = $this->normalizeFieldType($sub_column_object);
                        if ($sub_column_object->isHidden() || $sub_type === 'hidden') {
                            continue;
                        }
                        $res_sub_field = [
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
                                $res_sub_field['option_values'][] = [
                                    'id'    => (string) $opt,
                                    'value' => (string) $option
                                ];
                            }
                        }
                        $result['fields'][] = $res_sub_field;
                    }
                    if ($field['id'] === 'birthday') {
                        $result['fields'] = [
                            ['id' => 'year', 'name' => _w('Year'), 'type' => 'number']
                        ];
                        /** @var waContactBirthdayField $field_object */
                        $months = $field_object->getMonths();
                        $result['fields'][] = [
                            'id'            => 'month',
                            'name'          => _w('Month'),
                            'type'          => 'select',
                            'option_values' => array_map(function ($_month, $_id) {
                                return ['id' => (string) $_id, 'value' => (string) $_month];
                            }, $months, array_keys($months))
                        ];

                        $days = array_map('strval', $field_object->getDays());
                        $result['fields'][] = [
                            'id'            => 'day',
                            'name'          => _w('Day'),
                            'type'          => 'select',
                            'option_values' => array_map(function ($_day) {
                                return ['id' => (string) $_day, 'value' => (string) $_day];
                            }, $days)
                        ];
                    }
                }

                return $result;
            }, $form_info['params']['fields']),
                'button' => json_decode(ifset($info, 'params', 'button', '{}'), true) ?: [
                'captionplace' => empty($info['params']['button_caption']) ? 'left' : 'none',
                'width' => 'auto',
                'caption' => ifset($info, 'params', 'button_caption', _w('Submit')),
            ],
        ];

        if (!empty($form_info['params']['antibot_honey_pot'])) {
            $this->response['fields'][] = [
                'id'   => $form_info['params']['antibot_honey_pot']['empty_field_name'],
                'type' => 'hidden',
                'value' => '',
            ];
            $this->response['fields'][] = [
                'id'   => $form_info['params']['antibot_honey_pot']['filled_field_name'],
                'type' => 'hidden',
                'value' => $form_info['params']['antibot_honey_pot']['filled_field_value'],
            ];
        }
    }

    protected function getId()
    {
        return (int) $this->getRequest()->param('id', null, waRequest::TYPE_INT);
    }

    protected function notFound()
    {
        $this->getResponse()->setStatus(404);
        $this->errors = [_w('Form not found')];
    }

    protected function normalizeFieldTypeBase($raw_type)
    {
        switch ($raw_type) {
            case 'Number':
                return 'number';
            case 'Select':
            case 'Country':
                return 'select';
            case 'Radio':
                return 'radio';
            case 'Checkbox':
                return 'checkbox';
            case 'Hidden':
                return 'hidden';
            case 'Text':
                return 'text';
            case 'Address':
                return 'address';
            case 'Birthday':
            case 'Composite':
                return 'composite';
            case 'Date':
                return 'date';
            default:
                return 'string';
        }
    }

    protected function normalizeFieldType($column_object)
    {
        $column_type = $column_object->getType();
        $type = $this->normalizeFieldTypeBase($column_type);
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
