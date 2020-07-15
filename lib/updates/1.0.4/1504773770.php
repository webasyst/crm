<?php

$m = new waModel();

try {
    $m->query('SELECT `product_id` FROM `crm_invoice_items` WHERE 0');
} catch (waDbException $e) {
    $m->exec('ALTER TABLE `crm_invoice_items` ADD `product_id` VARCHAR(255) NULL AFTER `quantity`');
}
