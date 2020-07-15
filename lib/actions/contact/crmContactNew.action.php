<?php

/**
 * Dialog HTML to create new contact [deal]
 */
class crmContactNewAction extends crmBackendViewAction
{
    public function execute()
    {
        $phone = waRequest::get('phone', null, waRequest::TYPE_STRING_TRIM);
        $email = waRequest::get('email', null, waRequest::TYPE_STRING_TRIM);
        $is_extended = waRequest::get('extended', null, waRequest::TYPE_STRING_TRIM);
        $extended_fields = $this->getExtendedFields();
        $contact_data = $this->getContactData();
        $call = $this->getCall();
        if ($call) {
            $phone = $call['plugin_client_number'];
        }

        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        $vault_model = new crmVaultModel();
        $vaults = $vault_model->getAvailable();

        // Immidiately show self in list of owners after switching to limited mode
        $me = wa()->getUser();
        $owners = array(
            $me->getId() => array(
                'id' => $me->getId(),
                'name' => $me['name'],
                'photo_url' => $me->getPhoto('20'),
            ),
        );

        $funnels = $fm->getAllFunnels();
        foreach ($funnels as &$f) {
            $f['stages'] = $fsm->getStagesByFunnel($f['id']);
        }
        unset($f);

        $this->view->assign(array(
            'phone'            => $phone,
            'email'            => $email,
            'funnels'          => $funnels,
            'available_funnel' => $fm->getAvailableFunnel(),
            'is_extended'      => $is_extended,
            'contact_data'     => $contact_data,
            'extended_fields'  => $extended_fields,
            'currencies'       => $this->getCurrencies(),
            'currency'         => $this->getConfig()->getCurrency(),
            'call'             => $call,
            'can_edit'         => wa()->getUser()->getRights('crm', 'edit'),
            'segments'         => $this->getSegments(),
            'vaults'           => $vaults,
            'vaults_count'     => count($vaults),
            'owners'           => $owners,
        ));
    }

    protected function getCurrencies()
    {
        $m = new crmCurrencyModel();
        return $m->getAll();
    }

    private function getExtendedFields()
    {
        $field_constructor = new crmFieldConstructor();
        $main_fields = $field_constructor->getPersonMainFields();
        $main_fields = array_fill_keys($main_fields, true);

        $fields = array();
        /**
         * @var waContactField $field
         */
        foreach (waContactFields::getAll($this->getContactType(), true) as $field_id => $field) {
            if (!isset($main_fields[$field_id])) {
                $fields[$field_id] = $field->getInfo();
                $fields[$field_id]['is_composite'] = $field instanceof waContactCompositeField;
                $fields[$field_id]['html'] = $field->getHtml(array(
                    'namespace' => 'contact'
                ));

                if ($fields[$field_id]['is_composite']) {

                    foreach ($fields[$field_id]['fields'] as &$subfield) {
                        $subfield['html'] = '';
                        $subfield['sub_type'] = '';
                    }
                    unset($subfield);

                    foreach ($field->getFields() as $subfield) {
                        if (isset($fields[$field_id]['fields'][$subfield->getId()])) {

                            $subfield_info = &$fields[$field_id]['fields'][$subfield->getId()];

                            $attrs = sprintf('placeholder="%s"', htmlspecialchars($subfield->getName()));

                            $subfield_html = $subfield->getHtml(array(
                                'namespace' => 'contact',
                                'parent' => $field_id,
                                'placeholder' => true
                            ), $attrs);

                            if ($subfield instanceof waContactConditionalField) {
                                $split_pos = strpos($subfield_html, '<script>');
                                if ($split_pos !== false) {
                                    $subfield_html = substr($subfield_html, 0, $split_pos);
                                }
                            }

                            $subfield_info['html'] = $subfield_html;
                            if ($subfield instanceof waContactBranchField) {
                                $subfield_info['sub_type'] = 'Branch';
                            } elseif ($subfield instanceof waContactRadioSelectField) {
                                $subfield_info['sub_type'] = 'Radio';
                            }

                            $subfield_info['class'] = get_class($subfield);

                            unset($subfield_info);
                        }
                    }
                }
            }
        }

        return $fields;
    }

    private function getContactData() {
        return waRequest::get('contact', array(), waRequest::TYPE_ARRAY_TRIM);
    }

    private function getContactType()
    {
        return $this->getRequest()->request('type') === 'company' ? 'company' : 'person';
    }

    private function getCall()
    {
        $call_id = waRequest::get('call', null, waRequest::TYPE_INT);
        if (!$call_id) {
            return null;
        }
        $cm = new crmCallModel();
        $call = $cm->getById($call_id);
        return $call;
    }
    protected function getSegments()
    {
        $segments = $this->getSegmentModel()->getAllSegments(array(
            'type' => 'category',
            'archived' => 0
        ));

        foreach ($segments as &$segment) {
            $segment['checked'] = false;
            $segment['disabled'] = !$this->getCrmRights()->canEditSegment($segment);
        }
        unset($segment);

        $splintered = array(
            'my' => array(),
            'shared' => array(),
        );
        foreach ($segments as $segment) {
            if (!empty($segment['system_id'])) {
                continue;
            }
            if (!$segment['shared']) {
                $splintered['my'][$segment['id']] = $segment;
            } else {
                $splintered['shared'][$segment['id']] = $segment;
            }
        }
        return $splintered;
    }
}
