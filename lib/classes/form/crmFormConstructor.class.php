<?php

class crmFormConstructor
{
    const FIELD_TYPE_CONTACT = 'contact';
    const FIELD_TYPE_DEAL = 'deal';
    const FIELD_TYPE_SPECIAL = 'special';

    /**
     * @var int
     */
    protected $id;

    /**
     * @var crmForm
     */
    protected $form;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array[string]array
     */
    protected $available_fields;

    /**
     * @var array[string]array
     */
    protected $checked_fields;

    /**
     * @var array[string]bool Map for quick check is field is allowed
     */
    protected static $allowed_field_ids;

    protected static $captcha_is_invisible;

    protected static $captcha_type;

    /**
     * crmFormConstructor constructor.
     * @param int $id
     * @param array
     */
    public function __construct($id, $options = array())
    {
        $this->id = (int)$id;
        $this->options = $options;
        $this->form = new crmForm($this->id);
    }

    /**
     * @return crmForm
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @return int
     */
    public function getFormId()
    {
        return $this->getForm()->getId();
    }

    /**
     * @param $data
     * @param bool $delete_old_params
     * @return bool
     */
    public function saveForm($data, $delete_old_params = true)
    {
        $data['params']['antibot_honey_pot'] = [
            'empty_field_name'   => '!f' . waUtils::getRandomHexString(6),
            'filled_field_name'  => '!f' . waUtils::getRandomHexString(6),
            'filled_field_value' => waUtils::getRandomHexString(32),
        ];
        $this->getForm()->save($data, $delete_old_params);
        $success = $this->getForm()->getId() > 0;
        $this->available_fields = null;
        $this->checked_fields = null;

        return $success;
    }

    public function deleteForm()
    {
        $this->getForm()->delete();
    }

    public function formExists()
    {
        return $this->getForm()->exists();
    }

    public function getFormInfo()
    {
        $info = $this->getForm()->getInfo();
        $info['params']['fields'] = $this->getCheckedFields();
        $info['params']['button'] = json_decode(ifset($info, 'params', 'button', '{}'), true) ?: [
            'captionplace' => empty($info['params']['button_caption']) ? 'left' : 'none',
            'width' => 'auto',
            'caption' => ifset($info, 'params', 'button_caption', _w('Submit')),
        ];
        $info['params']['widget_domains'] = json_decode(ifset($info, 'params', 'widget_domains', '[]'), true);
        $info['params']['widget_path'] = json_decode(ifset($info, 'params', 'widget_path', '{}'), true);
        return $info;
    }

    protected function obtainAvailableFields()
    {
        $available_fields = array_merge(
            $this->getContactFields(),
            $this->getSpecialFields()
        );

        foreach ($this->getDealFields() as $field_id => $field) {
            if (!isset($available_fields[$field_id])) {
                $available_fields[$field_id] = $field;
            }
        }

        // throw off not-allowed fields
        $available_fields = array_filter($available_fields, function ($field) {
            return isset($field['id']) && self::isFieldAllowed($field['id']);
        });
        
        $form = $this->getForm();

        $field_ids = array_column($form->getFields(), 'id');

        $available_fields = array_map(function ($field) use ($field_ids) {
            $field['checked'] = in_array($field['id'], $field_ids) ? 1 : 0;
            return $field;
        }, $available_fields);

        $available_fields = array_reduce(
            $available_fields,
            function ($result, $field) {
                $result[strval($field['id'])] = $field;
                return $result;
            }, 
        []);

        return $available_fields;
    }

    public function getCheckedFields()
    {
        if ($this->checked_fields !== null) {
            return $this->checked_fields;
        }

        $fields = $this->getForm()->getFields();

        $checked_counters = array();
        $checked_fields = array();
        $available_fields = $this->obtainAvailableFields();
        foreach ($fields as $field) {
            if (!isset($field['id']) || !isset($available_fields[$field['id']])) {
                continue;
            }
            $a_field = $available_fields[$field['id']];
            if ($a_field['checked'] <= 0) {
                continue;
            }

            $checked_counters[$field['id']] = (int)ifset($checked_counters[$field['id']]);
            $checked_counters[$field['id']] += 1;

            $field_info = $this->prepareCheckedFieldInfo($a_field, $field);
            $html = $this->renderField($field_info);
            $field_info['html'] = $html;
            $field_info['checked'] = $checked_counters[$field['id']];
            $checked_fields[] = $field_info;
        }

        return $this->checked_fields = $checked_fields;
    }

