<?php

/**
 * Plugin for integration MAX messenger with Webasyst CRM.
 */
return array(
    'name'                => 'MAX',
    'description'         => 'Provides integration with MAX Messenger',
    'img'                 => 'img/max.png',
    'version'             => '1.0.1',
    'vendor'              => 'webasyst',
    'frontend'            => true,
    'source'              => true,
    'custom_settings_url' => '?plugin=max&action=settings',
    'handlers'            => array(
        'backend_assets' => 'backendAssets',
    ),
);
