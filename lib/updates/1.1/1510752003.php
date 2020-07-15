<?php

// files to delete
$_file_paths = array();

// delete email signature old related files
$_file_paths[] = wa()->getAppPath('lib/actions/email/crmEmailSignatureDialog.action.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/email/crmEmailSignatureSave.controller.php', 'crm');
$_file_paths[] = wa()->getAppPath('templates/actions/email/EmailSignatureDialog.html', 'crm');

// delete form old related files (after refactoring)
$_file_paths[] = wa()->getAppPath('lib/classes/form/crmFormAgreementCheckboxField.class.php', 'crm');
$_file_paths[] = wa()->getAppPath('templates/form/date.datepicker.html', 'crm');
$_file_paths[] = wa()->getAppPath('templates/actions/settings/SettingsForm.inc.html', 'crm');

foreach ($_file_paths as $_file_path) {
    if (file_exists($_file_path)) {
        try {
            waFiles::delete($_file_path);
        } catch (Exception $e) {
        }
    }
}

waAppConfig::clearAutoloadCache('crm');
