<?php

$m = new waModel();

try {
    $m->query('SELECT `id` FROM `crm_template` WHERE 0');
} catch (waDbException $e) {
    $m->exec("CREATE TABLE IF NOT EXISTS crm_template (
    id int PRIMARY KEY AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    content text NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8");
};

try {
    $m->query('SELECT `template_id` FROM `crm_template_params` WHERE 0');
} catch (waDbException $e) {
    $m->exec("CREATE TABLE IF NOT EXISTS crm_template_params (
                    `template_id` int NOT NULL,
                    `code` varchar(64) NOT NULL,
                    `name` varchar(255) NOT NULL,
                    `placeholder` varchar(255),
                    `type` enum('STRING', 'COLOR', 'NUMBER') DEFAULT 'STRING' NOT NULL,
                    `sort` int(11) NOT NULL,
                   PRIMARY KEY (`template_id`,`code`)
              ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

    // insert initial templates
    $installer = new crmInstaller();
    $installer->installTemplates();
}