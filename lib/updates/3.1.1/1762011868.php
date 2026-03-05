<?php

$_installer = new crmInstaller();
$_installer->createTable('crm_invoice_recurrent');

$m = new waModel();

try {
    $m->query("SELECT recurrent_id FROM crm_invoice WHERE 0");
} catch (Exception $e) {
    $m->exec("ALTER TABLE `crm_invoice` 
        ADD `recurrent_id` INT(11) DEFAULT NULL AFTER `comment`,
        ADD KEY `recurrent_id` (`recurrent_id`)");

    $m->exec("ALTER TABLE `crm_invoice` 
        CHANGE `number` `number` VARCHAR(32) DEFAULT NULL");
}
