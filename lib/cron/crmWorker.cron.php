<?php

class crmWorkerCron extends waCronExecutor
{
    public function execute($params)
    {
        if (empty($params['action'])) {
            // no action
            return;
        }

        if ($params['action'] == 'sources') {
            crmPbxActions::cleanUpCalls();
            crmSourceWorker::cliRun();
            crmRemindersPush::cliRun();
            return;
        }

        if ($params['action'] == 'birthday') {
            crmNotificationBirthdayWorker::cliRun();
            return;
        }

        if ($params['action'] == 'reminders_recap') {
            crmRemindersRecap::cliRun();
            return;
        }

        if ($params['action'] == 'invoice-daily') {
            crmInvoice::cliInvoicesArchive();
            crmInvoice::recurrentIssue();
            return;
        }

        if ($params['action'] == 'currencies_copy') {
            crmShop::cliCurrenciesCopy();
            return;
        }

        if ($params['action'] == 'deal_stages_overdue') {
            crmDeal::cliOverdue();
            return;
        }
    }
    
    public static function lastLegacyRunTs($task)
    {
        switch ($task) {
            case 'reminders_recap':
                return self::dt2ts(crmRemindersRecap::getLastCliRunDateTime());
            case 'currencies_copy':
                return self::dt2ts(crmShop::getLastCliRunDateTime());
            case 'deal_stages_overdue':
                return self::dt2ts(crmDeal::getLastCliRunDateTime());
            case 'invoices_archive':
                return self::dt2ts(crmInvoice::getLastCliRunDateTime());
            case 'birthday':
                return self::dt2ts(crmNotificationBirthdayWorker::getLastCliRunDateTime());
            case 'sources':
                return self::dt2ts(crmSourceWorker::getLastCliRunDateTime());
            default:
                return null;
        }
    }

    protected static function dt2ts($dt)
    {
        if (empty($dt)) {
            return null;
        }
        $ts = strtotime($dt);
        if (!$ts) {
            return null;
        }
        return $ts;
    }
}