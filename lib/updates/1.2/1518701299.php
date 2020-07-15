<?php

// files to delete after source refactoring
$_file_paths = array();

$_file_paths[] = wa()->getAppPath('lib/cli/crmInvoiceArchive.cli.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/cli/crmWorker.cli.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/cli', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/deal/crmDealMessageDialog.action.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/deal/crmDealMessageSend.controller.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/log/crmLogMessageDialog.action.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/log/crmLogMessageSend.controller.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/message/crmMessageListByClient.action.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/message/crmMessageListByDeal.action.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/message/crmMessageWriteReplyDialog.action.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/settings/source/crmSettingsSourceCreateDealBlock.action.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/settings/companies/crmSettingsCompanyLogoDelete.controller.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/settings/companies/crmSettingsCompanyLogoSave.controller.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/settings/source/crmSettingsSourceEmailTestConnection.controller.php', 'crm');

$_file_paths[] = wa()->getAppPath('lib/classes/crmMailIMAP.class.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/classes/source/crmSourceEmailMail.class.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/classes/source/crmSourceEmailWorker.class.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/classes/source/crmSourceEmailWorkerStrategy.class.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/classes/source/crmSourceEmailWorkerStrategyIncoming.class.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/classes/source/crmSourceEmailWorkerStrategyOutcoming.class.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/classes/source/crmSourceMailDecoder.class.php', 'crm');

$_file_paths[] = wa()->getAppPath('templates/actions/message/MessageListByClient.html', 'crm');
$_file_paths[] = wa()->getAppPath('templates/actions/message/MessageListByDeal.html', 'crm');
$_file_paths[] = wa()->getAppPath('templates/actions/settings/SettingsSourceId.html', 'crm');
$_file_paths[] = wa()->getAppPath('templates/actions/settings/SettingsSourcePOP3Block.inc.html', 'crm');

foreach ($_file_paths as $_file_path) {
    if (file_exists($_file_path)) {
        try {
            waFiles::delete($_file_path);
        } catch (Exception $e) {
        }
    }
}

waAppConfig::clearAutoloadCache('crm');
