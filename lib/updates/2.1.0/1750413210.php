<?php

$_m = new waModel();

// new right 'export'
// grant full access to contacts and deals export for users that already has access to CRM app
// BUT if anyone has access right 'export', than skip granting (supposed meta up has be applied)
$_cnt = $_m->query("SELECT COUNT(*) FROM `wa_contact_rights` WHERE `app_id`='crm' AND `name`='export'")->fetchField();
if ($_cnt <= 0) {
    $_m->exec("INSERT IGNORE INTO `wa_contact_rights` (`group_id`, `app_id`, `name`, `value`)
                SELECT `group_id`, `app_id`, 'export', '1'
                FROM `wa_contact_rights` 
                WHERE `app_id`='crm' AND `name`='backend'
    ", crmRightConfig::RIGHT_CALL_ALL);
}
