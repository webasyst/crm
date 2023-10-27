<?php

$m = new waModel();

try {
    $m->exec("SELECT crm_last_log_id FROM wa_contact WHERE 0 LIMIT 0");
} catch (Exception $e) {
    $m->exec("ALTER TABLE wa_contact
              ADD crm_last_log_id BIGINT NULL DEFAULT NULL");
}

try {
    $m->exec("SELECT crm_last_log_datetime FROM wa_contact WHERE 0 LIMIT 0");
} catch (Exception $e) {
    $m->exec("ALTER TABLE `wa_contact`
              ADD `crm_last_log_datetime` DATETIME NULL DEFAULT NULL");
    $m->exec("UPDATE wa_contact 
            INNER JOIN (
                SELECT contact_id, MAX(id) last_id, MAX(create_datetime) last_datetime 
                FROM crm_log 
                GROUP BY contact_id
            ) AS t ON t.contact_id = wa_contact.id 
            SET crm_last_log_id = t.last_id, crm_last_log_datetime = t.last_datetime");
    $m->exec("UPDATE wa_contact 
            INNER JOIN (
                SELECT d.contact_id, MAX(l.id) last_id, MAX(l.create_datetime) last_datetime 
                FROM crm_log l
                INNER JOIN crm_deal d ON d.id = ABS(l.contact_id)
                WHERE l.contact_id < 0
                GROUP BY d.contact_id
            ) AS t ON t.contact_id = wa_contact.id 
            SET crm_last_log_id = t.last_id, crm_last_log_datetime = t.last_datetime
            WHERE crm_last_log_id IS NULL OR crm_last_log_id < t.last_id");
}
