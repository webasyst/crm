<?php

$m = new waModel();

try {
    $m->query('SELECT `event` FROM `crm_message` WHERE 0');
} catch (waDbException $e) {
    $m->exec('ALTER TABLE `crm_message` ADD COLUMN `event` VARCHAR (64) NULL DEFAULT NULL');
}
