<?php

return array(
    'contact_info' => array(
        'name' => 'Contact info',       // _w('Contact info')
        'items' => array(
            'name' => array(
                'name' => 'Name',      // _w('Name')
                'title' => false,
                'children' => 1,
                'items' => array(
                    'name' => array(
                        'field_id' => 'name'
                    ),
                    'title' => array(
                        'field_id' => 'title',
                        'items' => array(
                            'blank' => array(
                                'name' => 'Empty',     // _w('Empty')
                                'where' => "c.title = ''"
                            ),
                            'not_blank' => array(
                                'name' => 'Not empty',     // _w('Not empty')
                                'where' => "c.title != ''"
                            ),
                            ':sep' => array(),
                            ':values' => array(
                                "autocomplete" => "AND title LIKE ':term%'",
                                "limit" => 10,
                                "sql" => "SELECT title AS name, COUNT(*) count
                            FROM wa_contact
                            WHERE title != '' :autocomplete
                            GROUP BY title
                            ORDER BY count DESC
                            LIMIT :limit",
                                "count" => "SELECT COUNT(DISTINCT title) FROM wa_contact WHERE title != '' :autocomplete"
                            )
                        )
                    ),
                    'firstname' => array(
                        'field_id' => 'firstname',
                        'items' => array(
                            'blank' => array(
                                'name' => 'Empty',         // _w('Empty')
                                'where' => "c.firstname = ''"
                            ),
                            'not_blank' => array(
                                'name' => 'Not empty',     // _w('Not empty')
                                'where' => "c.firstname != ''"
                            ),
                            ':sep' => array(),
                            ':values' => array(
                                "autocomplete" => "AND firstname LIKE ':term%'",
                                "limit" => 10,
                                "sql" => "SELECT firstname AS name, COUNT(*) count
                            FROM wa_contact
                            WHERE firstname != '' :autocomplete
                            GROUP BY firstname
                            ORDER BY count DESC
                            LIMIT :limit",
                                "count" => "SELECT COUNT(DISTINCT firstname) FROM wa_contact WHERE firstname != '' :autocomplete"
                            )
                        )
                    ),
                    'middlename' => array(
                        'field_id' => 'middlename',
                        'items' => array(
                            'blank' => array(
                                'name' => 'Empty',     // _w('Empty')
                                'where' => "c.middlename = ''"
                            ),
                            'not_blank' => array(
                                'name' => 'Not empty',     // _w('Not empty')
                                'where' => "c.middlename != ''"
                            ),
                            ":sep" => array(),
                            ':values' => array(
                                "autocomplete" => "AND middlename LIKE ':term%'",
                                "limit" => 10,
                                "sql" => "SELECT middlename AS name, COUNT(*) count
                            FROM wa_contact
                            WHERE middlename != '' :autocomplete
                            GROUP BY middlename
                            ORDER BY count DESC
                            LIMIT :limit",
                                "count" => "SELECT COUNT(DISTINCT middlename) FROM wa_contact WHERE middlename != '' :autocomplete"
                            )
                        )
                    ),
                    'lastname' => array(
                        'field_id' => 'lastname',
                        'items' => array(
                            'blank' => array(
                                'name' => 'Empty',     // _w('Empty')
                                'where' => "c.lastname = ''"
                            ),
                            'not_blank' => array(
                                'name' => 'Not empty',     // _w('Not empty')
                                'where' => "c.lastname != ''"
                            ),
                            ':sep' => array(),
                            ':values' => array(
                                "autocomplete" => "AND lastname LIKE ':term%'",
                                "limit" => 10,
                                "sql" => "SELECT lastname AS name, COUNT(*) count
                            FROM wa_contact
                            WHERE lastname != '' :autocomplete
                            GROUP BY lastname
                            ORDER BY count DESC
                            LIMIT :limit",
                                "count" => "SELECT COUNT(DISTINCT lastname) FROM wa_contact WHERE lastname != '' :autocomplete"
                            )
                        )
                    ),
                )
            ),
            'jobtitle' => array(
                'field_id' => 'jobtitle',
                'items' => array(
                    'blank' => array(
                        'name' => 'Empty',         // _w('Empty')
                        'where' => "c.jobtitle = ''"
                    ),
                    'not_blank' => array(
                        'name' => 'Not empty',     // _w('Not empty')
                        'where' => "c.jobtitle != ''"
                    ),
                    ':sep' => array(),
                    ':values' => array(
                        "autocomplete" => "AND jobtitle LIKE ':term%'",
                        "limit" => 10,
                        "sql" => "SELECT jobtitle AS name, COUNT(*) count
                    FROM wa_contact
                    WHERE jobtitle != '' :autocomplete
                    GROUP BY jobtitle
                    ORDER BY count DESC
                    LIMIT :limit",
                        "count" => "SELECT COUNT(DISTINCT jobtitle) FROM wa_contact WHERE jobtitle != '' :autocomplete"
                    )
                )
            ),
            'contact_type' => array(
                'name' => 'Contact type',  // _w('Contact type')
                'readonly' => true,
                'items' => array(
                    'person' => array(
                        'name' => 'Person',        // _w('Person')
                        'where' => 'c.is_company = 0',
                    ),
                    'company' => array(
                        'name' => 'Company',   // _w('Company')
                        'where' => 'c.is_company = 1'
                    )
                )
            ),
            'company' => array(
                'field_id' => 'company',
                'items' => array(
                    'blank' => array(
                        'name' => 'Empty',     //  _w('Empty')
                        'where' => "c.company = ''"
                    ),
                    'not_blank' => array(
                        'name' => 'Not empty',     // _w('Not empty')
                        'where' => "c.company != ''"
                    ),
                    ':sep' => array(),
                    ':values' => array(
                        "autocomplete" => "AND company LIKE ':term%'",
                        "limit" => 10,
                        "sql" => "SELECT company AS name, COUNT(*) count
                    FROM wa_contact
                    WHERE company != '' :autocomplete
                    GROUP BY company
                    ORDER BY count DESC
                    LIMIT :limit",
                        "count" => "SELECT COUNT(DISTINCT company) FROM wa_contact WHERE company != '' :autocomplete"
                    )
                )
            ),
            'about' => array(
                'field_id' => 'about',
                'items' => array(
                    'blank' => array(
                        'name' => 'Empty',     // _w('Empty')
                        'where' => "c.about IS NULL OR c.about = ''"
                    ),
                    'not_blank' => array(
                        'name' => 'Not empty',     // _w('Not empty')
                        'where' => "c.about IS NOT NULL AND c.about != ''"
                    ),
                    ':values' => array(
                        'autocomplete' => "WHERE about LIKE '%:term%'",
                        'limit' => 10,
                        'sql' => 'SELECT about AS name
                            FROM wa_contact
                            :autocomplete
                            LIMIT :limit'
                    )
                )
            ),
            'email' => array(
                'field_id' => 'email',
                'items' => array(
                    'blank' => array(
                        'name' => 'Empty',     // _w('Empty')
                        'where' => "(SELECT COUNT(*) FROM wa_contact_emails WHERE contact_id = c.id) = 0"
                    ),
                    'not_blank' => array(
                        'name' => 'Not empty',     // _w('Not empty')
                        'where' => "(SELECT COUNT(*) FROM wa_contact_emails WHERE contact_id = c.id) > 0"
                    ),
                    ':sep' => array(),
                    ':values' => array(
                        'autocomplete' => "WHERE email LIKE '%:term%'",
                        'limit' => 10,
                        'sql' => 'SELECT email AS name
                    FROM wa_contact_emails
                    :autocomplete
                    LIMIT :limit',
                        "count" => "SELECT COUNT(DISTINCT email) FROM wa_contact_emails :autocomplete"
                    )
                )
            ),
            'phone' => array(
                'field_id' => 'phone',
                'items' => array(
                    'blank' => array(
                        'name' => 'Empty',     // _w('Empty')
                        'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'phone') = 0"
                    ),
                    'not_blank' => array(
                        'name' => 'Not empty',     // _w('Not empty')
                        'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'phone') > 0"
                    ),
                    ':sep' => array(),
                    ':values' => array(
                        "autocomplete" => "AND value LIKE '%:term%'",
                        "limit" => 10,
                        "sql" => "SELECT value AS name, COUNT(*) count
                    FROM wa_contact_data
                    WHERE field = 'phone' :autocomplete
                    GROUP BY value
                    ORDER BY count DESC
                    LIMIT :limit",
                        "count" => "SELECT COUNT(DISTINCT value) FROM wa_contact_data WHERE field = 'phone' :autocomplete"
                    )
                )
            ),
            'sex' => array(
                'field_id' => 'sex',
                'readonly' => true,
                'items' => array(
                    'blank' => array(
                        'name' => 'Empty',     // _w('Empty')
                        'where' => "c.sex IS NULL"
                    ),
                    'not_blank' => array(
                        'name' => 'Not empty',     // _w('Not empty')
                        'where' => "c.sex IS NOT NULL"
                    ),
                    ':sep' => array(),
                    ':values' => array(
                        'autocomplete' => 1,
                        "sql" => "SELECT sex AS name, sex AS value, COUNT(*) AS count
                    FROM wa_contact
                    WHERE sex IS NOT NULL
                    GROUP BY sex",
                    )
                )
            ),
            'birthday' => array(
                'field_id' => 'birthday',
                'readonly' => 1,
                'items' => array(
                    'blank' => array(
                        'name' => 'Empty',     // _w('Empty')
                        'where' => "c.birth_day IS NULL AND c.birth_month IS NULL AND c.birth_year IS NULL"
                    ),
                    'not_blank' => array(
                        'name' => 'Not empty',     // _w('Not empty')
                        'where' => "c.birth_day IS NOT NULL OR c.birth_month IS NOT NULL OR c.birth_year IS NOT NULL"
                    ),

                    ':sep' => array(),

                    'today' => array(
                        'name' => 'today',     // _w('today')
                        'where' => "c.birth_day = DAY(NOW()) AND c.birth_month = MONTH(NOW())"
                    ),
                    'today_or_tomorrow' => array(
                        'name' => 'today or tomorrow',         // _w('today or tomorrow')
                        'where' => "(c.birth_day = DAY(NOW()) OR c.birth_day = DAY(DATE_ADD(NOW(), INTERVAL 1 DAY))) AND c.birth_month = MONTH(NOW())"
                    ),
                    'week' => array(
                        'name' => 'in the nearest week',       // _w('in the nearest week')
                        'where' => "c.birth_day IS NOT NULL AND c.birth_month IS NOT NULL AND
                    STR_TO_DATE(CONCAT(YEAR(NOW()), '-', c.birth_month, '-', c.birth_day), '%Y-%m-%d') >= NOW() AND (
                        STR_TO_DATE(CONCAT(YEAR(NOW()), '-', c.birth_month, '-', c.birth_day), '%Y-%m-%d') <= DATE_ADD(NOW(), INTERVAL 7 DAY) OR
                        STR_TO_DATE(CONCAT(YEAR(DATE_ADD(NOW(), INTERVAL 1 YEAR)), '-', c.birth_month, '-', c.birth_day), '%Y-%m-%d') <= DATE_ADD(NOW(), INTERVAL 7 DAY))"
                    ),
                    'month' => array(
                        'name' => 'in the nearest month',      // _w('in the nearest month')
                        'where' => "c.birth_day IS NOT NULL AND c.birth_month IS NOT NULL AND
                    STR_TO_DATE(CONCAT(YEAR(NOW()), '-', c.birth_month, '-', c.birth_day), '%Y-%m-%d') >= NOW() AND (
                        STR_TO_DATE(CONCAT(YEAR(NOW()), '-', c.birth_month, '-', c.birth_day), '%Y-%m-%d') <= DATE_ADD(NOW(), INTERVAL 30 DAY) OR
                        STR_TO_DATE(CONCAT(YEAR(DATE_ADD(NOW(), INTERVAL 1 YEAR)), '-', c.birth_month, '-', c.birth_day), '%Y-%m-%d') <= DATE_ADD(NOW(), INTERVAL 30 DAY))"
                    ),
                    ':period' => array(
                        'name' => 'select a period',       // _w('select a period')
                        'where' => array(
                            ':between' =>
                                "c.birth_day IS NOT NULL AND c.birth_month IS NOT NULL AND
                            STR_TO_DATE(CONCAT(YEAR(NOW()), '-', c.birth_month, '-', c.birth_day), '%Y-%m-%d') >= ':0' AND
                                STR_TO_DATE(CONCAT(YEAR(NOW()), '-', c.birth_month, '-', c.birth_day), '%Y-%m-%d') <= ':1'",
                            ':gt' => "c.birth_day IS NOT NULL AND c.birth_month IS NOT NULL AND
                            STR_TO_DATE(CONCAT(YEAR(NOW()), '-', c.birth_month, '-', c.birth_day), '%Y-%m-%d') >= ':?'",
                            ':lt' => "c.birth_day IS NOT NULL AND c.birth_month IS NOT NULL AND
                            STR_TO_DATE(CONCAT(YEAR(NOW()), '-', c.birth_month, '-', c.birth_day), '%Y-%m-%d') <= ':?'",
                        )
                    )
                ),
            ),
            'address' => array(
                'field_id' => 'address',
                'items' => array(
                    'street' => array(
                        'items' => array(
                            'blank' => array(
                                'name' => 'Empty',       // _w('Empty')
                                'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'address:street') = 0"
                            ),
                            'not_blank' => array(
                                'name' => 'Not empty',      // _w('Not empty')
                                'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'adress:street') > 0"
                            ),
                            ':sep' => array(),
                            ':values' => array(
                                    "autocomplete" => "AND value LIKE '%:term%'",
                                    "limit" => 10,
                                    "sql" => "SELECT value AS name, COUNT(DISTINCT contact_id) count
                            FROM wa_contact_data
                            WHERE field = 'address:street' :autocomplete
                            GROUP BY value
                            ORDER BY count DESC
                            LIMIT :limit",
                                    "count" => "SELECT COUNT(DISTINCT value)
                            FROM wa_contact_data
                            WHERE field = 'address:street' :autocomplete"
                            )
                        ),
                    ),
                    'city' =>  array(
                        'items' => array(
                            'blank' => array(
                                'name' => 'Empty',       // _w('Empty')
                                'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'address:city') = 0"
                            ),
                            'not_blank' => array(
                                'name' => 'Not empty',      // _w('Not empty')
                                'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'address:city') > 0"
                            ),
                            ':sep' => array(),
                            ':values' =>
                                array(
                                    "autocomplete" => "AND value LIKE '%:term%'",
                                    "limit" => 10,
                                    "sql" => "SELECT value AS name, COUNT(DISTINCT contact_id) count
                        FROM wa_contact_data
                        WHERE field = 'address:city' :autocomplete
                        GROUP BY value
                        ORDER BY count DESC
                        LIMIT :limit",
                                    "count" => "SELECT COUNT(DISTINCT value)
                            FROM wa_contact_data
                            WHERE field = 'address:city' :autocomplete"
                                ),
                        )
                    ),
                    'region' => array(
                        'items' => array(
                            'blank' => array(
                                'name' => 'Empty',     // _w('Empty')
                                'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'address:region') = 0"
                            ),
                            'not_blank' => array(
                                'name' => 'Not empty',     // _w('Not empty')
                                'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'address:region') > 0"
                            ),
                            ':sep' => array(),
                            ':values' => array(
                                'autocomplete' => 1,
                                'class' => 'crmContactsSearchRegionValues'
                            )
                        )
                    ),
                    'country' => array(
                        'items' => array(
                            'blank' => array(
                                'name' => 'Empty',     // _w('Empty')
                                'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'address:country') = 0"
                            ),
                            'not_blank' => array(
                                'name' => 'Not empty',     // _w('Not empty')
                                'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'address:country') > 0"
                            ),
                            ':sep' => array(),
                            ':values' => array(
                                'autocomplete' => 1,
                                'class' => 'crmContactsSearchCountryValues'
                            )
                        )
                    ),
                    'zip' =>  array(
                        'items' => array(
                            'blank' => array(
                                'name' => 'Empty',     // _w('Empty')
                                'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'address:zip') = 0"
                            ),
                            'not_blank' => array(
                                'name' => 'Not empty',     // _w('Not empty')
                                'where' => "(SELECT COUNT(*) FROM wa_contact_data WHERE contact_id = c.id AND field = 'address:zip') > 0"
                            ),
                            ':sep' => array(),
                            ':values' =>
                                array(
                                    "autocomplete" => "AND value LIKE ':term%'",
                                    "limit" => 10,
                                    "sql" => "SELECT value AS name, COUNT(DISTINCT contact_id) count
                        FROM wa_contact_data
                        WHERE field = 'address:zip'
                        GROUP BY value
                        ORDER BY count DESC
                        LIMIT :limit",
                                    "count" => "SELECT COUNT(DISTINCT value)
                            FROM wa_contact_data
                            WHERE field = 'address:zip' :autocomplete"
                                ),
                        )
                    )
                )
            ),
            'locale' => array(
                'field_id' => 'locale',
                'readonly' =>  true,
                'items' => array(
                    'blank' => array(
                        'name' => 'Empty',     // _w('Empty')
                        'where' => "c.locale IS NULL OR c.locale = ''"
                    ),
                    'not_blank' => array(
                        'name' => 'Not empty',     // _w('Not empty')
                        'where' => "c.locale IS NOT NULL AND c.locale != ''"
                    ),
                    ':sep' => array(),
                    ':values' => array(
                        'autocomplete' => 1,
                        'sql' => "SELECT locale AS name, locale AS value, COUNT(*) AS count
                            FROM wa_contact
                            WHERE locale IS NOT NULL AND locale != ''
                            GROUP BY locale"
                    )
                ),
            ),
            'timezone' => array(
                'field_id' => 'timezone',
                'readonly' => true,
                'items' => array(
                    'blank' => array(
                        'name' => 'Empty',     // _w('Empty')
                        'where' => "c.timezone IS NULL OR c.timezone = ''"
                    ),
                    'not_blank' => array(
                        'name' => 'Not empty',     // _w('Not empty')
                        'where' => "c.timezone IS NOT NULL AND c.timezone != ''"
                    ),
                    ':sep' => array(),
                    ':values' => array(
                        'sql' => "SELECT timezone AS name, timezone AS value, COUNT(*) AS count
                                FROM wa_contact
                                WHERE timezone IS NOT NULL AND timezone != ''
                                GROUP BY timezone"
                    )
                )
            ),
            'creating' => array(
                'name' => 'Creating method and date',       // _w('Creating method and date')
                'multi' => true,
                'items' => array(
                    'method' => array(
                        'name' => 'Method',         // _w('Method')
                        'readonly' => true,
                        'items' => array(
                            ':values' => array(
                                'class' => 'crmContactsSearchCreateMethodValues'
                            )
                        ),
                    ),
                    'date' => array(
                        'name' => 'Date',           // _w('Date')
                        'items' => array(
                            ':period' => array(
                                'name' => 'select a period',       // _w('select a period')
                                'where' => array(
                                    ':between' => "c.create_datetime IS NOT NULL AND DATE(c.create_datetime) >= ':0' AND DATE(c.create_datetime) <= ':1'",
                                    ':gt' => "c.create_datetime IS NOT NULL AND DATE(c.create_datetime) >= ':?'",
                                    ':lt' => "c.create_datetime IS NOT NULL AND DATE(c.create_datetime) <= ':?'"
                                )
                            )
                        )
                    )
                )
            )
        )
    ),

    'activity' => array(
        'name' => 'Activity',  // _w('Activity')
        'items' => array(
            'action_by' => array(
                'name' => 'Performed an action',        // _w('Performed an action')
                'multi' => true,
                'join' => array(
                    'table'=> 'wa_log'
                ),
                'items' => array(
                    'action' => array(
                        'name' => 'Action',                     // _w('Action')
                        'readonly' => true,
                        'skip_first_space' => true,
                        'items' => array(
                            'any_action' => array(
                                'name' => 'any action',         // _w('any action')
                            ),
                            ':sep' => array(),
                            ':values' => array(
                                'class' => 'crmContactsSearchActivityActionValues'
                            )
                        )
                    ),
                    'period' => array(
                        'name' => 'Period',        // _w('Period')
                        'items' => array(
                            ':period' => array(
                                'name' => '',
                                'where' => array(
                                    ':between' => "DATE(:parent_table.datetime) >= ':0' AND DATE(:parent_table.datetime) <= ':1'",
                                    ':gt' => "DATE(:parent_table.datetime) >= ':?'",
                                    ':lt' => "DATE(:parent_table.datetime) <= ':?'",
                                )
                            )
                        )
                    )
                )
            ),
            'action_to' => array(
                'name' => 'Applied an action to',       // _w('Applied an action to')
                'multi' => true,
                'join' => array(
                    'table' => 'wa_log',
                    'on' => ':table.subject_contact_id = c.id'
                ),
                'items' => array(
                    'action' => array(
                        'name' => 'Action',                     // _w('Action')
                        'readonly' => true,
                        'skip_first_space' => true,
                        'items' => array(
                            'any_action' => array(
                                'name' => 'any action',         // _w('any action')
                            ),
                            ':sep' => array(),
                            ':values' => array(
                                'class' => 'crmContactsSearchActivityActionValues',
                                'options' => array(
                                    'subject' => true
                                )
                            )
                        )
                    ),
                    'period' => array(
                        'name' => 'Period',        // _w('Period')
                        'items' => array(
                            ':period' => array(
                                'name' => '',
                                'where' => array(
                                    ':between' => "DATE(:parent_table.datetime) >= ':0' AND DATE(:parent_table.datetime) <= ':1'",
                                    ':gt' => "DATE(:parent_table.datetime) >= ':?'",
                                    ':lt' => "DATE(:parent_table.datetime) <= ':?'",
                                )
                            )
                        )
                    )
                )
            ),
            'access' => array(
                'name' => 'Access',         // _w('Access')
                'readonly' => true,
                'items' => array(
                    'forbidden' => array(
                        'name' => 'Forbidden',       // _w('Forbidden')
                        'where' => 'c.is_user=-1'
                    ),
                    'customer_portal' => array(
                        'name' => 'Customer portal only',   // _w('Customer portal only'),
                        'where' => 'c.is_user=0'
                    ),
                    'backend' => array(
                        'name' => 'Backend',
                        'where' => 'c.is_user=1'
                    )
                )
            ),
            'status' => array(
                'name' => 'Status',     // _w('Status'),
                'readonly' => true,
                'items' => array(
                    'online' => array(
                        'name' => 'Online',     // _w('Online')
                        'join' => array(
                            'table' => 'wa_login_log',
                            'on' => 'c.id = :table.contact_id',
                            'where' => "c.last_datetime IS NOT NULL AND
                    UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(c.last_datetime) < '" . waUser::getOption('online_timeout') . "' AND :table.datetime_out IS NULL"
                        ),
                        'group_by' => 1
                    ),
                    'offline' => array(
                        'name' => 'Offline',     // _w('Offline')
                        'where' => "c.last_datetime IS NULL OR
                    UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(c.last_datetime) >= '" . waUser::getOption('online_timeout') . "'"
                    ),
                    'never_login' => array(
                        'name' => 'Never logged in',      // _w('Never logged in')
                        'where' => 'c.last_datetime IS NULL'
                    )
                )
            )
        ),
    )
);
