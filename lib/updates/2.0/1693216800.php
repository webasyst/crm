<?php
/**
 * 2023-08-28 13:00:00+03
 */

$_model = new waModel();

try {
    $_model->query("ALTER TABLE crm_message MODIFY body MEDIUMTEXT NOT NULL");
} catch (waDbException $e) {
    //
}
