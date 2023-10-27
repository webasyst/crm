<?php

class crmRemindersPush
{
    public static function cliRun($options = [])
    {
        $reminders_push_sent = [];
        $now = strtotime('now');
        $asm = new waAppSettingsModel();
        $reminder_push_start_ts = (int) $asm->get('crm', 'reminder_push_start_ts');
        $reminder_push_end_ts = (int) $asm->get('crm', 'reminder_push_end_ts');

        /** Предохранитель от параллельного выполнения */
        if (!empty($reminder_push_end_ts) && ($now - $reminder_push_end_ts < 120)) {
            /** Выполняем не чаще раз в две минуты */
            return;
        } elseif (!empty($reminder_push_start_ts) && ($now - $reminder_push_start_ts < 300)) {
            /** Параллельный процесс еще выполняется или помер. Таймаут 5 минут */
            return;
        }
        $asm->set('crm', 'reminder_push_end_ts', '');
        $asm->set('crm', 'reminder_push_start_ts', $now);

        list($user_minutes, $disabled_ids) = self::getSettingsPopupMin();
        $left_date = date('Y-m-d H:i:s', $now);
        $right_date = date('Y-m-d H:i:s', strtotime('now +'.max($user_minutes).' minutes'));
        $reminder_model = new crmReminderModel();
        $reminders = $reminder_model->select('id,user_contact_id,contact_id,due_datetime,type,content')
            ->where('complete_datetime IS NULL')
            ->where('due_datetime IS NOT NULL')
            ->where('push_sent = 0')
            ->where('user_contact_id NOT IN ('.implode(',',$disabled_ids).')')
            ->where('due_datetime BETWEEN s:left AND s:right', ['left' => $left_date, 'right' => $right_date])
            ->fetchAll('id');

        $user_ids = array_column($reminders, 'user_contact_id');
        if (empty($user_ids)) {
            self::finish($asm);
            return;
        }
        $user_ids = array_unique($user_ids);
        $contact_ids = array_column($reminders, 'contact_id');
        $contact_ids = array_unique($contact_ids);
        $contact_ids = array_merge($contact_ids, $user_ids);
        $contacts = (new waContactModel)->getByField(['id' => $contact_ids], 'id');
        if (empty($contacts)) {
            self::finish($asm);
            return;
        }

        $push_service = new crmPushService();
        foreach ($reminders as $_reminder_id => $_reminder) {
            if (!ifset($contacts, $_reminder['user_contact_id'], false)) {
                continue;
            }
            $now = strtotime($_reminder['due_datetime']);
            $pop_up_min = ifempty($user_minutes, $_reminder['user_contact_id'], crmReminderModel::POP_UP_MIN);
            $minutes = floor((strtotime($_reminder['due_datetime']) - $now) / 60);
            if ($minutes > $pop_up_min) {
                continue;
            }
            $reminders_push_sent[] = $_reminder_id;
            $push_service->notifyAboutreminder(
                $_reminder, 
                $contacts[$_reminder['user_contact_id']], 
                ifset($contacts, $_reminder['contact_id'], new waContact())
            );
        }
        $reminder_model->updateById($reminders_push_sent, ['push_sent' => 1]);

        self::finish($asm);
    }

    protected static function finish(waAppSettingsModel $asm)
    {
        $asm->set('crm', 'reminder_push_start_ts', '');
        $asm->set('crm', 'reminder_push_end_ts', time());
        if (wa()->getEnv() === 'cli') {
            $asm->set('crm', 'reminder_push_cli_done', date('Y-m-d H:i:s'));
        }
    }

    public static function getLastCliRunDateTime()
    {
        $sm = new waAppSettingsModel();
        return $sm->get('crm', 'reminder_push_cli_done');
    }

    public static function isCliOk()
    {
        return !!self::getLastCliRunDateTime();
    }

    private static function getSettingsPopupMin()
    {
        $user_minutes = [ 0 => crmReminderModel::POP_UP_MIN ];
        $disabled_ids = [ 0 ];
        $settings = (new waContactSettingsModel())->select('contact_id, name, value')
            ->where('app_id = ?', 'crm')
            ->where('name IN ("reminder_pop_up_disabled", "reminder_pop_up_min")')
            ->fetchAll();
        foreach ($settings as $_settings) {
            if ($_settings['name'] === 'reminder_pop_up_disabled' && $_settings['value'] === '1') {
                $disabled_ids[] = (int) $_settings['contact_id'];
                continue;
            }
            $user_minutes[$_settings['contact_id']] = (int) $_settings['value'];
        }

        return [$user_minutes, $disabled_ids];
    }
}