    protected function prepareCheckedFieldInfo($a_field, $field)
    {
        $field_info = array_merge(
            $a_field,
            $field
        );
        $field_info['field'] = ifset($a_field['field']);
        $field_info['checked'] = (int)ifset($a_field['checked']);
        $field_info['form_field_type'] = (string)ifset($a_field['form_field_type']);

        $suffix = '';
        if ($field_info['checked'] > 0) {
            $suffix = '_' . ($field_info['checked']);
        }
        $field_info['uid'] = $field_info['id'] . $suffix;

        return $field_info;
    }

    public function getAvailableFields()
    {
        if ($this->available_fields !== null) {
            return $this->available_fields;
        }

        $available_fields = $this->obtainAvailableFields();
        foreach ($available_fields as $field_id => &$field_info) {
            $field_info['html'] = $this->renderField($field_info);
        }
        unset($field_info);

        return $this->available_fields = $available_fields;
    }

    protected function renderField($info)
    {
        $field_type = $info['form_field_type'];
        if (isset($info['field']) && $info['field'] instanceof waContactField) {
            $contact_field_type = strtolower($info['field']->getType());
            if ($info['field'] instanceof waContactRadioSelectField || $info['field'] instanceof waContactBranchField) {
                $template_path = "templates/form/fields/constructor/field.contact.radio.html";
            } elseif ($info['field'] instanceof waContactSelectField) {
                $template_path = "templates/form/fields/constructor/field.contact.select.html";
            } elseif ($info['field'] instanceof waContactTextField) {
                $template_path = "templates/form/fields/constructor/field.contact.textarea.html";
            } elseif ($info['field'] instanceof waContactCompositeField || $info['field'] instanceof waContactAddressField) {
                $subfields = $info['field']->getFields();
                $info['subfields'] = [];
                foreach ($subfields as $subfield_id => $subfield) {
                    $subfield_info = [
                        'id' => $subfield_id,
                        'type' => $subfield->getType(),
                        'name' => $subfield->getName(),
                        'placeholder' => $subfield->getName(),
                        'is_multi' => false,
                        'form_field_type' => self::FIELD_TYPE_CONTACT,
                        'field' => $subfield,
                    ];
                    $subfield_info['html'] = $this->renderField($subfield_info);
                    $info['subfields'][$subfield_id] = $subfield_info;
                }
                $template_path = "templates/form/fields/constructor/field.contact.composite.html";
            } else {
                $template_path = "templates/form/fields/constructor/field.{$field_type}.{$contact_field_type}.html";
            }
        } else {
            $field_id = ltrim($info['id'], '!');
            $template_path = "templates/form/fields/constructor/field.{$field_type}.{$field_id}.html";
        }

        $template_path = wa()->getAppPath($template_path, 'crm');
        if (file_exists($template_path)) {
            $html = $this->renderFieldByTemplate($template_path, $info);
        } else if (isset($info['field']) && ($info['field'] instanceof waContactField || $info['field'] instanceof crmDealField)) {
            $html = $this->renderFieldByObject($info);
        } else {
            $html = '';
        }
        return $html;
    }

    /**
     * @param array $info
     * @return string
     */
    protected function renderFieldByObject($info)
    {
        $params = $info;
        unset($params['field']);

        $classes = array(
            "crm-{$info['id']}-input"
        );
        if (!empty($params['required'])) {
            $classes[] = 'crm-required-input';
        }

        $params['attrs'] = array(
            'class="' . join(' ', $classes) . '"',
            'disabled="disabled"'
        );

        if (!empty($params['placeholder'])) {
            $params['attrs'][] = 'placeholder="' . htmlspecialchars($params['placeholder']) . '"';
        }

        $params['attrs'] = join(' ', $params['attrs']);

        $field_renderer = new crmFormFieldRenderer($info['field'], $params);
        return $field_renderer->render();
    }

