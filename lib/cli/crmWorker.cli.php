<?php

/*
 * Usage example: php cli.php crm worker [task(s)]
 */

class crmWorkerCli extends waCliController
{
    public function execute()
    {
        $tasks = array_flip(array_filter(explode(',', join(',', waRequest::param())))); // wow @ such lisp @ much parens

        // STRICTLY DON'T TOUCH IT
        // 'email' - is old alias for backward compatibility
        if (!$tasks || isset($tasks['source']) || isset($tasks['sources']) || isset($tasks['email'])) {
            crmSourceWorker::cliRun();
            crmRemindersPush::cliRun();
        }

        if (!$tasks || isset($tasks['birthday']) || isset($tasks['bday'])) {

            $should_run = true;

            // When no tasks specified or 'bday' alias used, apply time limits
            $app_settings_model = new waAppSettingsModel();
            $prev_start = $app_settings_model->get('crm', 'notification_birthday_cli_start');
            if ($prev_start && strtotime($prev_start) + 30*60 > time()) {
                $should_run = false;
            }
            $prev_end = $app_settings_model->get('crm', 'notification_birthday_cli_end');
            if ($prev_end && strtotime($prev_end) + 7*60*60 > time()) {
                $should_run = false;
            }

            // When 'birthday' alias used, run unconditionally
            $should_run = $should_run || isset($tasks['birthday']);

            if ($should_run) {
                crmNotificationBirthdayWorker::cliRun();
            }
        }

        if (!$tasks || isset($tasks['calls'])) {
            crmPbxActions::cleanUpCalls();
        }

        if (!$tasks || isset($tasks['reminders_recap'])) {
            crmRemindersRecap::cliRun();
        }

        if (!$tasks || isset($tasks['invoices_archive'])) {
            crmInvoice::cliInvoicesArchive();
        }

        if (!$tasks || isset($tasks['currencies_copy'])) {
            crmShop::cliCurrenciesCopy();
        }

        if (!$tasks || isset($tasks['deal_stages_overdue'])) {
            crmDeal::cliOverdue();
        }
    }
}
