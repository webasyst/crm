<?php
return array(
    'name'     => /*_w*/('CRM'),
    'description' => /*_w*/('Webasyst CRM is excellent for managing your clients database and sales.'),
    'icon'     => 'img/crm.svg',
    'sash_color' => '#e63a24',
    'version'  => '2.0.4',
    'vendor'   => 'webasyst',
    'plugins'  => true,
    'rights'   => true,
    'csrf'     => true,
    'routing_params' => array(
        'private' => true,
    ),
    'payment_plugins'  => true,
    'sms_plugins'      => true,
    'frontend'         => true,
    'ui' => '1.3,2.0',
);
