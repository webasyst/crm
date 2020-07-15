<?php

$_m = new waModel();

$_columns = $_m->query("SHOW COLUMNS FROM `crm_log`")->fetchAll('Field');
$_type = $_columns['object_type']['Type'];
if (strstr($_type, 'ORDER_LOG') !== false) {
    return; // no need to update
}

// update `object_type` ENUM list
$_m->exec("
      ALTER TABLE `crm_log` CHANGE `object_type` `object_type`
          ENUM('CONTACT','DEAL','INVOICE','REMINDER','NOTE','FILE','CALL','EMAIL','MESSAGE','ORDER_LOG')
          NOT NULL;
    ");
