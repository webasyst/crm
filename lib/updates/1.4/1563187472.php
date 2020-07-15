<?php

// These dialogs are dead
$_file_paths = array(
    wa()->getAppPath('lib/actions/message/crmMessageByClientDialog.action.php', 'crm'),
    wa()->getAppPath('lib/actions/message/crmMessageByDealDialog.action.php', 'crm'),
    wa()->getAppPath('templates/actions/message/MessageByClientDialog.html', 'crm'),
    wa()->getAppPath('templates/actions/message/MessageByClientSmsDialog.html', 'crm'),
    wa()->getAppPath('templates/actions/message/MessageByDealDialog.html', 'crm'),
);

foreach ($_file_paths as $_file_path) {
    if (file_exists($_file_path)) {
        try {
            waFiles::delete($_file_path);
        } catch (Exception $e) {
        }
    }
}