    protected function renderFieldByTemplate($template, $info)
    {
        if ($info['id'] === '!captcha') {
            return $this->renderCaptcha($template, $info);
        }
        return $this->renderFieldTemplate($template, $info);
    }

    protected function renderCaptcha($template, $info)
    {
        $info['captcha_type'] = self::getCaptchaType();
        $info['caption'] = $info['captcha_type'] === 'waPHPCaptcha' ? 
                _w('Enter the code from the image') :
                _w('Antibot protection');

        $info['placeholder_need'] = $info['captcha_type'] === 'waPHPCaptcha';

        $info['is_invisible'] = self::getCaptchaIsInvisible();
        $info['captionplace'] = $info['is_invisible'] ? 'none' : 'left';                

        $img_url = 'img/waCaptchaImg.png';
        if ($info['captcha_type'] === 'waReCaptcha') {
            $img_url = 'img/reCaptchaEN.png';
            if (wa()->getLocale() === 'ru_RU') {
                $img_url = 'img/reCaptchaRU.png';
            }
        } elseif ($info['captcha_type'] === 'waSmartCaptcha') {
            $img_url = 'img/smartCaptcha.png';
        }
        $info['img_url'] = $img_url;

        return $this->renderFieldTemplate($template, $info);
    }

    protected function renderFieldTemplate($template, $assign = array())
    {
        $params = $assign;
        $params['template_path'] = $template;
        $renderer = new crmFormFieldRenderer($assign, $params);
        return $renderer->render();
    }

    public static function isFieldAllowed($field_id)
    {
        $field_ids = self::getAllAllowedFieldIds();
        return isset($field_ids[$field_id]);
    }

    public static function isFieldOfContact($field) {
        $field_id = $field;
        $form_field_type = null;
        if (is_array($field)) {
            $form_field_type = ifset($field['form_field_type']);
            $field_id = $field['id'];
        }
        if (!self::isFieldAllowed($field_id)) {
            return false;
        }
        if ($form_field_type !== null) {
            return $form_field_type === self::FIELD_TYPE_CONTACT;
        }
        $contact_fields = waContactFields::getAll('enabled', true);
        return isset($contact_fields[$field_id]) || $field_id === 'password';
    }

    public static function isFieldOfDeal($field, $exclusive = true)
    {
        $field_id = $field;
        $form_field_type = null;
        if (is_array($field)) {
            $form_field_type = ifset($field['form_field_type']);
            $field_id = $field['id'];
        }
        if (!self::isFieldAllowed($field_id)) {
            return false;
        }
        if ($form_field_type !== null) {
            return $form_field_type === self::FIELD_TYPE_DEAL;
        }

        $deal_fields = crmDealFields::getAll('enabled');
        if (!$exclusive) {
            return isset($deal_fields[$field_id]);
        }
        $contact_fields = waContactFields::getAll('enabled', true);
        return !isset($contact_fields[$field_id]) && isset($deal_fields[$field_id]);
    }

    public static function isFieldOfContactAndDeal($field)
    {
        $field_id = $field;
        if (is_array($field)) {
            $field_id = $field['id'];
        }
        if (!self::isFieldAllowed($field_id)) {
            return false;
        }
        $contact_fields = waContactFields::getAll('enabled', true);
        $deal_fields = crmDealFields::getAll('enabled');
        return isset($contact_fields[$field_id]) && isset($deal_fields[$field_id]);
    }

    public static function isSpecialField($field)
    {
        $field_id = $field;
        $form_field_type = null;
        if (is_array($field)) {
            $form_field_type = ifset($field['form_field_type']);
            $field_id = $field['id'];
        }
        if (!self::isFieldAllowed($field_id)) {
            return false;
        }
        if ($form_field_type !== null) {
            return $form_field_type === self::FIELD_TYPE_SPECIAL;
        }
        return $field_id[0] === '!';
    }

