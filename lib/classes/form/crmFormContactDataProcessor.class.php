<?php

class crmFormContactDataProcessor
{
    /**
     * @var crmForm
     */
    protected $form;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $errors;

    public function __construct(crmForm $form, $options = array())
    {
        $this->form = $form;
        $this->options = $options;
        $this->options['strategy_type'] = (string)ifset($this->options['strategy_type']);
        $this->options['user'] = ifset($this->options['user']);
    }

    protected function getUser()
    {
        return $this->options['user'] instanceof waUser ? $this->options['user'] : wa()->getUser();
    }

    protected function getStrategyType()
    {
        return $this->options['strategy_type'];
    }

    protected function isStrategyForAuthorizedUser()
    {
        return $this->getStrategyType() === 'is_authorized_user';
    }

    protected function isSignUpStrategy()
    {
        return $this->getStrategyType() === 'sign_up';
    }

    protected function isBasicStrategy()
    {
        $type = $this->getStrategyType();
        return $type === 'basic' || !$type;
    }

    protected function getFormFields()
    {
        return crmFormProcessor::getFormFields($this->form, $this->getStrategyType());
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function process($data)
    {
        if ($this->isStrategyForAuthorizedUser()) {
            return $this->processAuthorizedUser($data);
        } else {
            return $this->processNotAuthorizedUser($data);
        }
    }

    protected function processAuthorizedUser($data)
    {
        $update_data = array();

        $fields = $this->getFormFields();

        foreach ($fields as $field) {

            if (!isset($data[$field['id']])) {
                continue;
            }

            $field_form_type = null;
            $fld = crmFormConstructor::getFieldObject($field['id'], $field_form_type);
            if ($field_form_type !== crmFormConstructor::FIELD_TYPE_CONTACT || !$fld) {
                continue;
            }

            $editable = crmFormRenderer::isEditableField(
                true,
                $fld,
                ifset($field['required']),
                $this->getUser()->get($fld->getId())
            );

            if (!$editable) {
                continue;
            }

            if (!$fld->isMulti()) {
                $update_data[$field['id']] = $data[$field['id']];
            } else {
                $update_data[$field['id']] = $this->normalizeFieldValue($fld->getId(), $data[$field['id']]);
            }
        }

        $contact = new crmContact($this->getUser()->getId());

        if (isset($data['email'])) {
            $contact->setProperty('email_to_send', $data['email']);
        }

        if ($update_data) {
            $this->updateContact($contact, $update_data);
        }

        return array(
            'contact' => $contact,
        );
    }

    protected function updateContact(crmContact $contact, $update_data)
    {
        if (!$update_data) {
            return;
        }

        foreach ($update_data as $field_id => &$update_value) {
            $contact_values = $contact->get($field_id);
            if (is_array($contact_values)) {
                foreach ($contact_values as $contact_value) {
                    if (!$this->isContactValueChanged($field_id, $contact_value, $update_value)) {
                        unset($update_data[$field_id]);
                    }
                }
            }
        }
        unset($update_value);
        
        if (!$update_data) {
            return;
        }

        foreach ($update_data as $field_id => $update_value) {
            $contact->add($field_id, $update_value);
        }

        $contact->save();
    }

    protected function normalizeFieldValue($field_id, $value)
    {
        $sub_fields = $this->getSubFieldsOfCompositeField($field_id);
        if (!is_array($sub_fields)) {
            return is_string($value) ? trim($value) : $value;
        }
        $normalized = array();
        foreach ($sub_fields as $sub_field_id) {
            $val = ifset($value[$sub_field_id]);
            $val = is_string($val) ? trim($val) : $val;
            $normalized[$sub_field_id] = $val;
        }
        return $normalized;
    }

    protected function getSubFieldsOfCompositeField($field_id)
    {
        static $sub_fields = array();
        if (array_key_exists($field_id, $sub_fields)) {
            return $sub_fields[$field_id];
        }
        $field = waContactFields::get($field_id);
        if (!($field instanceof waContactCompositeField)) {
            return $sub_fields[$field_id] = null;
        }

        // collect displayable sub-fields
        foreach ($field->getFields() as $sub_field_id => $sub_field) {
            if (!($sub_field instanceof waContactHiddenField)) {
                $sub_fields[$field_id][] = $sub_field_id;
            }
        }
        return $sub_fields[$field_id];
    }

    protected function isContactValueChanged($field_id, $contact_value, $update_value)
    {
        if (isset($contact_value['data'])) {
            return $this->isContactValueOfCompositeFieldChanged($field_id, $contact_value['data'], $update_value);
        } elseif (isset($contact_value['value'])) {
            $value = $this->typecastContactValue($field_id, $update_value);
            return $value != $contact_value['value'];
        } else {
            return false;
        }
    }

    protected function isContactValueOfCompositeFieldChanged($field_id, $contact_value, $update_value)
    {
        $field = waContactFields::get($field_id);
        if (!($field instanceof waContactCompositeField)) {
            return false;
        }
        $contact_value = $this->normalizeFieldValue($field_id, $contact_value);
        $value = $this->typecastContactValue($field_id, $update_value);
        $value = $this->normalizeFieldValue($field_id, $value);
        return $value != $contact_value;
    }

    protected function typecastContactValue($field_id, $value)
    {
        static $dummy;
        if ($dummy === null) {
            $dummy = new crmContact();
        }
        $dummy->set($field_id, $value);
        $values = $dummy->get($field_id);
        if (empty($values)) {
            return '';
        }
        $value = reset($values);
        if (isset($value['data'])) {
            return $value['data'];
        } elseif (isset($value['value'])) {
            return $value['value'];
        } else {
            return '';
        }
    }

    protected function processNotAuthorizedUser($data)
    {
        // creating contact
        $contact = $this->createContact($data);
        if (!$contact) {
            return false;
        }

        // add to segments
        $this->addContactToSegments($contact);

        // sigUp if need
        if ($this->isSignUpStrategy()) {
            wa()->getAuth()->auth($contact);
        }

        // log result
        if ($this->isSignUpStrategy()) {
            $this->logSignUp($contact, $this->form->getId());
        } else {
            $this->logCreateContact($contact, $this->form->getId());
        }

        return array(
            'contact' => $contact
        );
    }

    protected function addContactToSegments(crmContact $contact)
    {
        $segments = $this->form->getSource()->getParam('segments');
        if (is_array($segments) || is_scalar($segments)) {
            $segments = (array)$segments;
        }

        if (!$segments || !is_array($segments)) {
            // just in case check form param (backward compatibility)
            $segments = $this->form->getParam('segments');
            if (is_array($segments) || is_scalar($segments)) {
                $segments = (array)$segments;
            }
            if (!$segments || !is_array($segments)) {
                return;
            }
        }

        $wcc = new waContactCategoriesModel();
        foreach ($segments as $segment) {
            $wcc->add($contact['id'], $segment);
        }
    }

    /**
     * @param $data
     * @return crmContact|null
     */
    protected function createContact($data)
    {
        $contact_data = $this->prepareDataForContactSaving($data);

        // try save contact
        $contact = new crmContact();
        $errors = $contact->save($contact_data, true);
        if ($errors) {
            $this->errors = $errors;
            return null;
        }

        return $contact;
    }

    protected function prepareDataForContactSaving($data)
    {
        foreach ($this->getFormFields() as $field) {
            if (!crmFormConstructor::isFieldOfContact($field) && isset($data[$field['uid']])) {
                unset($data[$field['uid']]);
            }
        }

        if (empty($data['locale'])) {
            $locale = $this->form->getSource()->getParam('locale');
            $data['locale'] = $locale ? $locale : wa()->getLocale();
        }

        $responsible_contact_id = $this->form->getSource()->getNormalizedResponsibleContactId();
        if ($responsible_contact_id) {
            $data['crm_user_id'] = $responsible_contact_id;
        }

        $data['create_app_id'] = 'crm';
        $data['create_contact_id'] = 0;
        $data['create_method'] = $this->form->getType() === crmForm::TYPE_SIGN_UP ? 'signup' : 'form';
        $data['create_ip'] = waRequest::getIp();
        $data['create_user_agent'] = waRequest::getUserAgent();
        return $data;
    }

    /**
     * @param crmContact $contact
     * @param int $form_id
     */
    protected function logCreateContact($contact, $form_id)
    {
        $log_params = json_encode(array(
            'form_id' => $form_id
        ));
        $this->logAction('form_lead', $log_params, null, $contact->getId());
    }

    /**
     * @param crmContact $contact
     * @param int $form_id
     */
    protected function logSignUp($contact, $form_id)
    {
        $log_params = json_encode(array(
            'form_id' => $form_id
        ));
        $this->logAction('signup', $log_params, null, $contact->getId());
    }

    protected function logAction($action, $params = null, $subject_contact_id = null, $contact_id = null)
    {
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        return $log_model->add($action, $params, $subject_contact_id, $contact_id);
    }
}
