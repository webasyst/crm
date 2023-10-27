<?php

$sm = new waAppSettingsModel();

$renames = array(
    'source_worker_cli_start' => 'source_worker_start',
    'source_worker_cli_end' => 'source_worker_end'
);
foreach ($renames as $from => $to) {
    try {
        $sm->updateByField(
            array('app_id' => 'crm', 'name' => $from),
            array('app_id' => 'crm', 'name' => $to)
        );
    } catch (waDbException $e) {
        // already renamed, continue
    }
}
