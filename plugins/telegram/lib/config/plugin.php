<?php

return array(
    'name'                => 'Telegram',
    'description'         => 'Provides integration with Telegram Messenger',
    'img'                 => 'img/telegram.png',
    'version'             => '1.0.6',
    'vendor'              => 'webasyst',
    'custom_settings_url' => '?plugin=telegram&action=settings',
    'source'              => true,
    'handlers'            => array(
        'backend_assets' => 'backendAssets',
        'message_delete' => 'messageDelete'
    ),
);
