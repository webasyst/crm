<?php

return array(
    'name'                => 'Facebook',
    'description'         => 'Provides integration with Facebook messages',
    'img'                 => 'img/fb.png',
    'version'             => '1.0.7',
    'vendor'              => 'webasyst',
    'custom_settings_url' => '?plugin=fb&action=settings',
    'frontend'            => true,
    'source'              => true,
    'handlers'            => array(
        'backend_assets' => 'backendAssets',
        'message_delete' => 'messageDelete'
    ),
);