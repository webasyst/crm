<?php

$m = new waModel();

try {
    $m->query("SELECT report FROM crm_reminder WHERE 0");
} catch (Exception $e) {
    $m->exec("ALTER TABLE `crm_reminder` ADD `report` TEXT DEFAULT NULL AFTER `content`");
}
