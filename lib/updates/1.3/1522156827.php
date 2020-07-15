<?php

// update to reset captcha config for CRM app

$_crm_config_path = wa('crm')->getConfig()->getConfigPath('config.php');
if (file_exists($_crm_config_path)) {
    $_config = include($_crm_config_path);
    if (isset($_config['factories']) &&
        is_array($_config['factories']) &&
        isset($_config['factories']['captcha']) &&
        !empty($_config['factories']['captcha'])) {
            unset($_config['factories']['captcha']);
        waUtils::varExportToFile($_config, $_crm_config_path);
    }
}
