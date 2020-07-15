<?php

$m = new waModel();

try {
    $m->query('SELECT `receipt_data` FROM `crm_atolonline_receipt` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_atolonline_receipt` ADD `receipt_data` TEXT NOT NULL AFTER `operation`");
};

try {
    $m->query('SELECT `refund_id` FROM `crm_atolonline_receipt` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_atolonline_receipt` ADD `refund_id` INT NULL");
};
