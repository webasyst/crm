<?php
/**
 * 2023-02-20 15:00:00+03
 */

$_model = new waModel();

try {
    $_model->query("SELECT `params` FROM `crm_log` WHERE 0");
} catch (waDbException $e) {
    $_model->exec("ALTER TABLE `crm_log` ADD COLUMN `params` TEXT NULL DEFAULT NULL");
    $_model->exec("
        UPDATE crm_log
        SET `params` = CONCAT('{\"contact_id\": ', object_id, '}'), object_id = ABS(contact_id) 
        WHERE `action` IN ('deal_addcontact', 'deal_removecontact')
    ");
}
