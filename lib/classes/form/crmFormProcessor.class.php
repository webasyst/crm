<?php

class crmFormProcessor
{
    static protected $LOG_FILE = 'crm/source_form.log';

    /**
     * @var crmForm
     */
    protected $form;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var waUser
     */
    protected $user;

    /**
     * @var bool
     */
    protected $is_auth;

    protected $strategy_type;

    /**
     * @var array
     */
    protected $form_fields;

    /**
     * crmFormProcessor constructor.
     * @param array $options
     */
    public function __construct($options = array())
    {
        $this->options = $options;
        $this->user = wa()->getUser();
        $this->is_auth = $this->user->isAuth();
    }

    /**
     * Main method for process form data
     *
     * @param int $id Form id
     * @param array $data
     * @return array|null $result
     *     - Null $result - Not processed, something wrong
     *     - Array $result - Processed, return response for further work. All keys are OPTIONAL
     *         array $result['errors'] Validation errors
     *         array $result['captcha_hash'] Need be send to js as mark that captcha is passed
     *         bool $result['send_confirmation'] Confirmation link has been send
     *         string $result['redirect'] If not missed form must be redirected to this url
     *         string $result['html'] If not missed html block must be shown
     */
    public function process($id, $data)
    {
        $this->initForm($id);
        
        if ($this->isBotDetected($data)) {
            waLog::log('Bot detected. Form data:' . PHP_EOL . wa_dump_helper($data), self::$LOG_FILE);

            //return ['errors' => [_ws('Who the fuck are you?')]];
            return $this->getResponseAfterSuccessProcess();
        }

        $data = $this->sanitize($data);

        // validation
        $result = $this->validate($data);
        if ($result) {
            return $result;
        }

        // save contact data to temp table and send confirmation email
        if ($this->needToConfirmEmail()) {
            $this->sendConfirmationLink($data);
            $response = $this->getResponseAfterSuccessProcess(array(
                'send_confirmation' => true
            ));
            return $response;
        }

        $result = $this->processData($data);
        if (!$result) {
            return null;
        }
        if (!empty($result['errors'])) {
            return $result;
        }

        return $this->getResponseAfterSuccessProcess();
    }

    protected function isBotDetected($data)
    {
        $antibot_honey_pot = $this->getForm()->getParam('antibot_honey_pot');
        if (empty($antibot_honey_pot)) {
            return false;
        }

        if (!empty($antibot_honey_pot['empty_field_name']) && !empty($data[$antibot_honey_pot['empty_field_name']])) {
            // empty field filled by bot (must be empty)
            return true;
        }
        
        if (!empty($antibot_honey_pot['filled_field_name']) && 
            !empty($antibot_honey_pot['filled_field_value']) && (
                empty($data[$antibot_honey_pot['filled_field_name']]) || 
                $data[$antibot_honey_pot['filled_field_name']] != $antibot_honey_pot['filled_field_value']
            )
        ) {
            // filled field value not valid (so js does not work)
            return true;
        }
        
        return false;
    }

    protected function getResponseAfterSuccessProcess($default = array())
    {
        $response = array_merge(array(
            'send_confirmation' => false
        ), $default);

        $after_submit = $this->getForm()->getParam('after_submit');
        if (!$after_submit || $after_submit === 'redirect') {
            $url = wa()->getUrl(true);
            if ($this->getForm()->getParam('redirect_after_submit')) {
                $url = $this->getForm()->getParam('redirect_after_submit');
            }
            $response['redirect'] = $url;
            return $response;
        }

        $response['html'] = (string)$this->getForm()->getParam('html_after_submit');
        return $response;
    }

