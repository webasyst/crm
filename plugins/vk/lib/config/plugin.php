<?php

return array(
    'name'        => 'VK',
    'description' => 'Provides integration with VK.com messages',
    'img'         => 'img/vk.png',
    'version'     => '1.0.6',
    'vendor'      => 'webasyst',
    'custom_settings_url' => '?plugin=vk&action=settings',
    'frontend'            => true,
    'source'              => true,
    'handlers'            => array(
        'backend_assets' => 'backendAssets',
        'message_delete' => 'messageDelete'
    ),
);
