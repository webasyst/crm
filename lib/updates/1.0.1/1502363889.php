<?php

$m = new waModel();

try {
    $m->exec("ALTER TABLE `crm_invoice_items` ADD INDEX `invoice_id` (`invoice_id`)");
} catch (Exception $e) {
}

try {
    $m->exec("DELETE FROM `crm_invoice_params` WHERE name=''");
    $m->exec("ALTER TABLE `crm_invoice_params` ADD UNIQUE `invoice_name` (`invoice_id`, `name`)");
} catch (Exception $e) {
}

try {
    $m->exec("ALTER TABLE `crm_message` ADD INDEX `deal_id` (`deal_id`)");
} catch (Exception $e) {
}

try {
    $m->exec("ALTER TABLE `crm_message_attachments` ADD INDEX `message_id` (`message_id`)");
    $m->exec("ALTER TABLE `crm_message_attachments` ADD UNIQUE `message_file` (`message_id`, `file_id`)");
} catch (Exception $e) {
}

try {
    $m->exec("SELECT * FROM crm_deal_params WHERE 0");
} catch (Exception $e) {
    $m->exec("CREATE TABLE `crm_deal_params` (
                `deal_id` INT NOT NULL,
                `name` varchar(64) NOT NULL,
                `value` varchar(255) NOT NULL,
                PRIMARY KEY (`deal_id`, `name`)
             )");
}
