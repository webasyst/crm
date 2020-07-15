<?php

$_files = array(

    // contact(s) related classes

    'crmContact.class.php',
    'crmContactsAction.class.php',
    'crmContactsCollection.class.php',
    'crmContactsCompositeCollection.class.php',
    'crmContactsExporter.class.php',
    'crmContactViewAction.class.php',

    // deal(s) related classes

    'crmDeal.class.php',
    'crmDealsExporter.class.php',

    // view related classes

    'crmViewAction.class.php',
    'crmViewHelper.class.php',

    // reminder(s) related classes

    'crmReminder.class.php',
    'crmRemindersRecap.class.php',

    // notification(s) related classes,

    'crmNotification.class.php',
    'crmNotificationBirthdayWorker.class.php'

);

$_dir = wa()->getAppPath('lib/classes/', 'crm');

foreach ($_files as $_file) {
    $_file_path = $_dir . $_file;
    if (file_exists($_file_path)) {
        try {
            waFiles::delete($_file_path);
        } catch (Exception $e) {}
    }
}

waAppConfig::clearAutoloadCache('crm');
