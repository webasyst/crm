<?php

return array(
    'name'                => 'Telegram',
    'description'         => 'Provides integration with Telegram Messenger',
    'img'                 => 'img/telegram.png',
    'version'             => '1.1.4',
    'vendor'              => 'webasyst',
    'custom_settings_url' => '?plugin=telegram&action=settings',
    'frontend'            => true,
    'source'              => true,
    'handlers'            => array(
        'backend_assets' => 'backendAssets',
        'message_delete' => 'messageDelete'
    ),
);
