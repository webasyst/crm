<?php
return array(
    'name'     => /*_w*/('CRM'),
    'description' => /*_w*/('Webasyst CRM is excellent for managing your clients database and sales.'),
    'icon'     =>
        array(
            24 => 'img/crm24.png',
            48 => 'img/crm48.png',
            96 => 'img/crm96.png',
        ),
    'sash_color' => '#e63a24',
    'version'  => '1.4.16',
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
);