<?php

$_installer = new crmInstaller();
$_installer->createTable('crm_message_params');

$m = $model = new waModel();

try {
    $m->query("SELECT `provider` FROM `crm_source` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_source` ADD COLUMN `provider` VARCHAR(64) NULL DEFAULT NULL AFTER `type`");
    $m->exec("ALTER TABLE `crm_source` CHANGE COLUMN `type` `type` ENUM('EMAIL','FORM','SHOP','SPECIAL','IM') NOT NULL DEFAULT 'SPECIAL'");
    $m->exec("ALTER TABLE `crm_source` CHANGE COLUMN `funnel_id` `funnel_id` INT(11) NULL DEFAULT NULL");
    $m->exec("ALTER TABLE `crm_source` CHANGE COLUMN `stage_id` `stage_id` INT(11) NULL DEFAULT NULL");
}

try {
    $m->query("SELECT `source_id` FROM `crm_message` WHERE 0");
} catch (waDbException $e) {
    $m->exec('ALTER TABLE `crm_message` ADD COLUMN `source_id` INT(11) NULL DEFAULT NULL AFTER `deal_id`');
    $m->exec("ALTER TABLE `crm_message` CHANGE COLUMN `transport` `transport` ENUM('EMAIL','SMS','IM') NOT NULL DEFAULT 'EMAIL'");
}



$type = $m->query("SHOW COLUMNS FROM `crm_message_recipients` WHERE FIELD = 'type'")->fetchField('Type');
preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
$enum = explode("','", $matches[1]);

if (!in_array("FROM", $enum)) {
    $m->query("ALTER TABLE `crm_message_recipients` MODIFY `type` enum('TO', 'CC', 'BCC', 'FROM') NOT NULL DEFAULT 'CC';");
}

$count = $m->query('SELECT * FROM `crm_message_recipients` WHERE `type` = "FROM"')->count();
if (!$count) {
    try {
        $m->query('SELECT `email` FROM `crm_message_recipients` WHERE 0');
        $m->query('
            INSERT IGNORE INTO crm_message_recipients (message_id, email, type, name, contact_id)
            SELECT m.id, e.email, "FROM", c.name, c.id
            FROM wa_contact c
            INNER JOIN crm_message m
              ON m.creator_contact_id=c.id
                AND m.transport="EMAIL"
                AND m.direction="OUT"
                AND m.event IS NULL
            INNER JOIN wa_contact_emails e
              ON e.contact_id=c.id
                AND e.sort=0');
    } catch (Exception $e) {}
}

try {
    $model->query("SELECT `destination` FROM `crm_message_recipients`");
} catch (waDbException $e) {
    $model->query("ALTER TABLE `crm_message_recipients` CHANGE COLUMN `email` `destination` VARCHAR(255) NOT NULL");
}

try {
    $model->exec("CREATE INDEX `contact_id` ON `crm_message_recipients` (`contact_id`)");
} catch (waDbException $e) {
    // index already exists
}



try {
    $m->exec("SELECT `conversation_id` FROM `crm_message` LIMIT 0");
} catch (Exception $e) {
    $m->exec("ALTER TABLE `crm_message` ADD `conversation_id` INT,
        ADD INDEX `conversation_id` (`conversation_id`)");
}

try {
    $m->exec("SELECT `id` FROM `crm_conversation` LIMIT 0");
} catch (Exception $e) {
    $installer = new crmInstaller();
    $installer->createTable('crm_conversation');

    $m->exec("INSERT INTO crm_conversation
        (create_datetime, update_datetime, source_id, type, contact_id, deal_id, user_contact_id, summary, last_message_id, count, is_closed)
        SELECT MIN(m.create_datetime), MAX(m.create_datetime), IFNULL(d.source_id, 0), 'EMAIL', d.contact_id, m.deal_id, d.user_contact_id, d.name, MAX(m.id), COUNT(*), 0
        FROM crm_message m
        INNER JOIN crm_deal d ON d.id=m.deal_id
        GROUP BY m.deal_id");

    $m->exec("UPDATE crm_message, crm_conversation SET crm_message.conversation_id=crm_conversation.id
        WHERE crm_message.deal_id=crm_conversation.deal_id");
}
