<?php

$m = new waModel();

$shop_type_exists = false;
foreach ($m->query("DESCRIBE `crm_source`")->fetchAll() as $item) {
    if ($item['Field'] === 'type') {
        if (strpos($item['Type'], 'SHOP') !== false) {
            $shop_type_exists = true;
        }
        break;
    }
}

if (!$shop_type_exists) {
    $m->exec("ALTER TABLE `crm_source` CHANGE `type` `type` ENUM('EMAIL','FORM','SHOP') NOT NULL DEFAULT 'FORM'");
}
