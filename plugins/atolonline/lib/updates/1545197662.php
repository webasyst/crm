<?php

$m = new waModel();

try {
    $m->exec("SELECT `perefix_id` FROM `crm_atolonline_receipt` LIMIT 0");
} catch (Exception $e) {
    $m->exec("ALTER TABLE `crm_atolonline_receipt` CHANGE `sid` `perefix_id` VARCHAR(255) NULL DEFAULT NULL");
}
