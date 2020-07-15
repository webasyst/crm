<?php

class crmNotificationBirthdayWorker
{
    protected $options;

    static protected $LOG_FILE = 'crm/notification_birthday_worker.log';

    protected $event = 'customer.birthday';

    /**
     * @var array
     */
    protected $contact_ids;

    /**
     * @var int
     */
    protected $total_count;

    /**
     * @var array[string]waModel
     */
    static protected $models;

    protected $max_execution_time;

    public function __construct($options = array())
    {
        $this->options = $options;
        if (isset($options['max_execution_time'])) {
            $this->max_execution_time = (int) $options['max_execution_time'];
        }
    }

    /**
     * @param array $options
     * @return null|array
     */
    public static function cliRun($options = array())
    {
        if (waConfig::get('is_template')) {
            return;
        }

        self::getSettingsModel()->set('crm', 'notification_birthday_cli_start', date('Y-m-d H:i:s'));
        if (!isset($options['max_execution_time'])) {
            $options['max_execution_time'] = wa('crm')->getConfig()->getMaxExecutionTime();
        }

        $res = null;
        try {
            $worker = new self($options);
            $res = $worker->processAll();
        } catch (Exception $e) {
            $message = join(PHP_EOL, array(
                'Exception',
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            waLog::log($message, self::$LOG_FILE);
        }
        self::getSettingsModel()->set('crm', 'notification_birthday_cli_end', date('Y-m-d H:i:s'));

        return $res;
    }

    public static function getLastCliRunDateTime()
    {
        return self::getSettingsModel()->get('crm', 'notification_birthday_cli_end');
    }

    public static function isCliOk()
    {
        return !!self::getLastCliRunDateTime();
    }

    /**
     * @return waAppSettingsModel
     */
    protected static function getSettingsModel()
    {
        return !empty(self::$models['asm']) ? self::$models['asm'] : (self::$models['asm'] = new waAppSettingsModel());
    }

    /**
     * @return crmNotificationModel
     */
    protected static function getNotificationModel()
    {
        return !empty(self::$models['nm']) ? self::$models['nm'] : (self::$models['nm'] = new crmNotificationModel());
    }

    public function processAll()
    {
        $total_count = $this->getTotalCount();
        $contact_ids = $this->getContactIds();
        $count = count($contact_ids);

        /**
         * @event start_notification_birthday_worker
         */
        wa('crm')->event('start_notification_birthday_worker');

        $processed_count = 0;
        $start_time = time();

        foreach ($contact_ids as $contact_id) {

            $contact = new crmContact($contact_id);
            $this->processContact($contact);
            $processed_count += 1;
            $elapsed_time = time() - $start_time;
            if ($this->max_execution_time !== null && $elapsed_time > $this->max_execution_time - 5) {
                break;
            }
        }

        $done = true;
        if ($processed_count < $count || $count < $total_count) {
            $done = false;
        }

        return array(
            'total_count' => $total_count,
            'processed_count' => $processed_count,
            'count' => $count,
            'done' => $done
        );
    }

    /**
     * @param waContact $contact
     */
    protected function processContact($contact)
    {
        foreach (crmNotification::factoryByEventType($this->event) as $notification) {
            $notification->send(array(
                'customer' => $contact
            ));
        }
    }

    protected function getContactIds()
    {
        if ($this->contact_ids !== null) {
            return $this->contact_ids;
        }
        $m = new crmModel();
        $sql = $this->getContactsSql();
        $this->contact_ids = $m->query($sql)->fetchAll(null, true);
        return $this->contact_ids;
    }

    protected function getTotalCount()
    {
        if ($this->total_count !== null) {
            return (int)$this->total_count;
        }
        $m = new crmModel();
        $sql = $this->getContactsSql('count');
        $this->total_count = (int)$m->query($sql)->fetchField();
        return $this->total_count;
    }

    public function needToProcess()
    {
        return $this->getTotalCount() > 0;
    }

    protected function getContactsSql($type = 'list')
    {
        $select = "c.id";
        $limit = "LIMIT 0, 500";
        $group_py = 'GROUP BY c.id';

        if ($type === 'count') {
            $select = 'COUNT(DISTINCT c.id)';
            $limit = '';
            $group_py = '';
        }

        $delay = date('Y-m-d H:i:s', strtotime('-60 days'));

        $birth_day = date('j');
        $birth_month = date('n');

        return "SELECT {$select} 
                FROM `wa_contact` c
                LEFT JOIN `crm_message` m ON c.id = m.contact_id AND m.event = '{$this->event}' AND m.create_datetime > '{$delay}'
                WHERE 
                    c.birth_day = '{$birth_day}' AND c.birth_month = '{$birth_month}' AND m.id IS NULL
                {$group_py}
                {$limit}";
    }

    /**
     * @return crmConfig
     */
    protected function getConfig()
    {
        return wa('crm')->getConfig();
    }
}
