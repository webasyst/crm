<?php

$cm = new crmCompanyModel();

try {
    $cm->query('SELECT `template_id` FROM `crm_company_params` WHERE 0');
} catch (waDbException $e) {
    $cm->exec("CREATE TABLE IF NOT EXISTS `crm_company_params` (
                      `company_id`  int          NOT NULL,
                      `template_id` int          NOT NULL,
                      `name`        varchar(255) NOT NULL,
                      `value`       varchar(255),
                      PRIMARY KEY (`company_id`, `template_id`, `name`)
                    ) ENGINE=MyISAM DEFAULT CHARSET=utf8"
    );

    $cpm = new crmCompanyParamsModel();
    $companies = $cm->getAll();
    $insertParams = array();
    foreach ($companies as $company) {

        if(isset($company['invoice_options'])) {
            $json = json_decode($company['invoice_options'], true);
            if (is_array($json)) {
                foreach ($json as $code => $value) {
                    $insertParams[] =  array(
                        'company_id'  => (int) $company['id'],
                        'template_id' => (int) $company['template_id'],
                        'name'        => $code,
                        'value'       => $value,
                    );
                }
            }
        }
        $cm->updateById($company['id'], array('invoice_options' => $company['template_id']));
    }

    $cpm->multipleInsert($insertParams);
};

try {
    $cm->query('SELECT `invoice_options` FROM `crm_company` WHERE 0');
    $cm->exec('ALTER TABLE `crm_company` DROP `invoice_options`');

} catch (waDbException $e) {

}