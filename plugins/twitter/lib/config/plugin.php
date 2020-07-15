<?php

return array(
    'name'                => 'Twitter',
    'description'         => 'Provides integration with Twitter messages',
    'img'                 => 'img/twitter.png',
    'version'             => '1.0.3',
    'vendor'              => 'webasyst',
    'custom_settings_url' => '?plugin=twitter&action=settings',
    'source'              => true,
    'handlers'            => array(
        'backend_assets' => 'backendAssets',
        //'message_delete' => 'messageDelete'
    ),
);