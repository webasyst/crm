<?php

$m = new waModel();

try {
    $m->query('SELECT `color` FROM `crm_tag` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_tag` ADD `color` VARCHAR(13) DEFAULT NULL AFTER `name`");
}
