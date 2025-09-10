<?php

$m = new waModel();

try {
    $m->query('SELECT `origin_id` FROM `crm_template` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_template` ADD `origin_id` INT(11) DEFAULT NULL");
}

try {
    $m->query('SELECT `style_version` FROM `crm_template` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_template` ADD `style_version` INT(11) NOT NULL DEFAULT 1");
}
