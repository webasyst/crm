<?php

$m = new waModel();

try {
    $m->exec("SELECT `external_id` FROM `crm_deal`");
} catch (Exception $e) {
    $m->exec("ALTER TABLE `crm_deal` ADD `external_id` VARCHAR(255) NULL AFTER `source_id`,
    ADD INDEX `external_id` (`external_id`)");
}
