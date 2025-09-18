<?php

return [
    'name'        => 'VK',
    'description' => 'Provides integration with VK messages',
    'img'         => 'img/vk.png',
    'version'     => '1.1.1',
    'vendor'      => 'webasyst',
    'custom_settings_url' => '?plugin=vk&action=settings',
    'frontend'            => true,
    'source'              => true,
    'handlers'            => [
        'backend_assets' => 'backendAssets',
        'message_delete' => 'messageDelete',
        '*' => [
            [
                'event_app_id' => 'contacts',
                'event'        => 'merge',
                'class'        => 'crmVkPlugin',
                'method'       => 'mergeContacts',
            ]
        ],
    ],
];
