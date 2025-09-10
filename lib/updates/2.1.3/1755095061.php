<?php

$m = new waModel();

try {
    $m->query('SELECT `is_archived` FROM `crm_funnel` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_funnel` ADD `is_archived` TINYINT(1) NOT NULL DEFAULT 0");
}
