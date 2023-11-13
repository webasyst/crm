<?php
return array(
    'name'                => 'Gravitel',
    'img'                 => 'img/gravitel.png',
    'version'             => '1.0.3',
    'vendor'              => 'webasyst',
    'frontend'            => true,
    'custom_settings_url' => '?plugin=gravitel&module=settings',
    'handlers'            => array(
        'backend_assets'      => 'backendAssetsHandler',
        'backend_profile_log' => 'backendAssetsHandler',
    ),
);
