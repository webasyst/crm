<?php

$m = new waModel();

try {
    $m->query('SELECT `sort` FROM `crm_invoice_items` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_invoice_items` ADD `sort` INT(11) NOT NULL DEFAULT 0 AFTER `product_id`");
}
