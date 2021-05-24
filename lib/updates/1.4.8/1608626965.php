<?php

$model = new waModel();

try {
    $model->exec("SELECT `funnel_id` FROM `crm_deal_lost` where 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `crm_deal_lost` ADD `funnel_id` INT NOT NULL DEFAULT '0' AFTER `id`");
    $model->exec("ALTER TABLE `crm_deal_lost` ADD INDEX `funnel_id` (`funnel_id`)");
}