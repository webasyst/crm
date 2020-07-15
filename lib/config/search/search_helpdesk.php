<?php

return array(
    'name' => 'Helpdesk',                                               // _w('Helpdesk')
    'items' => array(
        'helpdesk' => array(
            'name' => 'Wrote requests',       // _w('Wrote requests')
            'join' => array(
                'table' => 'helpdesk_request',
                'on' => 'c.id = :table.creator_contact_id'
            ),
            'order_by' => 'last_request_datetime DESC',
            'group_by' => 1,
            'multi' => true,
            'items' => array(
                'period' => array(
                    'name' => 'Period',           // _w('Period')
                    'items' => array(
                        ':period' => array(
                            'name' => 'select a period',      // _w('select a period')
                            'where' => array(
                                ':between' => "DATE(:parent_table.created) >= ':0' AND DATE(:parent_table.created) <= ':1'",
                                ':gt' => "DATE(:parent_table.created) >= ':?'",
                                ':lt' => "DATE(:parent_table.created) <= ':?'",
                            )
                        )
                    )
                ),
                'status' => array(
                    'name' => 'Current status',   // _w('Current status')
                    'readonly' => true,
                    'items' => array(
                        ':values' => array(
                            'sql' => 'SELECT DISTINCT state_id AS value, state_id AS name FROM helpdesk_request',
                            'where' => array(
                                '=' => ":parent_table.state_id = ':value'",
                            )
                        )
                    )
                )
            ),
        ),
        'helpdesk_actions' => array(
            'name' => 'Performed an action with requests',        // _w('Performed an action with requests')
            'join' => array(
                'table' => 'helpdesk_request_log',
                'on' => 'c.id = :table.actor_contact_id'
            ),
            'group_by' => 1,
            'multi' => true,
            'items' => array(
                'period' => array(
                    'name' => 'Period',           // _w('Period')
                    'items' => array(
                        ':period' => array(
                            'name' => 'select a period',      // _w('select a period')
                            'where' => array(
                                ':between' => "DATE(:parent_table.datetime) >= ':0' AND DATE(:parent_table.datetime) <= ':1'",
                                ':gt' => "DATE(:parent_table.datetime) >= ':?'",
                                ':lt' => "DATE(:parent_table.datetime) <= ':?'",
                            )
                        )
                    )
                ),
                'action' => array(
                    'name' => 'Action',           // _w('Action')
                    'readonly' => true,
                    'items' => array(
                        ':values' => array(
                            'class' => 'crmContactsSearchHelpdeskActionsValues'
                        )
                    )
                )
            )
        )
    )
);
