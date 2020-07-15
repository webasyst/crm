<?php

return array(
    'crm_telegram_plugin_sticker'     => array(
        'id'               => array('int', 11, 'unsigned' => 1, 'null' => 0, 'autoincrement' => 1),
        'crm_file_id'      => array('int', 11, 'unsigned' => 1, 'null' => 0),
        'telegram_file_id' => array('varchar', 255, 'null' => 0),
        ':keys'            => array(
            'PRIMARY'          => 'id',
            'crm_file_id'      => 'crm_file_id',
            'telegram_file_id' => 'telegram_file_id',
        ),
    ),
    'crm_telegram_plugin_file_params' => array(
        'file_id' => array('int', 11, 'null' => 0),
        'name'    => array('varchar', 32, 'null' => 0),
        'value'   => array('text', 'null' => 0),
        ':keys'   => array(
            'PRIMARY' => array('file_id', 'name'),
        ),
    ),
);