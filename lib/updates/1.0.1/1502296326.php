<?php

$app_root_folder = wa()->getDataPath('', false, 'crm');
if (file_exists($app_root_folder) && file_exists($app_root_folder.'/crm') && is_dir($app_root_folder.'/crm')) {
    foreach (waFiles::listdir($app_root_folder.'/crm') as $file) {
        @waFiles::move($app_root_folder.'/crm/' . $file, $app_root_folder.'/'.$file);
    }
    try {
        waFiles::delete($app_root_folder . '/crm');
    } catch (Exception $e) {

    }
}
