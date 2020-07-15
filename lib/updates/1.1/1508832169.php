<?php

$m = new waModel();

try {
    $m->query('SELECT `sort` FROM `crm_company` WHERE 0');

} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_company` ADD `sort` int DEFAULT 0 NOT NULL");
    $m->exec("UPDATE `crm_company` SET `sort` = `id`");
}