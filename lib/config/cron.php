<?php

return [
    'sources' => [
        'expression' => '*/5 * * * *', // every 5 minutes
        'action' => 'worker',
        'name' => _w('Check email sources'),
        'legacy_cli' => 'sources',
        'description_success' => _w('A cron job for automatically fetching new mail and sending web push notifications is set up.'),
        'description_warning' => _w('You do not have cron jobs set up which are required for automatically fetching new mail and sending reminders web push notifications. Without the cron setup, new mail is fetched from mailboxes only when the CRM app is opened in a browser.'),
        'params' => [
            'action' => 'sources'
        ]
    ],
    'deal-stages-overdue' => [
        'expression' => '44 * * * *', // every hour at 44 minutes
        'action' => 'worker',
        'name' => _w('Deal stages overdue'),
        'legacy_cli' => 'deal_stages_overdue',
        'description_success' => _w('A cron job to mark overdue deals is set up.'),
        'description_warning' => _w('A cron job to automatically mark overdue deals must be set up.'),
        'params' => [
            'action' => 'deal_stages_overdue'
        ]
    ],
    'invoice-daily' => [
        'expression' => '15 10 * * *', // every day at 10:15
        'action' => 'worker',
        'name' => _w('Daily invoices job'),
        'legacy_cli' => 'invoices_archive',
        'description_success' => _w('A job to archive expired invoices and issue recurring invoices is executed.'),
        'description_warning' => _w('A job to archive expired invoices and issue recurring invoices must be set up.'),
        'params' => [
            'action' => 'invoice-daily'
        ]
    ],
    'currencies-copy' => [
        'expression' => '25 14 * * *', // every day at 14:25
        'action' => 'worker',
        'name' => _w('Currencies copying'),
        'legacy_cli' => 'currencies_copy',
        'description_success' => _w('A currencies copying job is executed.'),
        'description_warning' => _w('A cron job to automatically copy currencies must be set up.'),
        'params' => [
            'action' => 'currencies_copy'
        ]
    ],
    'reminders-recap' => [
        'expression' => '30 9 * * *', // every day at 9:30
        'action' => 'worker',
        'name' => _w('Reminders recap'),
        'legacy_cli' => 'reminders_recap',
        'description_success' => _w('A reminders recap cron job is set up.'),
        'description_warning' => _w('A reminders recap cron job must be set up.'),
        'params' => [
            'action' => 'reminders_recap'
        ]
    ],
    'birthday' => [
        'expression' => '30 10 * * *', // every day at 10:30
        'action' => 'worker',
        'name' => _w('Clients’ birthdays'),
        'legacy_cli' => 'birthday',
        'description_success' => _w('A clients’ birthday notifications cron job is set up.'),
        'description_warning' => _w('A clients’ birthday notifications cron job must be set up.'),
        'params' => [
            'action' => 'birthday'
        ]
    ],
];
