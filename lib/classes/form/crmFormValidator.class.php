<?php

class crmFormValidator
{
    /**
     * @var crmForm
     */
    protected $form;

    /**
     * @var array
     */
    protected $options;

    public function __construct(crmForm $form, $options = array())
    {
        $this->form = $form;
        $this->options = $options;
        $this->options['strategy_type'] = (string)ifset($this->options['strategy_type']);
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

    public function validate($data)
    {
        // first of all we must validate captcha, so we can remember that it's not a bot
        $captcha_errors = $this->validateCaptcha($data);

        if ($this->isSignUpStrategy()) {
            $errors = $this->validateAtLeastOneRequired($data);
            if ($errors) {
                return array_merge($captcha_errors, $errors);
            }
        }

        $errors = $this->validateForm($data);
        if ($errors) {
            return array_merge($captcha_errors, $errors);
        }

        if ($this->isSignUpStrategy()) {
            $errors = $this->validateForSignUp($data);
            if ($errors) {
                return array_merge($captcha_errors, $errors);
            }
        }

        if ($captcha_errors) {
            return $captcha_errors;
        }

        // cleaning, cause of uselessness
        $this->delCaptchaHash();

        return array();
    }

    public function getCaptchaHash()
    {
        return wa()->getStorage()->get('crm/form/validation/captcha_hash');
    }

    protected function generateCaptchaHash()
    {
        wa()->getStorage()->set('crm/form/validation/captcha_hash', md5(uniqid(__METHOD__, true)));
    }

    protected function delCaptchaHash()
    {
        wa()->getStorage()->del('crm/form/validation/captcha_hash');
    }

    protected function validateAtLeastOneRequired($data)
    {
        $errors = array();

        $failed = true;
        $at_least_one_required = array('firstname', 'lastname', 'middlename', 'email', 'company');
        foreach ($at_least_one_required as $field_id) {
            $value = trim(ifset($data[$field_id], ''));
            if (strlen($value) > 0) {
                $failed = false;
                continue;
            }
        }
        if ($failed) {
            $errors[join(',', $at_least_one_required)] = _w('At least one of these fields must be filled.');
        }
        return $errors;
    }

    protected function validateForm($data)
    {
        $errors = array();

        $contact = new crmContact();

        $password_present = false;
        $password_value = '';
        $password_without_confirm = false;

        foreach ($this->getFormFields() as $field) {

            $uid = $field['uid'];

            if ($field['id'] == '!deal_attachments') {
                if (!empty($field['required']) && count($data['!deal_attachments']) <= 0) {
                    $errors[$uid] = sprintf(_ws("%s is required"), $this->getFieldName($uid));
                }
                continue;
            }


            $value = $this->trim(ifset($data[$uid]));

            if ($this->skipAuthorizedUserFieldValidation($field)) {
                continue;
            }

            if (!empty($field['required']) && $this->isEmpty($value) && $uid !== '!captcha') {
                $errors[$uid] = array(
                    sprintf(_ws("%s is required"), $this->getFieldName($uid))
                );
                continue;
            }

            if ($field['id'] === '!agreement_checkbox') {
                if ($this->isEmpty($value)) {
                    $errors[$uid] = '';
                }
                continue;
            }


            if ($field['id'] === 'password') {
                $password_present = true;
                $password_value = $value;
                $password_without_confirm = ifset($field['without_confirm'], false);
            }

            if (crmFormConstructor::isFieldOfContact($field)) {
                $fld = waContactFields::get($field['id']);
                if ($fld) {
                    $error = $fld->validate($fld->set($contact, $value, array()));
                    if ($error) {
                        $errors[$field['id']] = $error;
                        continue;
                    }
                }
            } elseif (crmFormConstructor::isFieldOfDeal($field)) {
                $fld = crmDealFields::get($field['id']);
                if ($fld) {
                    $error = $fld->validate($value);
                    if ($error) {
                        $errors[$field['id']] = $error;
                        continue;
                    }
                }
            }
        }

        if ($password_present && !$password_without_confirm) {
            $password_confirm_value = (string)ifset($data['password_confirm']);
            if ($password_value !== $password_confirm_value) {
                $errors['password,password_confirm'] = array(_ws('Passwords do not match'));
            }
        }

        return $errors;
    }

    protected function skipAuthorizedUserFieldValidation($field)
    {
        $is_auth = $this->isStrategyForAuthorizedUser();
        if (!$is_auth) {
            return false;
        }
        $fld = crmFormConstructor::getFieldObject($field['id']);
        $editable = crmFormRenderer::isEditableField(
            $is_auth,
            $fld,
            !empty($field['required']),
            wa()->getUser()->get($field['id'])
        );
        return !$editable;
    }

    /**
     * Check captcha_hash or CAPTCHA value itself
     * @see validate
     *
     * If CAPTCHA is valid generate captcha_hash (mark as not bot)
     * Latter by controller captcha_hash we will be received in $data array
     *
     * @param $data
     * @return array|void
     */
    protected function validateCaptcha($data)
    {
        if (!$this->form->isCaptchaPresented()) {
            $this->delCaptchaHash();
            return array();
        }

        $error_response = array('!captcha' => _ws('Invalid captcha'));;

        $is_valid_captcha_itself = wa()->getCaptcha()->isValid(ifset($data['!captcha']));

        $hash = $this->getCaptchaHash();

        if (!$hash && !$is_valid_captcha_itself) {
            return $error_response;
        }

        $data['captcha_hash'] = ifset($data['captcha_hash']);
        if ($hash !== $data['captcha_hash'] && !$is_valid_captcha_itself) {
            return $error_response;
        }

        $this->generateCaptchaHash();

        return array();

    }

    protected function validateForSignUp($data)
    {
        $errors = array();

        $is_email_presented = $this->form->isEmailPresented();

        $email = (string) ifset($data['email']);
        if ($is_email_presented && strlen($email) <= 0) {
            $errors['email'] = sprintf(_ws("%s is required"), $this->getFieldName('email'));
        }

        if ($errors) {
            return $errors;
        }

        // check exists contacts
        $auth = wa()->getAuth();

        // check if user exist and set unconfirmed status for email
        if ($is_email_presented && strlen($email) > 0) {
            $contact = $auth->getByLogin($email);
            if ($contact) {
                $errors['email'] = array(
                    sprintf(_ws('User with the same %s is already registered'), $this->getFieldName('email'))
                );
            }
        }

        return $errors;
    }

    protected function isEmpty($value)
    {
        return !$this->isNotEmpty($value);
    }

    protected function trim($value)
    {
        if (is_array($value)) {
            foreach ($value as &$val) {
                $val = $this->trim($val);
            }
            unset($val);
            return $value;
        }
        return trim((string) $value);
    }

    protected function getFieldName($field_id)
    {
        $field = $this->form->getFieldByUid($field_id);
        if ($field) {
            if (isset($field['caption'])) {
                return $field['caption'];
            } elseif (isset($field['name'])) {
                return $field['name'];
            }
        }
        return ucfirst($field_id);
    }

    private function isNotEmpty($value) {
        if (is_array($value)) {
            foreach ($value as $val) {
                if ($this->isNotEmpty($val)) {
                    return true;
                }
            }
            return false;
        }
        $value = is_scalar($value) ? trim((string) $value) : '';
        return strlen($value) > 0;
    }

}
