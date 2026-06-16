<?php

abstract class crmEmailSource extends crmSource
{
    const MESSAGE_TO_VARIANT_CLIENT = 'client';
    const MESSAGE_TO_VARIANT_RESPONSIBLE_USER = 'responsible_user';

    const PARAM_EMAIL_SUFFIX_SUPPORTING = 'email_suffix_supporting';
    const EMAIL_SUFFIX_SUPPORTING_NO = 'no';
    const EMAIL_SUFFIX_SUPPORTING_YES = 'yes';
    const EMAIL_SUFFIX_SUPPORTING_UNKNOWN = 'unknown';
    const EMAIL_SUFFIX_SUPPORTING_CLARIFYING = 'clarifying';

    /**
     * Default mail provider preset when creating a new email source (POP3 / IMAP provider dropdown).
     */
    const DEFAULT_MAIL_PROVIDER_ID = 'webasyst_cloud';

    protected $type = crmSourceModel::TYPE_EMAIL;

    public function __construct($id = null, array $options = array())
    {
        parent::__construct($id, $options);

        if (!$this->provider && $this->id > 0) {
            $provider = self::getSourceModel()->select('provider')->where('id = ?', $id)->fetchField();
            $this->provider = $provider;
        }

        if (!$this->provider) {
            throw new crmSourceException(
                sprintf("Couldn't factor email source instance: unknown provider %s", $this->provider ? $this->provider : 'NULL')
            );
        }
    }

    /**
     * @param int|string $id
     * @param array $options
     * @return crmEmailSource
     * @throws crmSourceException
     */
    public static function factory($id, array $options = array())
    {
        $instance = parent::factory($id, $options);
        if (!($instance instanceof crmEmailSource) && !($instance instanceof crmNullSource)) {
            throw new crmSourceException(sprintf("Can't factory email source '%s'", $id));
        }
        return $instance;
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->getParam('messages_formatted');
    }

    protected function workupParams($params)
    {
        if (!isset($params['antispam_mail_template'])) {
            $params['antispam_mail_template'] = self::getDefaultAntiSpamMailTemplate();
        }

        $params['messages_formatted'] = array();

        if (!isset($params['messages'])) {
            return $params;
        }

        $params['messages'] = (array)$params['messages'];
        foreach ($params['messages'] as &$message) {
            $message = (array)$message;
            if (!isset($message['tmpl'])) {
                $message['tmpl'] = self::getDefaultMessageMailTemplate();
            }
        }
        unset($message);

        $params['messages_formatted'] = self::formatMessagesArray($params['messages']);

        return $params;
    }

    protected function workupDataBeforeSave($data)
    {
        $data['params'] = (array)ifset($data['params']);
        $params_to_unset = array('messages_formatted');
        foreach ($params_to_unset as $name) {
            if (isset($data[$name])) {
                unset($data[$name]);
            }
        }
        return $data;
    }

    public function canWork()
    {
        return $this->exists() &&
            $this->isEnabled() &&
            $this->isValidEmail($this->getEmail());
    }

    protected function isValidEmail($email)
    {
        $email = (string)$email;
        if (strlen($email) <= 0) {
            return false;
        }
        $validator = new waEmailValidator();
        return $validator->isValid($email);
    }

    /**
     * @param string
     * @return string
     */
    public function getEmail($suffix = '')
    {
        $email = (string)$this->getParam('email');
        if (strlen($email) <= 0) {
            return '';
        }
        if (strlen($suffix) <= 0) {
            return $email;
        }
        $email = explode('@', $email, 2);
        $email[0] .= '+' . $suffix;
        return join('@', $email);
    }

    public function getEmailSuffixSupporting()
    {
        $value = (string)$this->getParam(self::PARAM_EMAIL_SUFFIX_SUPPORTING);
        if ($value === 'yes') {
            return self::EMAIL_SUFFIX_SUPPORTING_YES;
        } elseif ($value === 'no') {
            return self::EMAIL_SUFFIX_SUPPORTING_NO;
        } elseif ($value === 'clarifying') {
            return self::EMAIL_SUFFIX_SUPPORTING_CLARIFYING;
        } else {
            return self::EMAIL_SUFFIX_SUPPORTING_UNKNOWN;
        }
    }

    public function setEmailSuffixSupporting($value)
    {
        return $this->saveParam(self::PARAM_EMAIL_SUFFIX_SUPPORTING, $value);
    }

