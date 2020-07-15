<?php
return array(
    'crm_vk_plugin_chat' => array(
        'id' => array('int', 11, 'unsigned' => 1, 'null' => 0, 'autoincrement' => 1),
        'name' => array('varchar', 255, 'null' => 0),
        'principal_participant_id' => array('int', 11, 'unsigned' => 1, 'null' => 0),
        'participant_id' => array('int', 11, 'unsigned' => 1, 'null' => 0),
        'deal_id' => array('int', 11),
        'create_datetime' => array('datetime', 'null' => 0),
        'update_datetime' => array('datetime', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'principal_participant_id' => 'principal_participant_id',
            'participant_id' => 'participant_id',
        ),
    ),
    'crm_vk_plugin_chat_params' => array(
        'chat_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 255, 'null' => 0),
        'value' => array('text'),
        ':keys' => array(
            'PRIMARY' => array('chat_id', 'name'),
        ),
    ),
    'crm_vk_plugin_chat_participant' => array(
        'id' => array('int', 11, 'unsigned' => 1, 'null' => 0, 'autoincrement' => 1),
        'contact_id' => array('int', 11, 'null' => 0),
        'domain' => array('varchar', 255, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'domain' => array('domain', 'unique' => 1),
            'contact_id' => 'contact_id'
        ),
    ),
    'crm_vk_plugin_chat_participant_params' => array(
        'participant_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 255, 'null' => 0),
        'value' => array('text'),
        ':keys' => array(
            'PRIMARY' => array('participant_id', 'name'),
        ),
    ),
    'crm_vk_plugin_chat_messages' => array(
        'chat_id' => array('int', 11, 'unsigned' => 1),
        'message_id' => array('int', 11, 'unsigned' => 1),
        ':keys' => array(
            'PRIMARY' => array('chat_id', 'message_id'),
        ),
    ),
);
