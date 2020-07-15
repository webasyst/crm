<?php

$_installer = new crmInstaller();
$_installer->createTable('crm_deal_stages');

$m = new waModel();
try {
    $m->query("SELECT `limit_hours` FROM `crm_funnel_stage` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_funnel_stage` ADD `limit_hours` INT NULL");
}

try {
    $exists = $m->query("SELECT * FROM `crm_deal_stages` LIMIT 1")->fetchAssoc();
    if (!$exists) {
        $m->exec("INSERT INTO crm_deal_stages (deal_id, stage_id, in_datetime)
        SELECT id, stage_id, update_datetime FROM crm_deal WHERE status_id='OPEN'");
    }
} catch (Exception $e) {
}