    public function sendSuffixSupportingTestEmail()
    {
        $email = $this->getEmail();
        if (!$email) {
            return;
        }
        $email = explode('@', $email, 2);
        $email[0] .= '+0';
        $email = join('@', $email);

        try {
            $subject = _w('Email address suffix test');
            $body = _w('This letter is automatically created for checking address suffix supporting');
            $m = new waMailMessage($subject, $body);
            $m->setTo($email);
            $m->send();
            $this->setEmailSuffixSupporting(self::EMAIL_SUFFIX_SUPPORTING_CLARIFYING);
        } catch (Exception $e) {
            $this->setEmailSuffixSupporting(self::EMAIL_SUFFIX_SUPPORTING_NO);
        }
    }

    public static function getDefaultMessageMailTemplate()
    {
        if (wa()->getLocale() == 'ru_RU') {
            return '[Re: ] {$original_subject}'.
                '{SEPARATOR}'.
                '<p>Мы ответим вам в ближайшее время.</p>'.
                '<p>Спасибо!</p>'.
                '<p>--</p>'.
                '<p>{$company_name}</p>'.
                '<p>Это автоматическое уведомление о получении обращения. Пожалуйста, не отвечайте на это сообщение!</p>'.
                '<blockquote>{$original_text}</blockquote>';
        }
        return '[Re: ] {$original_subject}'.
            '{SEPARATOR}'.
            '<p>We shall reply to you as soon as possible.</p>'.
            '<p>Thank you!</p>'.
            '<p>--</p>'.
            '<p>{$company_name}</p>'.
            '<p>This is an automatic request receipt notification. Please do not reply to this message!</p>'.
            '<blockquote>{$original_text}</blockquote>';
    }

    public static function isMessageToVariant($variant)
    {
        $variants = self::getMessageToVariants();
        return isset($variants[$variant]);
    }

    public static function getMessageToVariants()
    {
        return array(
            self::MESSAGE_TO_VARIANT_CLIENT => _w('Client (request originator)'),
            self::MESSAGE_TO_VARIANT_RESPONSIBLE_USER => _w('Responsible user (owner)'),
        );
    }

    public static function getAntiSpamTemplateVars()
    {
        return array(
            '{ORIGINAL_SUBJECT}' => _w('Subject of the original message'),
            '{ORIGINAL_TEXT}' => _w('Text of the original message'),
            '{CONFIRM_URL}' => _w('URL for non-registered clients to confirm their email addresses.')
        );
    }

    /**
     * Presets for built-in email source settings (POP3 / IMAP).
     *
     * @param string $kind 'pop3' or 'imap'
     * @return array<string, array<string, int|string>>
     */
    public static function getMailProviderPresets($kind)
    {
        $kind = (string) $kind;
        if ($kind === 'pop3') {
            $ssl = array('ssl' => 1, 'tls' => 0);
            return array(
                //'gmail' => array_merge(array('server' => 'pop.gmail.com', 'port' => 995), $ssl),
                'yandex' => array_merge(array('server' => 'pop.yandex.com', 'port' => 995), $ssl),
                'mailru' => array_merge(array('server' => 'pop.mail.ru', 'port' => 995), $ssl),
                'rambler' => array_merge(array('server' => 'pop.rambler.ru', 'port' => 995), $ssl),
                'yahoo' => array_merge(array('server' => 'pop.mail.yahoo.com', 'port' => 995), $ssl),
                'outlook' => array_merge(array('server' => 'outlook.office365.com', 'port' => 995), $ssl),
                'icloud' => array_merge(array('server' => 'pop.mail.me.com', 'port' => 995), $ssl),
                'zoho' => array_merge(array('server' => 'pop.zoho.com', 'port' => 995), $ssl),
                'gmx' => array_merge(array('server' => 'pop.gmx.com', 'port' => 995), $ssl),
                'webasyst_cloud' => array_merge(array('server' => 'mail2.host.webasyst.com', 'port' => 995), $ssl),
            );
        }
        if ($kind === 'imap') {
            return array(
                //'gmail' => array('server' => 'imap.gmail.com', 'port' => 993, 'ssl' => 1),
                'yandex' => array('server' => 'imap.yandex.com', 'port' => 993, 'ssl' => 1),
                'mailru' => array('server' => 'imap.mail.ru', 'port' => 993, 'ssl' => 1),
                'rambler' => array('server' => 'imap.rambler.ru', 'port' => 993, 'ssl' => 1),
                'yahoo' => array('server' => 'imap.mail.yahoo.com', 'port' => 993, 'ssl' => 1),
                'outlook' => array('server' => 'outlook.office365.com', 'port' => 993, 'ssl' => 1),
                'icloud' => array('server' => 'imap.mail.me.com', 'port' => 993, 'ssl' => 1),
                'zoho' => array('server' => 'imap.zoho.com', 'port' => 993, 'ssl' => 1),
                'gmx' => array('server' => 'imap.gmx.com', 'port' => 993, 'ssl' => 1),
                'webasyst_cloud' => array('server' => 'mail2.host.webasyst.com', 'port' => 993, 'ssl' => 1),
            );
        }
        return array();
    }

