<?php

class crmCronjobsStartController extends crmJsonController
{
    public function execute()
    {
        wa()->getStorage()->close();

        /**
         * @event start_notification_birthday_worker
         */
        wa('crm')->event('start_notification_birthday_worker');

        /**
         * @event start_reminders_recap_worker
         */
        wa('crm')->event('start_reminders_recap_worker');

        /**
         * @event start_deal_stages_overdue_worker
         */
        wa('crm')->event('start_deal_stages_overdue_worker');

        /**
         * @event start_invoices_archive_worker
         */
        wa('crm')->event('start_invoices_archive_worker');

        /**
         * @event start_currencies_copy_worker
         */
        wa('crm')->event('start_currencies_copy_worker');
    }
}
