<?php
$m = new waModel();
$exists = $m->query("SELECT name
                    FROM `wa_contact_settings`
                    WHERE `app_id` = 'crm'
                        AND name = 'contact_create_auto_responsible'
                    LIMIT 1")
    ->fetchField();
if ($exists) {
    $m->exec("UPDATE `wa_contact_settings`
                SET `name` = 'contact_create_not_responsible',
                    `value` = 1
                WHERE `app_id` = 'crm'
                    AND `name` = 'contact_create_auto_responsible'
                    AND `value` = 0");
    $m->exec("DELETE FROM `wa_contact_settings`
              WHERE `app_id` = 'crm'
                  AND `name` = 'contact_create_auto_responsible'");
}