    /**
     * Method has side-effect
     * @param $field_id
     * @param &$form_field_type, side-effect var, form-type of filed will be returned
     * @return crmDealField|waContactPasswordField|null
     */
    public static function getFieldObject($field_id, &$form_field_type = null)
    {
        if (waConfig::get('is_template')) {
            return null;
        }
        static $contact_fields;
        static $deal_fields;

        if (!$deal_fields) {
            $deal_fields = crmDealFields::getAll('enabled');
        }
        if (!$contact_fields) {
            $contact_fields = waContactFields::getAll('enabled', true);
        }

        if (isset($contact_fields[$field_id])) {
            $form_field_type = crmFormConstructor::FIELD_TYPE_CONTACT;
            return $contact_fields[$field_id];
        } elseif (isset($deal_fields[$field_id])) {
            $form_field_type = crmFormConstructor::FIELD_TYPE_DEAL;
            return $deal_fields[$field_id];
        } elseif ($field_id === 'password') {
            $form_field_type = crmFormConstructor::FIELD_TYPE_CONTACT;
            return new waContactPasswordField('password', 'Password');
        } else {
            $form_field_type = crmFormConstructor::FIELD_TYPE_SPECIAL;
            return null;
        }
    }

    protected static function getAllAllowedFieldIds()
    {
        if (self::$allowed_field_ids !== null) {
            return self::$allowed_field_ids;
        }

        self::$allowed_field_ids = array(
            '!deal_description',
            '!deal_attachments',
            '!horizontal_rule',
            '!paragraph',
            '!agreement_checkbox',
            '!captcha',
            'password',
        );

        $contact_fields = waContactFields::getAll('enabled', true);
        self::$allowed_field_ids = array_merge(self::$allowed_field_ids, array_keys($contact_fields));

        $deal_fields = crmDealFields::getAll('enabled');
        self::$allowed_field_ids = array_merge(self::$allowed_field_ids, array_keys($deal_fields));

        self::$allowed_field_ids = array_fill_keys(self::$allowed_field_ids, true);

        // throw off prohibited keys
        foreach (array('company_contact_id', 'name') as $field_id) {
            if (isset(self::$allowed_field_ids[$field_id])) {
                unset(self::$allowed_field_ids[$field_id]);
            }
        }

        return self::$allowed_field_ids;
    }

    public static function getCaptchaIsInvisible()
    {
        if (self::$captcha_is_invisible === null) {
            self::$captcha_is_invisible = wa('crm')->getCaptcha()->isInvisible();
        }
        return self::$captcha_is_invisible;
    }

    public static function getCaptchaType()
    {
        if (self::$captcha_type === null) {
            self::$captcha_type = waCaptcha::getCaptchaType('crm');
        }
        return self::$captcha_type;
    }

    /**
     * @return array
     */
    protected function getContactFields()
    {
        $fields = array(
            'main' => array(),
            'other' => array(),
            'specials' => array()
        );

        $all_fields = waContactFields::getAll('enabled', true);

        if (isset($all_fields['company_contact_id'])) {
            unset($all_fields['company_contact_id']);
        }
        if (isset($all_fields['name'])) {
            unset($all_fields['name']);
        }
        $field_constructor = new crmFieldConstructor();
        $disabled_fields = array_fill_keys($field_constructor->getPersonAlwaysDisabledFields(), true);
        $main_fields = array_fill_keys($field_constructor->getPersonMainFields(), true);

        foreach ($all_fields as $fld_id => $fld) {
            if (isset($disabled_fields[$fld_id]) || !$fld) {
                continue;
            }
            if (isset($main_fields[$fld_id])) {
                $fields['main'][$fld_id] = $all_fields[$fld_id];
            } else {
                $fields['other'][$fld_id] = $all_fields[$fld_id];
            }
        }

        // PASSWORD IS ALWAYS AT THE BOTTOM

        $fields['specials']['password'] = new waContactPasswordField('password', 'Password');

        $res_fields = array();
        foreach (array('main', 'other', 'specials') as $ns) {
            foreach ((array) ifset($fields[$ns]) as $fld_id => $field) {
                $res_fields[$fld_id] = $field;
            }
        }
        $res_fields = array_filter($res_fields);

        $fields = array();

        /**
         * @var waContactField[] $res_fields
         */
        foreach ($res_fields as $field_id => $field) {

            $required_always = $field_id === 'password';
            $params = array(
                'required' => $required_always || $field->isRequired(),
                'required_always' => $required_always
            );

            $name = $field->getName();

            $pf = waContactFields::get($field_id, 'person');
            $cf = waContactFields::get($field_id, 'company');

            $fields[$field_id] = array_merge(
                array(
                    'id' => $field_id,
                    'field' => $field,
                    'name' => $name,
                    'type' => $field->getType(),
                    'is_multi' => false,
                    'placeholder_need' =>
                        ($field instanceof waContactSelectField
                            || $field instanceof waContactBirthdayField 
                            || $field instanceof waContactCompositeField
                            || $field instanceof waContactAddressField
                            || $field instanceof waContactCheckboxField
                        ) ? false : true,
                    'form_field_type' => self::FIELD_TYPE_CONTACT,
                    'person_enabled' => boolval($pf),
                    'company_enabled' => boolval($cf),
                ),
                $params
            );
        }

        return $fields;
    }

