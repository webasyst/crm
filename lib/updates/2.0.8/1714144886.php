<?php

$m = new waModel();

try {
    $m->query('SELECT `source_type` FROM `crm_file` WHERE 0');
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `crm_file` ADD `source_type` enum('MESSAGE','NOTE','FILE') not null default 'FILE'");
    $m->exec("UPDATE crm_file 
        INNER JOIN crm_message_attachments ON crm_file.id = crm_message_attachments.file_id 
        INNER JOIN crm_message ON crm_message_attachments.message_id = crm_message.id
        SET crm_file.source_type = 'MESSAGE', crm_file.creator_contact_id = crm_message.creator_contact_id");
    $m->exec("UPDATE crm_file 
        INNER JOIN crm_message_attachments ON crm_file.id = crm_message_attachments.file_id 
        INNER JOIN crm_message ON crm_message_attachments.message_id = crm_message.id AND (crm_message.deal_id = 0 OR crm_message.deal_id IS NULL)
        SET crm_file.contact_id = crm_message.contact_id");

    $note_list = (new crmNoteModel)
        ->select('*')
        ->where("create_datetime > '2023-11-13 00:00:00'")
        ->order('create_datetime DESC')
        ->fetchAll();

    $nam = new crmNoteAttachmentsModel();
    $fm = new crmFileModel();
    $file_list = $fm->select('*')
        ->where("source_type != 'MESSAGE' AND create_datetime > '2023-11-13 00:00:00'")
        ->order('create_datetime DESC')
        ->fetchAll();
    
    // Put files into notes if created at the same time by the same creator for the same contact
    foreach($note_list as $_note) {
        $note_dt = strtotime($_note['create_datetime']);
        $files = array_filter($file_list, function ($_file) use ($_note, $note_dt) {
            $file_dt = strtotime($_file['create_datetime']);
            return ($file_dt >= $note_dt && $file_dt - $note_dt < 20 
                    || $file_dt <= $note_dt && $note_dt -$file_dt < 2)
                && $_file['creator_contact_id'] == $_note['creator_contact_id']
                && $_file['contact_id'] == $_note['contact_id'];
        });
        if (empty($files)) {
            continue;
        }
        $file_ids = array_column($files, 'id');
        $fm->updateByField(['id' => $file_ids], ['source_type' => 'NOTE']);
        $nam->multipleInsert([
            'note_id' => $_note['id'],
            'file_id' => $file_ids,
        ]);
    }
}
