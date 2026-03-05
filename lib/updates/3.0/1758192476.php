<?php

$_installer = new crmInstaller();
$_installer->createTable('crm_notification_params');

$m = new waModel();

try {
    $m->exec("ALTER TABLE `crm_notification` CHANGE `transport` `transport` ENUM('email', 'sms', 'http', 'reminder') NOT NULL DEFAULT 'email'");
} catch (Exception $e) {
}
