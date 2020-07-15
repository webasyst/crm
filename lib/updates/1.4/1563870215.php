<?php

$_m = new waModel();

// new right 'calls'
// grant full access to 'calls' for users that already has access to CRM app
// BUT if anyone has access right 'calls', than skip granting (supposed meta up has be applied)
$_cnt = $_m->query("SELECT COUNT(*) FROM `wa_contact_rights` WHERE `app_id`='crm' AND `name`='calls'")->fetchField();
if ($_cnt <= 0) {
    $_m->exec("INSERT IGNORE INTO `wa_contact_rights` (`group_id`, `app_id`, `name`, `value`)
                SELECT `group_id`, `app_id`, 'calls', ?
                FROM `wa_contact_rights` 
                WHERE `app_id`='crm' AND `name`='backend'
    ", crmRightConfig::RIGHT_CALL_ALL);
}

// new rights - access to conversations
// grant access to conversations for users that already has access to CRM app
// BUT if anyone has already that right in DB, than skip granting (supposed meta up has be applied)
$_cnt = $_m->query("SELECT COUNT(*) FROM `wa_contact_rights` WHERE `app_id`='crm' AND `name` = 'conversations'")->fetchField();
if ($_cnt <= 0) {
    $_m->exec("INSERT IGNORE INTO `wa_contact_rights` (`group_id`, `app_id`, `name`, `value`)
                SELECT `group_id`, `app_id`, 'conversations', ?
                FROM `wa_contact_rights` 
                WHERE `app_id`='crm' AND `name`='backend'
    ", crmRightConfig::RIGHT_CONVERSATION_OWN_OR_FREE);
}
