<?php
return array(
    'name' => 'Yandex.Telephony',
    'img' => 'img/yandextelephony.png',
    'version' => '1.1.0',
    'vendor' => 'webasyst',
    'frontend' => true,
    'custom_settings_url' => '?plugin=yandextelephony&module=settings',
    'handlers' => array(
        'backend_assets' => 'backendAssetsHandler',
        'backend_profile_log' => 'backendAssetsHandler',
    ),
);
