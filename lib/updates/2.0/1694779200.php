<?php
/**
 * 2023-09-15 15:00:00+03
 */

$_model = new waModel();

try {
    $_model->query("SELECT push_sent FROM crm_reminder WHERE 0");
} catch (waDbException $e) {
    $_model->exec("ALTER TABLE crm_reminder ADD COLUMN push_sent TINYINT(1) NOT NULL DEFAULT 0");
}
