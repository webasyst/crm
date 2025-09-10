<?php

class crmFormRenderer
{
    /**
     * @var crmForm
     */
    protected $form;

    /**
     * @var array
     */
    protected $options;

    protected $captcha_is_invisible = false;

    protected $captcha;

    /**
     * crmForm constructor.
     * @param int $id
     * @param array $options
     */
    public function __construct($id, $options = array())
    {
        $this->form = new crmForm((int)$id);
        $this->options = $options;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->getForm()->getId();
    }

    /**
     * @return crmForm
     */
    public function getForm()
    {
       return $this->form;
    }

    /**
     * @param bool $lastest_version
     * @return string
     */
    public function render($lastest_version = false)
    {
        $form = $this->getForm();
        if (!$form->exists()) {
            return '';
        }

        $info = $form->getInfo();
        $info['params']['button'] = json_decode(ifset($info, 'params', 'button', '{}'), true) ?: [
            'captionplace' => empty($info['params']['button_caption']) ? 'left' : 'none',
            'width' => 'auto',
            'caption' => ifset($info, 'params', 'button_caption', _w('Submit')),
        ];
        $info = $this->workupFormInfoBeforeRender($info);
        if ($lastest_version) {
            $info['params']['_version'] = '2';
        }

        $view = wa()->getView();
        $vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign(array(
            'form' => $info,
            'locale' => wa()->getLocale(),
            'action_url' => wa()->getRouteUrl('crm/frontend/formSubmit', array()),
            'options' => $this->options,
            'page_url' => wa()->getConfig()->getRootUrl(true) . wa()->getConfig()->getRequestUrl(),
            'captcha_is_invisible' => $this->captcha_is_invisible,
        ));

        $path = wa()->getAppPath('templates/form/form.html', 'crm');
        $html = $view->fetch($path);

        $view->clearAllAssign();
        $view->assign($vars);

        return $html;
    }

    /**
     * @param $form
     * @return array
     */
    protected function workupFormInfoBeforeRender($form)
    {
        $fields = array_values((array)ifset($form['params']['fields']));

        foreach ($fields as $index => &$field) {
            if (!crmFormConstructor::isFieldAllowed($field['id'])) {
                unset($fields[$index]);
                continue;
            }

            if ($field['id'] === 'password' && $this->isUserAuth()) {
                unset($fields[$index]);
                continue;
            }

            if ($field['id'] === 'password' || $field['id'] === '!captcha') {
                $field['required'] = 1;
                $field['required_always'] = 1;
            }

            $field['form_field_type'] = null;
            $field['field'] = crmFormConstructor::getFieldObject($field['id'], $field['form_field_type']);
            $field['html'] = $this->renderField($field);

        }
        unset($field);

        $form['params']['fields'] = $fields;

        return $form;
    }

