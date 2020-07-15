<?php

$m = new waModel();

try {
    $m->query('SELECT `recipient` FROM `crm_notification` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_notification` ADD `recipient` VARCHAR(255) NOT NULL DEFAULT 'client'");
}
try {
    $m->query('SELECT `sender` FROM `crm_notification` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_notification` ADD `sender` varchar(255) DEFAULT 'system' NOT NULL");
}
