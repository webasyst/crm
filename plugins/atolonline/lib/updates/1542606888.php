<?php

$m = new waModel();

try {
    $m->query('SELECT `error_id` FROM `crm_atolonline_receipt` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_atolonline_receipt` ADD `error_id` VARCHAR(64) NULL AFTER `status`");
};
