<?php

$m = new waModel();

try {
    $m->query('SELECT `tax_type` FROM `crm_invoice_items` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_invoice_items` ADD `tax_type` enum('INCLUDE', 'APPEND', 'NONE') not null default 'NONE'");
    $m->exec("ALTER TABLE `crm_invoice_items` ADD `tax_percent` decimal(5,2) not null default 0");
    $m->exec("UPDATE crm_invoice_items ii
                    JOIN crm_invoice i
                      ON ii.invoice_id = i.id
                        SET ii.tax_type = i.tax_type,
                            ii.tax_percent = i.tax_percent
    ");

    $m->exec("UPDATE wa_contact_rights SET value = '2' WHERE name = 'manage_invoices' AND value = '1'"); // from task #83.3930
};
