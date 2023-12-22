<?php

class crmDealAddMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $deal_name = trim(ifset($_json, 'name', null));
        $deal_stage_id = trim(ifset($_json, 'stage_id', null));
        $contact_id = trim(ifset($_json, 'contact_id', null));

        $deal_data = [
            'name'            => $deal_name,
            'stage_id'        => $deal_stage_id,
            'contact_id'      => $contact_id,
            'funnel_id'       => null,
            'params'          => ifset($_json, 'fields', []),
            'description'     => trim(ifset($_json, 'description', '')),
            'expected_date'   => trim(ifset($_json, 'expected_date', '')),
            'amount'          => trim(ifset($_json, 'amount', '')),
            'currency_id'     => trim(ifset($_json, 'currency_id', '')),
            'contact_label'   => trim(ifset($_json, 'contact_label', '')),
            'user_contact_id' => trim(ifset($_json, 'user_contact_id', ''))
        ];

        $this->http_status_code = 400;
        if ($error_fields = $this->checkRequired($deal_name, $deal_stage_id, $contact_id)) {
            $this->response = [
                'error' => 'required_parameter',
                'error_description' => _w('Please correct errors in the data.'),
                'error_fields' => $error_fields
            ];
            return;
        } elseif ($error_fields = $this->validate($deal_data)) {
            $this->response = [
                'error' => 'error_validate',
                'error_description' => _w('Please correct errors in the data.'),
                'error_fields' => $error_fields
            ];
            return;
        } elseif ($error_fields = $this->validateFields($deal_data)) {
            $this->response = [
                'error' => 'error_validate',
                'error_description' => _w('Please correct errors in the data.'),
                'error_fields' => $error_fields
            ];
            return;
        }

        $deal_id = $this->getDealModel()->add($deal_data);
        $this->http_status_code = 201;
        $this->response = $deal_id;
        if (!empty($deal_data['contact_label'])) {
            $this->getDealModel()->updateParticipant(
                $deal_id,
                $deal_data['contact_id'],
                'contact_id',
                $deal_data['contact_label']
            );
        }
    }

    private function checkRequired($deal_name, $deal_stage_id, $contact_id)
    {
        $error_fields = [];
        if ($deal_name === '') {
            $error_fields[] = [
                'field' => 'name',
                'value' => '',
                'code'  => 'name',
                'description' => sprintf_wp('Missing required parameter: “%s”.', 'name'),
            ];
        }

        if ($deal_stage_id == '') {
            $error_fields[] = [
                'field' => 'stage_id',
                'value' => '',
                'code'  => 'stage_id',
                'description' => sprintf_wp('Missing required parameter: “%s”.', 'stage_id'),
            ];
        } elseif (!is_numeric($deal_stage_id) || $deal_stage_id < 1) {
            $error_fields[] = [
                'field' => 'stage_id',
                'value' => $deal_stage_id,
                'code'  => 'stage_id',
                'description' => _w('Deal stage not found.'),
            ];
        }

        if ($contact_id == '') {
            $error_fields[] = [
                'field' => 'contact_id',
                'value' => '',
                'code'  => 'contact_id',
                'description' => sprintf_wp('Missing required parameter: “%s”.', 'contact_id'),
            ];
        } elseif (!is_numeric($contact_id) || $contact_id < 1) {
            $error_fields[] = [
                'field' => 'contact_id',
                'value' => $contact_id,
                'code'  => 'contact_id',
                'description' => _w('Contact not found'),
            ];
        }

        return $error_fields;
    }

    private function validate(&$deal_data)
    {
        $error_fields = [];

        if (!(new crmContact($deal_data['contact_id']))->exists()) {
            throw new waAPIException('invalid_request', _w('Invalid contact identifier.'), 400);
        } elseif (!$this->getCrmRights()->contact($deal_data['contact_id'])) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $fsm = new crmFunnelStageModel();
        $stage = $fsm->getById($deal_data['stage_id']);
        if (!$stage || !$stage['funnel_id']) {
            $error_fields[] = [
                'field' => 'stage_id',
                'value' => $deal_data['stage_id'],
                'code'  => 'stage_id',
                'description' => _w('Deal stage not found.')
            ];
        } elseif (!$this->getCrmRights()->funnel($stage['funnel_id'])) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        $deal_data['funnel_id'] = $stage['funnel_id'];

        if (empty($deal_data['user_contact_id'])) {
            $deal_data['user_contact_id'] = $this->getUser()->getId();
        } elseif ($deal_data['user_contact_id'] != $this->getUser()->getId()) {
            if ($deal_data['user_contact_id'] < 1 || !(new crmContact($deal_data['user_contact_id']))->exists()) {
                throw new waAPIException('invalid_request', _w('Invalid user identifier.'), 400);
            }
            $crm_rights = new crmRights(['contact' => $deal_data['user_contact_id']]);
            if (!$crm_rights->contact($deal_data['contact_id'])) {
                $error_fields[] = [
                    'field' => 'user_contact_id',
                    'value' => $deal_data['user_contact_id'],
                    'code'  => 'user_contact_id',
                    'description' => _w('User does not have access to contact.')
                ];
            } elseif (!$crm_rights->funnel($deal_data['funnel_id'])) {
                $error_fields[] = [
                    'field' => 'user_contact_id',
                    'value' => $deal_data['user_contact_id'],
                    'code'  => 'user_contact_id',
                    'description' => _w('User does not have access to funnel.')
                ];
            }
        }

        if (!empty($deal_data['expected_date']) && !strtotime($deal_data['expected_date'])) {
            $error_fields[] = [
                'field' => 'expected_date',
                'value' => $deal_data['expected_date'],
                'code'  => 'expected_date',
                'description' => _w('Invalid date.')
            ];
        }

        if (!empty($deal_data['amount'])) {
            $deal_data['amount'] = str_replace(',', '.', $deal_data['amount']);
            if ($deal_data['amount'] > 99999999999.9999) {
                // decimal(15, 4) mysql
                $error_fields[] = [
                    'field' => 'amount',
                    'value' => $deal_data['amount'],
                    'code'  => 'amount_out',
                    'description' => _w('Out of range value')
                ];
            }
        }

        if (!empty($deal_data['currency_id'])) {
            $currencies = $this->getCurrencyModel()->getAll('code', true);
            if (empty($currencies[$deal_data['currency_id']])) {
                $error_fields[] = [
                    'field' => 'currency_id',
                    'value' => $deal_data['currency_id'],
                    'code'  => 'currency_id',
                    'description' => _w('Unknown currency.')
                ];
            }
        }

        return $error_fields;
    }

    private function validateFields(&$deal_data)
    {
        $data_params  = [];
        $error_fields = [];
        if (empty($deal_data['params'])) {
            return $error_fields;
        }

        $fields = crmDealFields::getAll();
        foreach ($deal_data['params'] as $params) {
            if (!isset($params['field'], $params['value'])) {
                $error_fields[] = [
                    'field' => ifset($params, 'field', ''),
                    'value' => ifset($params, 'value', ''),
                    'code'  => 'fields',
                    'description' => sprintf_wp('Missing required parameters: %s.', sprintf_wp('“%s” and “%s”', 'field', 'value'))
                ];
                continue;
            }

            if (!isset($fields[$params['field']])) {
                $error_fields[] = [
                    'field' => $params['field'],
                    'value' => $params['value'],
                    'code'  => 'fields',
                    'description' => _w('Unknown field name.')
                ];
                continue;
            }

            $field = $fields[$params['field']];
            if ($er = $field->validate($params['value'])) {
                $error_fields[] = [
                    'field' => $params['field'],
                    'value' => $params['value'],
                    'code'  => 'fields',
                    'description' => _w('Invalid field value.') . ' ' . reset($er)
                ];
            }
            $data_params[$params['field']] = $params['value'];
        }

        $deal_data['params'] = $data_params;

        return $error_fields;
    }
}
