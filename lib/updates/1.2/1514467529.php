<?php
$m = new waModel();

$_installer = new crmInstaller();
$_installer->createTable('crm_call_params');
$_installer->createTable('crm_pbx_users');
$_installer->createTable('crm_pbx_params');

try {
    $m->exec('SELECT * FROM `crm_pbx` WHERE `id` IS NOT NULL');
} catch (Exception $e) {
    $m->exec("ALTER TABLE `crm_pbx`
                  DROP PRIMARY KEY,
                  ADD `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;");
    $m->exec("CREATE UNIQUE INDEX `uniq` ON `crm_pbx` (plugin_id, plugin_user_number, contact_id);");
}

try {
    $m->exec("SELECT * FROM crm_pbx WHERE contact_id IS NOT NULL");
    $m->exec("INSERT IGNORE INTO crm_pbx_users (plugin_id, plugin_user_number, contact_id)
                    SELECT p.plugin_id, p.plugin_user_number, p.contact_id
                    FROM crm_pbx p");

    $m->exec("DROP TABLE crm_pbx");
    $_installer->createTable('crm_pbx');
    $m->exec("INSERT INTO crm_pbx (plugin_id, plugin_user_number) SELECT DISTINCT plugin_id, plugin_user_number FROM crm_pbx_users");
} catch (Exception $e) {}
