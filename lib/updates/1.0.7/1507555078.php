<?php

$m = new waModel();

try {
    $m->query('SELECT `template_id` FROM `crm_company` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_company` ADD `template_id` INT NULL DEFAULT NULL");
    $m->exec("UPDATE `crm_company` SET `template_id` = 1 WHERE `invoice_template_id` = 'a' OR `invoice_template_id` IS NULL");
    $m->exec("UPDATE `crm_company` SET `template_id` = 2 WHERE `invoice_template_id` = 'b'");
    $m->exec("ALTER TABLE `crm_company` MODIFY template_id INT NOT NULL");
    $m->exec("ALTER TABLE `crm_company` DROP `invoice_template_id`");
};
