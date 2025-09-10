<?php

$m = new waModel();

try {
    $m->query('SELECT `icon` FROM `crm_funnel` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_funnel` ADD `icon` VARCHAR(255) DEFAULT NULL AFTER `sort`");
}
