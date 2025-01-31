<?php

return array(
    'name'        => 'WhatsApp Business',
    'description' => 'Provides integration with WhatsApp Business Platform',
    'img'         => 'img/whatsapp.png',
    'version'     => '1.1.0',
    'vendor'      => 'webasyst',
    'custom_settings_url' => '?plugin=whatsapp&action=settings',
    'frontend'            => true,
    'source'              => true,
    'handlers'            => [
        'backend_assets' => 'backendAssets',
        'message_delete' => 'messageDelete',
        'contact.ui.actions' => 'contactUiActions',
    ],
);
