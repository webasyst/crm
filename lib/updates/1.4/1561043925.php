<?php

$_file_paths = array(
    wa()->getAppPath('lib/config/data/templates/reminder_daily.en_US.html', 'crm'),
    wa()->getAppPath('lib/actions/message/crmMessageSendSms.controller.php', 'crm'),
    wa()->getAppPath('lib/actions/message/crmMessageWriteReplySmsDialog.action.php', 'crm')
);

foreach ($_file_paths as $_file_path) {
    if (file_exists($_file_path)) {
        try {
            waFiles::delete($_file_path);
        } catch (Exception $e) {
        }
    }
}
