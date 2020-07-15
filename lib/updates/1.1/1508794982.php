<?php

$m = new waModel();

$table = 'crm_deal_params';

$meta = $m->describe($table);
if ($meta['value'] && $meta['value']['type'] === 'varchar') {
    $m->exec("ALTER TABLE `{$table}` CHANGE `value` `value` TEXT DEFAULT NULL");
    $m->exec("ALTER TABLE `{$table}` CHANGE `name` `name` VARCHAR(255) NOT NULL");
}
