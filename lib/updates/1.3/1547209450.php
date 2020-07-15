<?php

// All files need be deleted in next app version (1.4)
$_file_paths = array();

// add_to_segment view-block related files
$_file_paths[] = wa()->getAppPath('lib/classes/source/settings/blocks/crmSourceSettingsAddToSegmentsViewBlock.class.php', 'crm');
$_file_paths[] = wa()->getAppPath('templates/source/settings/blocks/add_to_segments.html', 'crm');
$_file_paths[] = wa()->getAppPath('templates/source/settings/blocks/form_add_to_segments.html', 'crm');


foreach ($_file_paths as $_file_path) {
    if (file_exists($_file_path)) {
        try {
            waFiles::delete($_file_path);
        } catch (Exception $e) {
        }
    }
}

waAppConfig::clearAutoloadCache('crm');
