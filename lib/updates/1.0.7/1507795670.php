<?php

$tpm = new crmTemplatesParamsModel();
$meta_data = $tpm->getMetadata();

$match = strripos($meta_data['type']['params'], '\'IMAGE\'');

if (!$match) {
    $tpm->exec("ALTER TABLE `crm_template_params` MODIFY `type` enum('STRING', 'COLOR', 'NUMBER', 'IMAGE') NOT NULL DEFAULT 'STRING'");
}