    /**
     * Confirmation over the hash
     *
     * If process method send confirmation link, controller must call this method, by passing hash received from link
     *
     * @param string $hash
     * @return array|bool
     *     - False - Confirmation failed
     *     - Array - Confirmation succeed, return response for further work
     */
    public function processConfirmEmail($hash)
    {
        $cst = new crmTempModel();
        $data = $cst->getByHash($hash);
        $cst->deleteByHash($hash);

        if (!$data) {
            return false;
        }

        $form_id = (int)ifset($data['data']['form_id']);
        unset($data['data']['form_id']);

        $this->initForm($form_id);

        $result = $this->processData($data['data']);

        if (!$result) {
            return false;
        }
        if (!empty($result['errors'])) {
            return $result['errors'];
        }

        $contact = $result['contact'];
        $contact_email = $contact->getDefaultEmailValue();
        $contact->setEmailConfirmed($contact_email);

        $url = null;
        $after_antispam_confirm = $this->getForm()->getParam('after_antispam_confirm');
        if ($after_antispam_confirm === 'redirect') {
            $redirect_url = (string) $this->getForm()->getParam('after_antispam_confirm_url');
            if (strlen($redirect_url) > 0) {
                $url = $redirect_url;
            }
        }

        if ($url) {
            return array('redirect_url' => $url);
        } else {
            return array('text' => (string)$this->getForm()->getParam('after_antispam_confirm_text'));
        }
    }

    public static function getFormFields(crmForm $form, $strategy_type = null)
    {
        if (waConfig::get('is_template')) {
            return array();
        }
        if ($form->getProperty('__processed_fields')) {
            return (array)$form->getProperty('__processed_fields');
        }
        $fields = $form->getFields();
        foreach ($fields as $index => $field) {
            if (!crmFormConstructor::isFieldAllowed($field['id'])) {
                unset($fields[$index]);
            }
            if ($field['id'] === 'password' && $strategy_type === 'is_authorized_user') {
                unset($fields[$index]);
            }
        }
        $form->setProperty('__processed_fields', $fields);
        return $fields;
    }

    protected function processData($data)
    {
        // processing contact data
        $result = $this->processContactData($data);
        if (!empty($result['errors'])) {
            return $result;
        }

        $contact = $result['contact'];

        $messages = $this->getForm()->getMessages();
        $messages_with_attachements = array_filter($messages, function($message) { return !empty($message['add_attachments']); });
        $attachements = [];
        if (!empty($messages_with_attachements) && !empty($data['!deal_attachments'])) {
            $dir = wa()->getTempPath('attachements', 'crm');
            foreach($data['!deal_attachments'] as $attachement) {
                $filepath = tempnam($dir, 'i');
                waFiles::delete($filepath);
                $attachement->copyTo($filepath);
                $attachements[] = [
                    'name' => $attachement->name,
                    'path' => $filepath,
                ];
            }
        }

        // processing deal data if need
        $deal = null;
        if ($this->getForm()->getParam('create_deal')) {
            if (!$this->getForm()->getSource()->getParam('create_deal')) {
                $this->getForm()->getSource()->saveParam('create_deal', 1);
            }
            if (!$this->getForm()->getSource()->isDisabled()) {
                $this->getForm()->getSource()->saveAsEnabled();
            }
            $result = $this->processDealData($data, $contact);
            if ($result) {
                $deal = ifset($result['deal']);
            }
        }

        $this->sendMessages($messages, $contact, $deal, $attachements);

        foreach ($attachements as $attachement) {
            waFiles::delete($attachement['path']);
        }

        return array(
            'contact' => $contact,
            'deal' => $deal
        );
    }

    protected function isUserAuth()
    {
        return $this->is_auth;
    }

    /**
     * @return waUser
     */
    protected function getUser()
    {
        return $this->user;
    }

