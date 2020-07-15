<?php

$m = new waModel();

try {
    $m->exec("SELECT `create_datetime` FROM `crm_deal_participants` LIMIT 0");
} catch (Exception $e) {
    $m->exec("ALTER TABLE `crm_deal_participants` ADD `create_datetime` DATETIME NOT NULL DEFAULT '2017-10-24'");
    $m->exec("UPDATE crm_deal_participants p
        INNER JOIN crm_deal d ON d.id = p.deal_id
        SET p.create_datetime = d.create_datetime");
}
