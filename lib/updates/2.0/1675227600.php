<?php
/**
 * 2023-02-01 10:00:00
 */

$_model = new waModel();

try {
    $_model->query('ALTER TABLE crm_reminder MODIFY due_date date NULL');
} catch (waDbException $e) {
}
