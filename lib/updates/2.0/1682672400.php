<?php

/**
 * 2023-04-28 12:00:00+03
 */

$m = new waModel();

try {
    $m->exec("SELECT last_log_id FROM crm_deal WHERE 0 LIMIT 0");
} catch (Exception $e) {
    $m->exec("ALTER TABLE `crm_deal` ADD `last_log_id` BIGINT NULL DEFAULT NULL");
}

try {
    $m->exec("SELECT last_log_datetime FROM crm_deal WHERE 0 LIMIT 0");
} catch (Exception $e) {
    $m->exec("ALTER TABLE `crm_deal` ADD `last_log_datetime` DATETIME NULL DEFAULT NULL");
    $m->exec(
        "UPDATE crm_deal
        INNER JOIN (
            SELECT contact_id, MAX(id) last_id, MAX(create_datetime) last_datetime
            FROM crm_log
            WHERE contact_id < 0
            GROUP BY contact_id
        ) AS t ON t.contact_id = -crm_deal.id
        SET last_log_id = t.last_id, last_log_datetime = t.last_datetime"
    );
}
