<?php

// just copy for now, for current update
// TODO: delete old folder in future update
$app_root_folder = wa()->getDataPath('', false, 'crm');
if (file_exists($app_root_folder) && file_exists($app_root_folder.'/crm') && is_dir($app_root_folder.'/crm')) {
    foreach (waFiles::listdir($app_root_folder.'/crm') as $file) {
        try {
            @waFiles::copy($app_root_folder . '/crm/' . $file, $app_root_folder . '/' . $file);
        } catch (Exception $e) {

        }
    }
}