    protected function initForm($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new waException(_w('Form not found'));
        }
        $form = new crmForm($id);
        if (!$form->exists()) {
            throw new waException(_w('Form not found'));
        }
        $this->form = $form;
    }

    /**
     * @return crmForm
     * @throws waException
     */
    protected function getForm()
    {
        if (!$this->form) {
            throw new waException(_w('Form not found'));
        }
        return $this->form;
    }

    /**
     * Use after initForm strictly
     * @return string
     */
    protected function getStrategyType()
    {
        if ($this->strategy_type !== null) {
            return $this->strategy_type;
        }
        if ($this->isUserAuth()) {
            $this->strategy_type = 'is_authorized_user';
        } elseif ($this->getForm()->getType() === crmForm::TYPE_SIGN_UP) {
            $this->strategy_type = 'sign_up';
        } else {
            $this->strategy_type = 'basic';
        }
        return $this->strategy_type;
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

    protected function needToConfirmEmail()
    {
        if ($this->isStrategyForAuthorizedUser()) {
            return false;
        }
        return $this->getForm()->getParam('confirm_mail');
    }

    protected function validate($data)
    {
        $validator = new crmFormValidator($this->getForm(), array(
            'strategy_type' => $this->getStrategyType(),
        ));
        $errors = $validator->validate($data);
        if ($errors) {
            return array(
                'errors' => $errors,
                'captcha_hash' => $validator->getCaptchaHash()
            );
        }
        return array();
    }

    protected function processContactData($data)
    {
        $processor = new crmFormContactDataProcessor(
            $this->getForm(),
            array(
                'strategy_type' => $this->getStrategyType(),
                'user' => $this->getUser()
            )
        );
        $result = $processor->process($data);
        if (!$result) {
            return array(
                'errors' => $processor->getErrors()
            );
        }
        return $result;
    }

    protected function processDealData($data, crmContact $contact)
    {
        $processor = new crmFormDealDataProcessor($this->getForm());
        $result = $processor->process($data, $contact);
        return $result;
    }

    protected function sanitize($data)
    {
        foreach ($data as $uid => $value) {
            if (!$this->getForm()->isFieldPresented($uid) &&
                    $uid !== 'locale' &&
                    $uid !== '!form_page_url' &&
                    $uid !== 'password_confirm' &&
                    $uid !== 'captcha_hash') {
                unset($data[$uid]);
            }
        }
        return $data;
    }

    /**
     * @param $messages
     * @param crmContact $contact
     * @param array|null $deal
     */
    protected function sendMessages($messages, crmContact $contact, $deal, $attachements)
    {
        $vars = null;
        $assign = null;

        foreach ($messages as $message) {

            if (empty($message['is_smarty_tmpl'])) {
                if ($vars === null) {
                    $vars = array_merge(array(
                        '{ORIGINAL_TEXT}' => $deal ? $deal['description'] : '',
                        '{COMPANY_NAME}' => htmlspecialchars(wa()->accountName())
                    ), $this->getContactVars($contact));
                }

                $compiled = $this->compilePlainMailTemplate($message['tmpl'], $vars);
            } else {
                if ($assign === null) {
                    $assign = [
                        'original_text' => $deal ? $deal['description'] : '',
                        'company_name' => wa()->accountName(),
                        'customer' => $contact
                    ];
                }

                $compiled = $this->compileSmartyMailTemplate($message['tmpl'], $assign);
            }

            $body = $compiled['body'];
            $subject = $compiled['subject'];
            $attaches = empty($message['add_attachments']) ? [] : $attachements;

            foreach ((array) ifset($message['to']) as $to => $on) {
                if (!$on) {
                    continue;
                }

                $to_contact = null;
                if ($to == crmFormSource::MESSAGE_TO_VARIANT_CLIENT) {
                    $to_contact = $contact;
                } elseif ($to === crmFormSource::MESSAGE_TO_VARIANT_RESPONSIBLE_USER) {
                    $responsible_contact_id = $this->getForm()->getSource()->getNormalizedResponsibleContactId();
                    $to_contact = new crmContact($responsible_contact_id);
                } elseif (wa_is_int($to)) {
                    $to_contact = new crmContact($to);
                }

                if (!$to_contact) {
                    continue;
                }

                $email = $to_contact->getProperty('email_to_send');
                if (!$email) {
                    $email = $to_contact->getDefaultEmailValue();
                }
                if (!$email) {
                    continue;
                }

                $to = array($email => $to_contact->getName());
                $from = waMail::getDefaultFrom();

                $this->sendEmail($subject, $body, $from, $to, $attaches);
            }
        }
    }

    protected function getContactVars(crmContact $contact)
    {
        $vars = array();
        foreach ($contact->load('value,list') as $fld_name => $value) {
            $var_name = strtoupper('{CUSTOMER_'.$fld_name.'}');
            if (is_array($value)) {
                $vars[$var_name] = $contact->get($fld_name, 'default');
                if (is_array($vars[$var_name]) || is_object($vars[$var_name])) {
                    unset($vars[$var_name]);
                }
            } else {
                $vars[$var_name] = $value;
            }
        }

        $vars['{CUSTOMER_ID}'] = $contact->getId();
        $vars['{CUSTOMER_NAME}'] = htmlspecialchars($contact->getName());

        return $vars;
    }

    protected function compilePlainMailTemplate($template, $vars = array())
    {
        $parts = explode('{SEPARATOR}', $template, 3);
        $body = array_pop($parts);
        $subject = array_pop($parts);
        $from = array_pop($parts);
        $subject = $this->resolveVars($subject, $vars);
        $body = $this->resolveVars($body, $vars);
        return array(
            'from' => $from,
            'subject' => $subject,
            'body' => $body
        );
    }

    protected function compileSmartyMailTemplate($template, $assign = [])
    {
        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign($assign);

        $parts = explode('{SEPARATOR}', $template, 3);
        $body = array_pop($parts);
        $subject = array_pop($parts);
        $from = array_pop($parts);

        $body = $view->fetch('string:'.$body);
        $subject = $view->fetch('string:'.$subject);

        $view->clearAllAssign();
        $view->assign($old_vars);

        return array(
            'from' => $from,
            'subject' => $subject,
            'body' => $body
        );
    }

    protected function resolveVars($message, $vars = array())
    {
        foreach ($vars as $var => $val) {
            $message = str_replace($var, $val, $message);
        }
        return $message;
    }

    protected function sendEmail($subject, $body, $from, $to, $attachments = [])
    {
        try {
            $m = new waMailMessage(htmlspecialchars_decode($subject), $body);
            $m->setTo($to)->setFrom($from);
            foreach ($attachments as $attachment) {
                $m->addAttachment($attachment['path'], $attachment['name']);
            }
            $sent = $m->send();
            $reason = 'waMailMessage->send() returned FALSE';
        } catch (Exception $e) {
            $sent = false;
            $reason = $e->getMessage();
        }

        if (!$sent) {
            if (is_array($to)) {
                $to = var_export($to, true);
            }

            waLog::log('Unable to send email from '.$from.' to '.$to.' ('.$subject.'): '.$reason, self::$LOG_FILE);
            return false;
        }

        return true;
    }

    protected function sendConfirmationLink($data)
    {
        $email = (string) ifset($data['email']);
        if (strlen($email) <= 0) {
            return;
        }

        $to = "";
        $subject = $this->getForm()->getParam('confirm_mail_subject');
        $body = $this->getForm()->getParam('confirm_mail_body');

        $confirmation_hash = md5(mt_rand() . uniqid(__METHOD__ . $this->getForm()->getId(), true));
        $confirm_url = wa()->getRouteUrl('crm/frontend/confirmEmail', array('hash' => $confirmation_hash), true);

        $body = str_replace('{CONFIRM_URL}', $confirm_url, $body);

        $from = waMail::getDefaultFrom();
        $to = array($email => $to);

        try {
            $m = new waMailMessage($subject, $body);
            $m->setFrom($from);
            $m->setTo($to);
            $sent = (bool) $m->send();
            $reason = 'waMailMessage->send() returned FALSE';
        } catch (Exception $e) {
            $sent = false;
            $reason = $e->getMessage();
        }

        if (!$sent) {
            $to = is_array($to) ? var_export($to, true) : $to;
            $from = is_array($from) ? var_export($from, true) : $from;
            waLog::log('Unable to send email from '.$from.' to '.$to.' ('.$subject.'): '.$reason, 'crm.log');
            return false;
        }

        $cst = new crmTempModel();
        $data['form_id'] = $this->getForm()->getId();
        $data['form_type'] = $this->getForm()->getType();
        $cst->save($confirmation_hash, $data);

        return true;
    }
}
