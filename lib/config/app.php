<?php
return array(
    'name'     => /*_w*/('CRM'),
    'description' => /*_w*/('Webasyst CRM is excellent for managing your clients database and sales.'),
    'icon'     => 'img/crm-magic.svg',
    'sash_color' => '#f27130',
    'version'  => '3.0.0', // public beta
    'vendor'   => 'webasyst',
    'plugins'  => true,
    'rights'   => true,
    'csrf'     => true,
    'routing_params' => array(
        'private' => true,
    ),
    'payment_plugins'  => array(
        'taxes'     => true,
    ),
    'sms_plugins'      => true,
    'frontend'         => true,
    'ui' => '1.3,2.0',
);
