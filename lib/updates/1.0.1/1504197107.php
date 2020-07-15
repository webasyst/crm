<?php

$m = new waModel();
try {
    $field = $m->query('SHOW FIELDS FROM crm_deal WHERE Field="external_id"')->fetchAssoc();
    if (strtolower(substr($field['Type'], 0, 3)) === 'int') {
        $m->exec("ALTER TABLE `crm_deal` CHANGE `external_id` `external_id` VARCHAR(255) NULL DEFAULT NULL");
    }
} catch (Exception $e) {
}