    /**
     * Ordered list for provider dropdown (last key must be custom).
     *
     * @return array<string, string>
     */
    public static function getMailProvidersForUi()
    {
        if (wa()->getLocale() === 'ru_RU') {
            return [
                'webasyst_cloud' => _w('Webasyst Cloud Mail'),
                'yandex' => _w('Yandex'),
                'mailru' => _w('Mail.ru'),
                'rambler' => _w('Rambler'),
                //'gmail' => _w('Gmail'),
                'yahoo' => _w('Yahoo Mail'),
                'outlook' => _w('Outlook.com / Hotmail / Office365'),
                'icloud' => _w('iCloud'),
                'zoho' => _w('Zoho Mail'),
                'gmx' => _w('GMX'),
                'custom' => _w('Other mail server'),
            ];
        }
        return [
            'webasyst_cloud' => _w('Webasyst Cloud Mail'),
            //'gmail' => _w('Gmail'),
            'yandex' => _w('Yandex'),
            'mailru' => _w('Mail.ru'),
            'rambler' => _w('Rambler'),
            'yahoo' => _w('Yahoo Mail'),
            'outlook' => _w('Outlook.com / Hotmail / Office365'),
            'icloud' => _w('iCloud'),
            'zoho' => _w('Zoho Mail'),
            'gmx' => _w('GMX'),
            'custom' => _w('Other mail server'),
        ];
    }

    /**
     * Email domain rules for auto-suggesting mail provider on source settings forms.
     * Rule is exact host (yandex.ru) or suffix (.webasyst.cloud).
     *
     * @return array<string, string[]>
     */
    public static function getMailProviderDomainMap()
    {
        return array(
            'webasyst_cloud' => array('host.webasyst.com', '.webasyst.cloud'),
            'yandex' => array('yandex.ru', 'yandex.com', 'yandex.by', 'yandex.kz', 'ya.ru'),
            'mailru' => array('mail.ru', 'inbox.ru', 'bk.ru', 'list.ru', 'internet.ru'),
            'rambler' => array('rambler.ru', 'autorambler.ru', 'ro.ru', 'lenta.ru'),
            'yahoo' => array('yahoo.com', 'yahoo.co.uk', 'yahoo.de', 'yahoo.fr', 'yahoo.it', 'yahoo.es', 'ymail.com', 'rocketmail.com'),
            'outlook' => array('outlook.com', 'hotmail.com', 'hotmail.co.uk', 'live.com', 'live.ru', 'msn.com', 'office365.com'),
            'icloud' => array('icloud.com', 'me.com', 'mac.com'),
            'zoho' => array('zoho.com', 'zoho.eu', 'zoho.in', 'zoho.com.au', 'zohomail.com'),
            'gmx' => array('gmx.com', 'gmx.de', 'gmx.net', 'gmx.at'),
        );
    }

