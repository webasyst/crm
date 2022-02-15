<?php
return array(
    'name'                => 'Telphin',
    'img'                 => 'img/telphin.png',
    'version'             => '1.1.3',
    'vendor'              => 'webasyst',
    'custom_settings_url' => '?plugin=telphin&action=settings',
    'frontend'            => true,
    'handlers'            => array(
        '/pbx_numbers_.*/'           => 'pbxNumbersHandler',
        'backend_assets'             => 'backendAssetsHandler',
        'backend_profile_log'        => 'backendAssetsHandler',
        'start_calls_cleanup_worker' => 'callsCleanupHandler',
    ),
);
