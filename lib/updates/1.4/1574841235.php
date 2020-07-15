<?php

$_m = new waModel();

try {
    $_m->query("SELECT `company_id` FROM `crm_notification` WHERE 0");
} catch (waDbException $e) {
    $_m->exec("ALTER TABLE `crm_notification` ADD COLUMN `company_id` INT(11) NULL DEFAULT NULL");
}


