<?php

$m = new waModel();
try {
    $m->query("SELECT `last_message_id` FROM `crm_deal` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_deal` ADD COLUMN `last_message_id` INT(11) NULL DEFAULT NULL");
}
