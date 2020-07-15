<?php

class crmRemindersRecap
{
    protected $options;

    static protected $LOG_FILE = 'crm/reminder_recap_incoming_worker.log';

    protected $max_execution_time;

    /**
     * @var array[string]waModel
     */
    static protected $models;

    public function __construct($options = array())
    {
        $this->options = $options;
        if (isset($options['max_execution_time'])) {
            $this->max_execution_time = (int)$options['max_execution_time'];
        }
    }

    /**
     * @param array $options
     */
    public static function cliRun($options = array())
    {
        self::getSettingsModel()->set('crm', 'reminders_recap_cli_end', date('Y-m-d H:i:s'));

        if (!isset($options['max_execution_time'])) {
            $options['max_execution_time'] = wa('crm')->getConfig()->getMaxExecutionTime();
        }

        try {
            $worker = new self($options);
            $worker->processAll();
        } catch (Exception $e) {
            $message = join(PHP_EOL, array(
                'Exception',
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            waLog::log($message, self::$LOG_FILE);
        }
    }

    public static function getLastCliRunDateTime()
    {
        return self::getSettingsModel()->get('crm', 'reminders_recap_cli_end');
    }

    public static function isCliOk()
    {
        return !!self::getLastCliRunDateTime();
    }

    protected static function getSettingsModel()
    {
        return !empty(self::$models['asm']) ? self::$models['asm'] : (self::$models['asm'] = new waAppSettingsModel());
    }

    public function processAll()
    {
        /**
         * @event start_reminders_recap_worker
         */
        wa('crm')->event('start_reminders_recap_worker');
        $start_time = time();

        $model = new crmReminderModel();
        $arrUsersReminders = $model->getUsersForSendReminders();

        foreach ($arrUsersReminders as $UserData) {
            $id = $UserData['contact_id'];
            $date = self::getDateReminders($UserData['value']);

            $reminders = $model->getReminders($id, $date);

            if (count($reminders) > 0) {
                self::sendReminderEmail($id, $reminders);
            } else {
                continue;
            }

            $elapsed_time = time() - $start_time;
            if ($this->max_execution_time !== null && $elapsed_time > $this->max_execution_time - 5) {
                break;
            }
        }
    }

    public static function sendReminderEmail($id, $reminders)
    {
        if (waConfig::get('is_template')) {
            return;
        }
        $contact = new waContact($id);
        $email = $contact->get('email', 'default');

        if ($email === '') {
            return false;
        };

        $user_name = $contact->get('name', true);
        $now = date('Y-m-d');
        $timezone = date_default_timezone_get();

        $view = wa()->getView();
        $view->assign(array(
            'reminders' => $reminders,
            'userName'  => $user_name,
            'now'       => $now,
            'timezone'  => $timezone,
        ));
        $result = $view->fetch(wa('crm')->getConfig()->getAppPath().'/lib/config/data/templates/reminder_daily.html');

        $m = new waMailMessage();
        $m->setTo($email);
        $m->setSubject(_w('Reminders daily recap'));
        $m->setBody($result);
        $m->send();

        $contact->setSettings('crm', "reminder_send_date", date('Y-m-d H:i:s'));

        if (waSystemConfig::isDebug()) {
            waLog::log("For a user with an ID {$id}, a reminder with notifications sent", self::$LOG_FILE);
        }

        return true;
    }

    protected static function getDateReminders($type)
    {
        $timeNow = date('H:i:s');
        $timeAM = '12:00:00';

        if ($timeNow < $timeAM) {
            $dateToday = date('Y-m-d');
            $dateTomorrow = date('Y-m-d', strtotime('+1 day'));
        } else {
            $dateToday = date('Y-m-d', strtotime('+1 day'));
            $dateTomorrow = date('Y-m-d', strtotime('+2 day'));
        };

        if ($type === 'today') {
            return $dateToday;
        } elseif ($type === 'today-tomorrow') {
            return $dateTomorrow;
        } else {
            return false;
        }
    }


}