    /**
     * Suggest built-in mail provider id from email address domain.
     *
     * @param string $email
     * @return string|null provider id, 'custom', or null when domain cannot be parsed
     */
    public static function suggestMailProviderFromEmail($email)
    {
        $email = trim((string) $email);
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }
        $domain = substr($email, $at + 1);
        if ($domain === '') {
            return null;
        }
        foreach (self::getMailProviderDomainMap() as $provider => $rules) {
            foreach ($rules as $rule) {
                if (self::emailDomainMatchesProviderRule($domain, $rule)) {
                    return $provider;
                }
            }
        }
        return 'custom';
    }

    /**
     * @param string $domain
     * @param string $rule
     * @return bool
     */
    protected static function emailDomainMatchesProviderRule($domain, $rule)
    {
        if (function_exists('mb_strtolower')) {
            $domain = mb_strtolower($domain, 'UTF-8');
            $rule = mb_strtolower($rule, 'UTF-8');
        } else {
            $domain = strtolower($domain);
            $rule = strtolower($rule);
        }
        if ($rule !== '' && $rule[0] === '.') {
            $len = strlen($rule);
            return strlen($domain) >= $len && substr($domain, -$len) === $rule;
        }
        return $domain === $rule;
    }

    /**
     * Fills server/port/(ssl|tls) from preset when provider is not custom.
     *
     * @param string $kind 'pop3' or 'imap'
     * @param string $param_key storage key, e.g. mail_provider or imap_provider
     */
    public static function applyMailProviderPresetToParams(array &$params, $kind, $param_key = 'mail_provider')
    {
        $key = isset($params[$param_key]) ? (string) $params[$param_key] : '';
        if ($key === '' || $key === 'custom') {
            return;
        }
        $presets = self::getMailProviderPresets($kind);
        if (!isset($presets[$key])) {
            return;
        }
        $p = $presets[$key];
        $params['server'] = $p['server'];
        $params['port'] = (string) $p['port'];
        if ($kind === 'pop3') {
            $params['ssl'] = !empty($p['ssl']) ? 1 : 0;
            $params['tls'] = !empty($p['tls']) ? 1 : 0;
        } else {
            $params['ssl'] = !empty($p['ssl']) ? 1 : 0;
        }
    }

    /**
     * @return array
     */
    public static function getMessageTemplateVars()
    {
        $vars = array(
            '$original_subject' => _w('Subject of the original message'),
            '$original_text' => _w('Text - value of field text, description of deal'),
            '$company_name' => _w('Company name specified in your Installer settings (also displayed in the top-left corner of your backend)'),
        );

        $vars = array_merge($vars, crmHelper::getVarsForContact());

        $all_vars = [];
        foreach ($vars as $name => $description) {
            $all_vars[$name] = $description;
        }

        return $all_vars;
    }

    protected static function formatMessagesArray($messages)
    {
        $contact_ids = array();
        foreach ($messages as $message) {
            if (!empty($message['to'])) {
                foreach ($message['to'] as $id => $flag) {
                    if (!self::isMessageToVariant($id)) {
                        $contact_ids[] = $id;
                    }
                }
            }
        }
        $contact_ids = array_unique($contact_ids);
        if (!$contact_ids) {
            return $messages;
        }
        $col = new waContactsCollection('id/' . join(',', $contact_ids));
        $contacts = $col->getContacts('id,name,firstname,lastname,middlename,email');
        foreach ($contacts as &$contact) {
            $contact['name'] = waContactNameField::formatName($contact);
        }
        unset($contact);

        foreach ($messages as &$message) {
            if (!empty($message['to'])) {
                foreach ($message['to'] as $id => $flag) {
                    if (!self::isMessageToVariant($id) && isset($contacts[$id])) {
                        $message['to'][$id] = $contacts[$id]['name'];
                    }
                }
            }
        }
        unset($message);

        return $messages;
    }

    protected static function getDefaultAntiSpamMailTemplate()
    {
        if (wa()->getLocale() == 'ru_RU') {
            return
                'Пожалуйста, подтвердите отправку обращения'.
                "{SEPARATOR}".
                "<p>Пожалуйста, подтвердите ваше обращение. Для этого просто перейдите по ссылке:<br>".
                '<a href="{CONFIRM_URL}">{CONFIRM_URL}</a></p>'.
                "<p>ВНИМАНИЕ: Ваше обращение будет принят к обработке только после подтверждения.<br>".
                'Подтверждение необходимо в связи с большим количеством спама, приходящим на наш адрес. После того, как вы подтвердите обращение, ваш электронный адрес будет добавлен в нашу базу данных и все последующие обращения с этого адреса будут автоматически приниматься к обработке.</p>'.
                "<p>Спасибо!</p>";
        }
        return
            'Please confirm your request'.
            "{SEPARATOR}".
            '<p>We have just received a request from your email address.</p>'.
            "<p>To confirm your request, please follow this link:<br>".
            '<a href="{CONFIRM_URL}">{CONFIRM_URL}</a></p>'.
            "<p>NOTE: Your request will be accepted only after confirmation.<br>".
            'Confirmation is required due to high volume of SPAM we receive. This is one-time action. After you confirm, we will add your email address to our contact database, and all future requests from you will be automatically accepted and queued into our customer support tracking system.</p>'.
            "<p>Thank you!</p>";
    }
}
