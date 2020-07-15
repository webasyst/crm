<?php
return array(
    'name'                => 'Zadarma',
    'img'                 => 'img/zadarma.png',
    'version'             => '1.0.1',
    'vendor'              => 'webasyst',
    'frontend'            => true,
    'custom_settings_url' => '?plugin=zadarma&module=settings',
    'handlers'            => array(
        'backend_assets'      => 'backendAssetsHandler',
        'backend_profile_log' => 'backendAssetsHandler',
    ),
);
