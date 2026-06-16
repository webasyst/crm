<?php

$_installer = new crmInstaller();
$_installer->installSearchConfig();

/*
$cf = wa('crm')->getConfig()->getConfigPath('search/search.php');
if (!file_exists($cf)) {
    exit;
}

crmContactsSearchHelper::updateItem('contact_info.creating', [
    'name' => _w('Creating method and date'),
    'multi' => true,
    'items' => [
        'method' => [
            'name' => _w('Method'),
            'items' => [
                ':values' => [
                    'autocomplete' => 1,
                    'class' => 'crmContactsSearchCreateMethodValues'
                ]
            ],
        ],
        'date' => [
            'name' => _w('Date'),
            'items' => [
                ':period' => [
                    'name' => _w('select a period'),
                    'where' => [
                        ':between' => "c.create_datetime IS NOT NULL AND DATE(c.create_datetime) >= ':0' AND DATE(c.create_datetime) <= ':1'",
                        ':gt' => "c.create_datetime IS NOT NULL AND DATE(c.create_datetime) >= ':?'",
                        ':lt' => "c.create_datetime IS NOT NULL AND DATE(c.create_datetime) <= ':?'"
                    ]
                ]
            ]
        ]
    ]
]);
*/