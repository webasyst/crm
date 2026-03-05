<?php

class crmNotification
{
    /**
     * @var crmModel[]
     */
    protected static $models;

    /**
     * @var crmConfig
     */
    protected static $app_config;

    /**
     * @var array
     */
    protected static $notifications_variants;

    /**
     * @var array
     */
    protected static $event_types;

    /**
     * @var array
     */
    protected static $event_spaces;


    /**
     * @var array
     */
    protected $options;

    /**
     * @var waContact
     */
    protected $customer;

    /**
     * @var waContact
     */
    protected $responsible;

    /**
     * @var array
     */
    protected $vars;

    /**
     * @var array
     */
    protected $notification;

    /**
     * @var int
     */
    protected $id;

    protected $recipient_type;

    protected function __construct($data, $options = array())
    {
        $this->notification = $this->obtainNotification($data);
        $this->id = (int)$this->notification['id'];
        $this->options = $options;
    }

    /**
     * Get info as it in DB plus some extra virtual fields
     * @return array
     *   - bool 'is_invoice_event' - is event of notification about invoice
     *   -
     *
     */
    public function getInfo()
    {
        return $this->notification;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getEvent()
    {
        $info = $this->getInfo();
        return $info['event'];
    }

    /**
     * @return bool
     */
    public function isInvoiceEvent()
    {
        $event_id = $this->getEvent();
        return substr($event_id, 0, 8) == 'invoice.';
    }

    /**
     * @return bool
     */
    public function isDealEvent()
    {
        $event_id = $this->getEvent();
        return substr($event_id, 0, 5) == 'deal.';
    }

    /**
     * @return array|null
     * @throws waDbException
     * @throws waException
     */
    public function getCompany()
    {
        $info = $this->getInfo();
        if (!$this->isInvoiceEvent() || $info['company_id'] <= 0) {
            return null;
        }
        $cm = new crmCompanyModel();
        return $cm->getById($info['company_id']);
    }

    public function getFunnel()
    {
        $info = $this->getInfo();
        if (!$this->isDealEvent() || $info['funnel_id'] <= 0) {
            return null;
        }
        $cm = new crmFunnelModel();
        return $cm->getById($info['funnel_id']);
    }

    /**
     * @param bool $render
     * @return string
     */
    public function getBody($render = false)
    {
        $this->notification['body'] = trim((string)ifset($this->notification['body']));
        if ($render) {
            $this->renderNotificationTemplates();
            return $this->notification['__rendered_body'];
        }
        return $this->notification['body'];
    }

    /**
     * @param bool $render
     * @return string
     */
    public function getSubject($render = false)
    {
        $this->notification['subject'] = (string)ifset($this->notification['subject']);
        if ($render) {
            $this->renderNotificationTemplates();
            return $this->notification['__rendered_subject'];
        }
        return $this->notification['subject'];
    }

    protected function getVars()
    {
        return [];
    }

    protected function renderNotificationTemplates()
    {
        if (isset($this->notification['__rendered_body'])) {
            return;
        }
        $subject = $this->getSubject();
        $body = $this->getBody();
        $templates = array($subject, $body);
        $res = $this->renderTemplates($templates, $this->getVars());
        list($subject, $body) = $res;
        $this->notification['__rendered_body'] = $body;
        $this->notification['__rendered_subject'] = $subject;
    }

    /**
     * @return string
     */
    public function getTransport()
    {
        $this->notification['transport'] = (string)ifset($this->notification['transport']);
        return $this->notification['transport'];
    }

    public function delete()
    {
        $id = $this->getId();
        if ($id > 0) {
            return self::getNotificationModel()->delete($id);
        }
        return true;
    }

    public function save($data = array())
    {
        /**
         * @event notification_save
         * @param array $data
         * @return void
         */
        wa('crm')->event('notification_save', $data);

        $id = $this->getId();
        if ($id > 0) {
            $data['id'] = $id;
        } else {
            $data = array_merge($this->getInfo(), $data);
            unset($data['id']);
        }

        $id = self::getNotificationModel()->save($data);
        $this->notification = $this->obtainNotification($id);

        /**
         * @event backend_notification_save
         * @param array [string]mixed $params
         * @param array [string]array $params['notification']
         * @return void
         */
        $event_params = array(
            'notification' => $this->notification
        );
        wa('crm')->event('backend_notification_save', $event_params);
    }

    /**
     * @param int|int[]|array|array[] $data
     * @param array $options
     * @return crmNotification|crmNotification[]|null
     * @throws waDbException
     * @throws waException
     */
    public static function factory($data, $options = array())
    {
        if (waConfig::get('is_template')) {
            return null;
        }

        // define input type
        $type = 'id';
        if (is_array($data)) {
            if (isset($data['id'])) {
                $type = 'record';
            } else {
                $item = reset($data);
                if (is_array($item) && isset($item['id'])) {
                    $type = 'records';
                } else {
                    $type = 'ids';
                }
            }
        }

        // typecast input data
        if ($type === 'id') {
            $data = array(wa_is_int($data) ? (int)$data : 0);
        } elseif ($type === 'record') {
            $data['id'] = wa_is_int($data['id']) ? (int)$data['id'] : 0;
            $data = array($data);
        } else {
            foreach ($data as &$item) {
                if ($type === 'ids') {
                    $item = wa_is_int($item) ? (int)$item : 0;
                } else {
                    $item['id'] = wa_is_int($item['id']) ? (int)$item['id'] : 0;
                }
            }
            unset($item);
        }

        // collect instances
        $instances = array();

        foreach ($data as $item) {
            $_id = $type === 'id' || $type === 'ids' ? $item : $item['id'];
            $_options = $options;
            if (isset($_options[$_id])) {
                $_options = $_options[$_id];
            }

            if (wa_is_int($item)) {
                $item = self::obtainNotificationById($item);
            }

            if (!empty($item['event'])) {
                if (stripos($item['event'], 'deal.') === 0) {
                    $instances[$_id] = new crmNotificationDeal($item, $options);
                } else {
                    $instances[$_id] = new crmNotificationInvoice($item, $options);
                }
            } else {
                $instances[$_id] = new crmNotification($item, $_options);
            }
        }
        return $type === 'id' || $type === 'record' ? reset($instances) : $instances;
    }

    /**
     * @param string $event
     * @param array $options
     * @return crmNotification[]
     */
    public static function factoryByEventType($event, $options = array())
    {
        if (waConfig::get('is_template')) {
            return array();
        }

        $notifications = self::getNotificationModel()->getNotificationsByEvent($event, false);

        // collect instances
        $instances = array();
        foreach ($notifications as $notification) {
            if (stripos($event, 'deal.') === 0) {
                $instances[$notification['id']] = new crmNotificationDeal($notification, $options);
            } else {
                $instances[$notification['id']] = new crmNotificationInvoice($notification, $options);
            }
        }
        return $instances;
    }

    /**
     * @param string $event
     * @param array $options
     * @return array
     */
    public static function sendByEventType($event, $options = array())
    {
        $result = array();
        $options['wa_log'] = false;
        $notifications = self::factoryByEventType($event, $options);
        foreach ($notifications as $notification) {
            $result[$notification->getId()] = $notification->send();
        }
        return $result;
    }

    /**
     * Replaces the recipient's address with the one that was specified when creating the test message
     * @param int|string $address
     * @return bool
     * @throws waException
     */
    public function sendTestNotification($address)
    {
        $old_address = $this->recipient_type;
        $this->notification['recipient'] = $address;
        $this->notification['url'] = $address;
        $this->notification['status'] = 1;
        $this->notification['test_mode'] = true;
        if ($this->notification['transport'] == crmNotificationModel::TRANSPORT_REMINDER) {
            $this->notification['recipient'] = 'reminder';
            $this->notification['reminder_user_type'] = 'selected';
        }
        $res = false;
        try {
            $res = $this->sendNotification();
        } catch (waException $e) {
            $this->recipient_type = $old_address;
            throw $e;
        }
        $this->recipient_type = $old_address;
        return $res;
    }

    protected function sendNotification()
    {
        return false;
    }

    /**
     * Send notification
     * Override in concrete class
     * @param array $options
     * @return bool
     */
    public function send($options = array())
    {
        return false;
    }

    protected function createReminder($contact_id, $user_contact_id = null)
    {
        if (!crmHelper::isPremium()) {
            return false;
        }

        if (!isset($this->notification['reminder_due_date']) || !wa_is_int($this->notification['reminder_due_date'])) {
            return false;
        }

        if (!empty($this->notification['test_mode'])) {
            $user_contact_id = wa()->getUser()->getId();
        }

        if (empty($user_contact_id)) {
            if (empty($this->notification['responsible_contact_id']) || !wa_is_int($this->notification['responsible_contact_id'])) {
                return false;
            }

            $user_contact_id = $this->notification['responsible_contact_id'] > 0 ?
                $this->notification['responsible_contact_id'] :
                (new crmDealModel)->getResponsibleUserOfGroup(-1 * $this->notification['responsible_contact_id']);
        }

        if (empty($user_contact_id)) {
            return false;
        }

        $content = ifset($this->notification['reminder_content'], '');
        if (!empty($content)) {
            list($content) = $this->renderTemplates([$content], $this->getVars());
        }

        $now = date('Y-m-d H:i:s');
        $types = crmConfig::getReminderType();
        $reminder = [
            'create_datetime' => $now,
            'update_datetime' => $now,
            'creator_contact_id' => wa()->getUser()->getId(),
            'user_contact_id' => $user_contact_id,
            'content'         => $content,
            'contact_id'      => $contact_id,
            'type'            => ifset($types[ifset($this->notification['reminder_type'])]) ? $this->notification['reminder_type'] : 'OTHER',
            'due_date'        => date('Y-m-d', strtotime('+'.$this->notification['reminder_due_date'].' day')),
            'due_datetime'    => null,
        ];

        if (!empty($this->notification['test_mode'])) {
            return [
                'data' => [
                    'message' => '<p>'._w('Reminder successfully created. It is not saved in the test mode.') . '</p><p class="small"><span class="hint">'._w('Due date') . ': '. waDateTime::format('humandate', $reminder['due_date']) .'</span><br>'.$content.'</p>',
                ],
            ];
        }

        $id = $reminder['id'] = (new crmReminderModel)->insert($reminder);
        crmReminder::sendNotification($reminder, array($reminder['user_contact_id']), 'reminder_new');

        if (!empty($reminder['contact_id'])) {
            crmDeal::updateReminder($reminder['contact_id']);
        }

        (new crmLogModel)->log('reminder_add', $reminder['contact_id'], $id);

        return true;
    }

    protected function sendHttp()
    {
        if (!crmHelper::isPremium()) {
            return false;
        }

        $n = $this->notification;
        $webhook_url = ifempty($n, 'url', false);
        if (!$webhook_url) {
            return false;
        }

        if (!in_array(ifset($n, 'method', ''), [waNet::METHOD_GET, waNet::METHOD_POST, waNet::METHOD_PUT, waNet::METHOD_PATCH, waNet::METHOD_DELETE])) {
            $n['method'] = waNet::METHOD_GET;
        }

        if ($n['method'] === waNet::METHOD_GET) {
            $n['post'] = null;
        }

        $data = $this->getVars();

        //GET params
        if (!empty($n['get'])) {
            $n['get'] = self::prepareHttpParam($data, $n['get']);
            if (!empty($n['get'])) {
                $webhook_url.= (strpos($webhook_url, '?') === false) ? '?' : '&';
                $webhook_url.= http_build_query($n['get']);
            }
        }

        //POST params
        if (!empty($n['post'])) {
            $n['post'] = self::prepareHttpParam($data, $n['post']);
        }

        // HTTP headers
        if (!empty($n['headers'])) {
            $n['headers'] = self::prepareHttpParam($data, $n['headers']);
        }

        $options = [
            'request_format' => $n['format'] === 'raw' ? waNet::FORMAT_RAW : waNet::FORMAT_JSON,
            'format' => $n['format'] === 'raw' ? waNet::FORMAT_RAW : waNet::FORMAT_JSON,
            'tolerate_empty_body_request' => true,
            'expected_http_code' => null,
        ];

        $net = new waNet($options, ifempty($n, 'headers', []));
        try {
            $net->query($webhook_url, ifempty($n, 'post', []), $n['method']);
        } catch (waException $e) {
            waLog::dump($e->getMessage(), 'crm/webhook.error.log');
            return empty($this->notification['test_mode']) ? false : ['status' => false, 'errors' => [$e->getMessage()]];
        }

        if (!empty($this->notification['test_mode'])) {
            $response_code = $net->getResponseHeader('http_code');
            $response_body = $net->getResponse(true);
            if (!empty($response_body) && mb_strlen($response_body) > 1000) {
                $response_body = mb_substr($response_body, 0, 1000) . '...';
            }
            $response_body = htmlspecialchars($response_body);
            $message = ($response_code < 400) ? _w('HTTP request successfully sent.') : _w('HTTP request failed.');
            $message = '<p>'. $message . '</p><div class="small">'._w('Response code').': ' . $response_code . '<br>'._w('Response body').': <pre>' . $response_body . '</pre></div>';
            return [
                'data' => [
                    'message' => $message,
                ],
            ];
        }

        return true;
    }

    private static function prepareHttpParam($data, $params_string)
    {
        $params_string = trim($params_string);
        $view = wa()->getView();
        $view->assign($data);
        $params = self::parseParams($params_string);
        if (!empty($params_string) && empty($params)) {
            return self::tryJsonDecode($params_string, $view);
        }
        foreach ($params as &$param) {
            //Skip text e.g. order=order
            if (strpos($param, '{') === false) {
                continue;
            }
            //Check for existing keys in order data
            $preset_key = trim($param,'{$}');
            if (array_key_exists($preset_key, $data)) {
                $param = $data[$preset_key];
                continue;
            }
            try {
                $param = $view->fetch('string:' . $param);
            } catch (Exception $e) {
                waLog::dump($e->getMessage(), 'crm/webhook.error.log');
                continue;
            }
            //Check if param is encoded array like order={$order.items|json_encode}
            try {
                $test_array = @json_decode($param, true);
                if (is_array($test_array)) {
                    $param = $test_array;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        return $params;
    }

    protected static function parseParams($params)
    {
        $result = [];
        if ($params) {
            $params = explode("\n", $params);
            foreach ($params as $param) {
                $param = explode('=', trim($param), 2);
                if (count($param) === 2) { //?
                    $result[$param[0]] = $param[1];
                }
            }
        }
        return $result;
    }

    protected static function tryJsonDecode($string, $view)
    {
        $string = $view->fetch('string:' . $string);
        try {
            return json_decode($string, true);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @param $data
     * @return array|null
     * @throws waDbException
     * @throws waException
     */
    protected function obtainNotification($data)
    {
        if (is_array($data) && isset($data['id'])) {
            return $this->notification = $data;
        }
        $id = wa_is_int($data) ? $data : 0;
        $notification = self::obtainNotificationById($id);
        return $this->notification = $notification;
    }

    /**
     * @param $id
     * @return array|null
     * @throws waDbException
     * @throws waException
     */
    protected static function obtainNotificationById($id)
    {
        $notification = self::getNotificationModel()->getNotification($id);
        if (!$notification) {
            $notification = self::getNotificationModel()->getEmptyRow();
        }
        return $notification;
    }

    protected function saveMessage($message)
    {
        $customer = $this->getCustomer();
        $message['direction'] = crmMessageModel::DIRECTION_OUT;
        $message['contact_id'] = $customer ? $customer->getId() : 0;
        $message['event'] = $this->getEvent();
        $message['crm_log_id'] = ifempty($this->options, 'crm_log_id', 0);
        $wa_log = array_key_exists('wa_log', $this->options) ? $this->options['wa_log'] : true;
        self::getMessageModel()->fix($message, array(
            'wa_log' => $wa_log,
        ));
    }

    /**
     * @param array[] string $templates
     * @param array $vars
     * @return array[]string
     */
    protected function renderTemplates($templates, $vars = array())
    {
        $view = wa()->getView();
        $prev_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign($vars);
        $result = array();
        foreach ($templates as $i => $template) {
            $result[$i] = $view->fetch('string:'.$template);
        }
        $view->clearAllAssign();
        $view->assign($prev_vars);
        return $result;
    }

    /**
     * @return string|null
     */
    protected function getSmsSender()
    {
        if ($this->getTransport() === crmNotificationModel::TRANSPORT_SMS) {
            $sender = $this->notification['sender'];
            if ($sender !== crmNotificationModel::SENDER_SYSTEM && $sender !== crmNotificationModel::SENDER_SPECIFIED && $sender !== '*') {
                return $sender;
            }
        }
        return null;
    }

    /**
     * @param bool $with_names
     * @return array
     */
    public static function getEventTypes($with_names = false)
    {
        if (self::$event_types === null) {
            $notifications = self::getNotificationVariants();
            self::$event_types = waUtils::getFieldValues($notifications, 'name', 'event');
        }
        return $with_names ? self::$event_types : array_keys(self::$event_types);
    }

    public static function getTransports()
    {
        return array(
            crmNotificationModel::TRANSPORT_EMAIL => array(
                'name' => _w('Send email'),
                'icon' => 'envelope',
                'is_premium_only' => false,
            ),
            crmNotificationModel::TRANSPORT_SMS   => array(
                'name' => _w('Send SMS'),
                'icon' => 'mobile-alt',
                'is_premium_only' => false,
            ),
            crmNotificationModel::TRANSPORT_HTTP   => array(
                'name' => _w('Send HTTP request'),
                'icon' => 'globe',
                'is_premium_only' => true,
            ),
            crmNotificationModel::TRANSPORT_REMINDER   => array(
                'name' => _w('Create reminder'),
                'icon' => 'bell',
                'is_premium_only' => true,
            ),
        );
    }

    public static function getRecipient()
    {
        return array(
            crmNotificationModel::RECIPIENT_CLIENT      => array(
                'name' => _w('Client'),
            ),
            crmNotificationModel::RECIPIENT_RESPONSIBLE => array(
                'name' => _w('Owner'),
            )
            ,
            crmNotificationModel::RECIPIENT_OTHER       => array(
                'name' => _w('Other'),
            )
        );
    }

    /**
     * Get sender types with localized name
     * @return array
     */
    public static function getSender()
    {
        return array(
            crmNotificationModel::SENDER_SYSTEM    => array(
                'name' => _w('System default'),
            ),
            crmNotificationModel::SENDER_SPECIFIED => array(
                'name' => _w('Specified'),
            ),
        );
    }

    public static function getSMSSenders()
    {
        $sms_from = array(
            crmNotificationModel::SENDER_SYSTEM => array(
                'name' => _w('System default'),
            ),
        );

        if (!waSMS::adapterExists()) {
            return $sms_from;
        }

        $sms_config = wa()->getConfig()->getConfigFile('sms');

        // sender '*' in CRM names "System default", so in foreach skip '*'

        foreach ($sms_config as $from => $options) {
            if ($from != '*') {
                $sms_from[$from] = array(
                    'name' => $from . ' (' . $options['adapter'] . ')'
                );
            }
        }

        $sms_from[crmNotificationModel::SENDER_SPECIFIED] = array(
            'name' => _w('Specified'),
        );

        return $sms_from;
    }

    /**
     * @return array
     */
    public static function getEventSpaces()
    {
        if (self::$event_spaces !== null) {
            return self::$event_spaces;
        }
        $spaces = array();
        $types = self::getEventTypes();
        foreach ($types as $type) {
            $parts = explode('.', $type, 2);
            $spaces[] = $parts[0];
        }
        return self::$event_spaces = array_unique($spaces);
    }

    /**
     * @return array
     */
    public static function getNotificationVariants($exclude = array())
    {
        if (self::$notifications_variants !== null) {
            return self::$notifications_variants;
        }

        $path = self::getAppConfig()->getAppPath('lib/config/data');
        if (!file_exists($path.'/notifications.php')) {
            return self::$notifications_variants = array();
        }

        $locale = wa()->getLocale() == 'ru_RU' ? 'ru_RU' : 'en_US';

        $notifications = array();
        $_notifications = include($path.'/notifications.php');
        foreach ($_notifications as $n) {
            if ($exclude && in_array($n['event'], $exclude)) {
                continue;
            }
            $n['name'] = _w($n['name']);
            $n['subject'] = _w($n['subject']);
            $n['recipient'] = ifset($n, 'recipient', 'client');
            $n['sms'] = _w($n['sms']);

            $n['body'] = '';
            $file_path = $path.'/templates/'.$n['event'].'.'.$locale.'.html';
            if (file_exists($file_path)) {
                $n['body'] = file_get_contents($file_path);
            }
            $notifications[$n['event']] = $n;
        }

        return self::$notifications_variants = $notifications;
    }

    /**
     * @param string $type
     * @return string
     */
    protected static function getSpaceOfEventType($type)
    {
        $parts = explode('.', $type, 2);
        return (string)ifset($parts[0]);
    }

    /**
     * @return crmAppConfig
     */
    protected static function getAppConfig()
    {
        return self::$app_config !== null ? self::$app_config : (self::$app_config = wa('crm')->getConfig());
    }

    /**
     * @return crmCompanyModel
     */
    protected static function getCompanyModel()
    {
        return !empty(self::$models['company']) ? self::$models['company'] : (self::$models['company'] = new crmCompanyModel());
    }

    /**
     * @return waContactModel
     */
    protected static function getContactModel()
    {
        return !empty(self::$models['cm']) ? self::$models['cm'] : (self::$models['cm'] = new waContactModel());
    }

    /**
     * @return crmNotificationModel
     */
    protected static function getNotificationModel()
    {
        return !empty(self::$models['nm']) ? self::$models['nm'] : (self::$models['nm'] = new crmNotificationModel());
    }

    /**
     * @return crmMessageModel
     */
    protected static function getMessageModel()
    {
        return !empty(self::$models['mm']) ? self::$models['mm'] : (self::$models['mm'] = new crmMessageModel());
    }
    /**
     * @return waContact|null
     */
    public function getCustomer()
    {
        if ($this->customer !== null) {
            return $this->customer === false ? null : $this->customer;
        }

        $this->customer = false;

        if (isset($this->options['customer'])) {
            if (wa_is_int($this->options['customer']) || is_array($this->options['customer'])) {
                return $this->customer = new waContact($this->options['customer']);
            } elseif ($this->options['customer'] instanceof waContact) {
                return $this->customer = $this->options['customer'];
            }
        }

        return null;
    }

    /**
     * @param int|array|waContact $customer
     */
    public function setCustomer($customer)
    {
        if (wa_is_int($customer) || is_array($customer)) {
            $customer = new waContact($customer);
        }

        $this->customer = null;
        if ($customer instanceof waContact) {
            $this->customer = $customer;
        }
    }

    /**
     * @param int|array|waContact $client_id
     */
    public function getResponsible($client_id)
    {
        $responsible_id = null;

        if (wa_is_int($client_id) || is_array($client_id)) {
            $client = new waContact($client_id);
            $responsible_id = $client->get('crm_user_id');
        }
        if ($responsible_id) {
            $this->responsible = new waContact($responsible_id);
        }
        return $this->responsible;
    }
}
