<?php
return array(
    'name'                => 'Sipuni',
    'img'                 => 'img/sipuni.png',
    'version'             => '1.0.3',
    'vendor'              => 'webasyst',
    'frontend'            => true,
    'custom_settings_url' => '?plugin=sipuni&module=settings',
    'handlers'            => array(
        'backend_assets'      => 'backendAssetsHandler',
        'backend_profile_log' => 'backendAssetsHandler',
    ),
);