    /**
     * @return array
     */
    protected function getSpecialFields()
    {
        $captcha_type = self::getCaptchaType();
        $captcha_is_invisible = self::getCaptchaIsInvisible();
        $fields = array(
            '!deal_description' => array(
                'id' => '!deal_description',
                'name' => _w('Text'),
                'placeholder_need' => true,
                'is_multi' => false,
                'form_field_type' => self::FIELD_TYPE_SPECIAL
            ),
            '!deal_attachments' => array(
                'id' => '!deal_attachments',
                'name' => _w('Attachments'),
                'placeholder_need' => false,
                'is_multi' => false,
                'form_field_type' => self::FIELD_TYPE_SPECIAL
            ),
            '!horizontal_rule' => array(
                'id' => '!horizontal_rule',
                'name' => _w('Horizontal rule'),
                'placeholder_need' => true,
                'is_multi' => true,
                'form_field_type' => self::FIELD_TYPE_SPECIAL
            ),
            '!paragraph' => array(
                'id' => '!paragraph',
                'name' => _w('Text paragraph'),
                'is_multi' => true,
                //'captionplace' => 'left',
                'form_field_type' => self::FIELD_TYPE_SPECIAL
            ),
            '!agreement_checkbox' => array(
                'id' => '!agreement_checkbox',
                'name' => _w('Consent to terms'),
                'is_multi' => true,
                'captionplace' => 'none',
                'html_label' =>
                    sprintf(
                        _w('I agree to <a class="c-agreement-checkbox-link" href="%s" target="_blank">personal data protection policy</a>'),
                        _w('---INSERT A LINK HERE!---')
                    ),
                'html_label_default_href_placeholder' => _w('---INSERT A LINK HERE!---'),
                'form_field_type' => self::FIELD_TYPE_SPECIAL
            ),
            '!captcha' => array(
                'id' => '!captcha',
                'name' => _w('Antispam (captcha)'),
                'caption' => $captcha_type === 'waPHPCaptcha' ? 
                    _w('Enter the code from the image') :
                    _w('Antibot protection'),
                'required' => true,
                'required_always' => true,
                'is_multi' => false,
                'is_invisible' => $captcha_is_invisible,
                'captcha_type' => $captcha_type,
                'placeholder_need' => $captcha_type === 'waPHPCaptcha',
                'captionplace' => $captcha_is_invisible ? 'none' : 'left',
                'form_field_type' => self::FIELD_TYPE_SPECIAL
            )
        );

        return $fields;
    }


    /**
     * @return array
     */
    protected function getDealFields()
    {
        $fields = array();
        foreach (crmDealFields::getAll('enabled') as $field_id => $field) {
            $name = $field->getName();
            $fields[$field_id] = array(
                'id' => $field_id,
                'field' => $field,
                'name' => $name,
                'type' => $field->getType(),
                'is_multi' => false,
                'placeholder_need' => !($field instanceof waContactSelectField) && !($field instanceof waContactCompositeField) && !($field instanceof waContactAddressField),
                'form_field_type' => self::FIELD_TYPE_DEAL
            );
        }
        return $fields;
    }
}
