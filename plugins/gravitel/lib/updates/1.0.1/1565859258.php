<?php

$file_path = wa()->getAppPath('plugins/gravitel/lib/config/settings.php', 'crm');

if (file_exists($file_path)) {
    try {
        waFiles::delete($file_path);
    } catch (Exception $e) {}
}