    protected function renderField($info)
    {
        $field_type = $info['form_field_type'];
        $field_id = ltrim($info['id'], '!');
        $env = 'frontend';

        if (isset($info['field']) && $info['field'] instanceof waContactField) {
            $contact_field_type = strtolower($info['field']->getType());
            if ($info['field'] instanceof waContactRadioSelectField || $info['field'] instanceof waContactBranchField) {
                $template_path = "templates/form/fields/{$env}/field.contact.radio.html";
            } elseif ($info['field'] instanceof waContactSelectField) {
                $template_path = "templates/form/fields/{$env}/field.contact.select.html";
            } elseif ($info['field'] instanceof waContactTextField) {
                $template_path = "templates/form/fields/{$env}/field.contact.textarea.html";
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
                        'form_field_type' => crmFormConstructor::FIELD_TYPE_CONTACT,
                        'field' => $subfield,
                        'parent' => $field_id,
                    ];
                    $subfield_info['html'] = $this->renderField($subfield_info);
                    $info['subfields'][$subfield_id] = $subfield_info;
                }
                $template_path = "templates/form/fields/{$env}/field.contact.composite.html";
            } else {
                $template_path = "templates/form/fields/{$env}/field.{$field_type}.{$contact_field_type}.html";
            }
        } else {
            $field_id = ltrim($info['id'], '!');
            $template_path = "templates/form/fields/{$env}/field.{$field_type}.{$field_id}.html";
        }

        $template_path = wa()->getAppPath($template_path, 'crm');
        if (file_exists($template_path)) {
            $html = $this->renderFieldByTemplate($template_path, $info);
        } elseif (isset($info['field']) && $info['field'] instanceof waContactField) {
            $html = $this->renderContactField($info);
        } elseif (isset($info['field']) && $info['field'] instanceof crmDealField) {
            $html = $this->renderDealField($info);
        } else {
            $html = '';
        }
        return $html;
    }

    protected function isUserAuth()
    {
        return wa()->getUser()->isAuth();
    }

    protected function getUser()
    {
        return wa()->getUser();
    }

    protected function renderContactField($info)
    {
        $params = $info;
        unset($params['field']);
        if ($info['field']->getType() === 'Date') {
            $params['template'] = 'date.datepicker';
        }

        $classes = array(
            "crm-{$info['id']}-input"
        );

        $is_required = !empty($params['required']);
        if ($is_required) {
            $classes[] = 'crm-required-input';
        }

        $params['attrs'] = 'class="' . join(' ', $classes) . '"';

        if (!empty($params['placeholder'])) {
            $params['attrs'] .= ' placeholder="' . htmlspecialchars($params['placeholder']) . '"';
        }

        if ($this->isUserAuth()) {
            $params['value'] = $this->getUserFieldValue($info['field']);

            $editable = self::isEditableField(
                $this->isUserAuth(),
                $info['field'],
                $is_required,
                $params['value']
            );

            if (!$editable) {
                $params['attrs'] .= ' disabled="disabled"';
            }
        }

        $params['namespace'] = 'crm_form';

        if ($info['id'] === 'address') {
            $params['xhr_url'] = wa()->getRouteUrl('crm/frontend/formRegions', array());
            $params['xhr_cross_domain'] = true;
        }

        $field_renderer = new crmFormFieldRenderer($info['field'], $params);
        return $field_renderer->render();
    }

    /**
     * @param bool $is_user_auth
     * @param waContactField|crmDealField $field
     * @param bool $is_required
     * @param string|value $value
     * @return bool
     */
    public static function isEditableField($is_user_auth, $field, $is_required, $value)
    {
        // all fields for not-authorized user are editable
        if (!$is_user_auth) {
            return true;
        }

        if (!($field instanceof waContactField) && !($field instanceof crmDealField)) {
            return true;
        }

        $is_contact_field = crmFormConstructor::isFieldOfContact($field->getId());
        $is_vertical_field = $is_contact_field && !($field->getStorage() instanceof waContactInfoStorage);

        // vertical field is always editable
        if ($is_vertical_field) {
            return true;
        }

        // so we have here only CONTACT HORIZONTAL fields

        return $is_required && self::isEmpty($value);
    }

    /**
     * @param waContactField $field
     * @return array|int|mixed|string|null
     * @throws waException
     */
    protected function getUserFieldValue($field)
    {
        $person_enabled = waContactFields::get($field->getId(), 'person');
        $company_enabled = waContactFields::get($field->getId(), 'company');
        $exclusive_company_enabled = $company_enabled && !$person_enabled;

        $contact = $this->getUser();

        // if need get value from company-contact then substitute contact in case if company exists
        $is_person = !$this->getUser()->get('is_company');
        if ($is_person && $exclusive_company_enabled) {
            $company_id = $contact->get('company_contact_id');
            $company = new waContact($company_id);
            if ($company->exists()) {
                $contact = $company;
            }
        }

        if (!($field instanceof waContactCompositeField)) {
            if ($field->isMulti()) {
                return $contact->get($field->getId(), 'default');
            } else {
                return $contact->get($field->getId());
            }
        }

        $field_data = $contact->get($field->getId());
        if (empty($field_data)) {
            return array();
        }
        $field_data = reset($field_data);
        return array($field_data);
    }

    /**
     * @param array $info
     * @return string
     */
    protected function renderDealField($info)
    {
        $params = $info;
        unset($params['field']);
        if ($info['field']->getType() === 'Date') {
            $params['template'] = 'date.datepicker';
        }

        $classes = array(
            "crm-{$info['id']}-input"
        );
        if (!empty($params['required'])) {
            $classes[] = 'crm-required-input';
        }

        $params['attrs'] = 'class="' . join(' ', $classes) . '"';

        if (!empty($params['placeholder'])) {
            $params['attrs'] .= 'placeholder="' . htmlspecialchars($params['placeholder']) . '"';
        }

        $params['namespace'] = 'crm_form';

        if ($info['id'] === 'address') {
            $params['xhr_url'] = wa()->getRouteUrl('crm/frontend/formRegions', array());
            $params['xhr_cross_domain'] = true;
        }

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
        $object = $this->getCaptcha();
        $isReCaptcha = $object instanceof waReCaptcha;
        $info['object'] = $object;
        $info['isReCaptcha'] = $isReCaptcha;
        return $this->renderFieldTemplate($template, $info);
    }

    protected function getCaptcha()
    {
        if (empty($this->captcha)) {
            $this->captcha = wa('crm')->getCaptcha();
            $this->captcha_is_invisible = $this->captcha->isInvisible();
        }
        return $this->captcha;
    }

    protected function renderFieldTemplate($template, $assign = array())
    {
        $params = $assign;
        $params['template_path'] = $template;
        $params['namespace'] = 'crm_form';

        $field_renderer = new crmFormFieldRenderer($assign['field'], $params);
        return $field_renderer->render();
    }

    private static function isEmpty($value)
    {
        return !self::isNotEmpty($value);
    }

    private static function isNotEmpty($value) {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (self::isNotEmpty($val)) {
                    return true;
                }
            }
            return false;
        }
        $value = is_scalar($value) ? trim((string)$value) : '';
        return strlen($value) > 0;
    }
}
