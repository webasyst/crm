<?php
$callback_url = $uri = rtrim(wa()->getRouteUrl('crm', array(
        'plugin' => 'zebratelecom',
        'module' => 'frontend',
        'action' => 'callback',
    ), true), '/');

return array(
    'header' => array(
        'control_type' => waHtmlControl::CUSTOM,
        'description'  => '<span style="margin: -12px 0 6px -180px; display: inline-block; font-size: 14px;">'.sprintf(_wd("crm_zebratelecom", "Below, enter the data for authentication in the %sZebraTelecom PBX%s %s"), '<a href="https://vats.zebratelecom.ru/" target="_blank">', '</a>', '<i class="icon10 new-window"></i>').'</span>',
    ),
    'login'      => array(
        'title'        => _w('Login'),
        'control_type' => waHtmlControl::INPUT,
    ),
    'password'   => array(
        'title'        => _w('Password'),
        'control_type' => waHtmlControl::PASSWORD,
    ),
    'sip_server' => array(
        'title'        => _wd('crm_zebratelecom', 'Domain (SIP Server)'),
        'control_type' => waHtmlControl::INPUT,
        'description'  => "<br /><span style='color: red; font-weight: bold'>". _wd('crm_zebratelecom', 'IMPORTANT:') ."</span> ". _wd('crm_zebratelecom', 'Zebra Telecom does not support the sending of callbacks to 443 ports.') ."<br />". sprintf(_wd("crm_zebratelecom", "The %s page should be accessible via the <b>http</b> protocol."), $callback_url) ."<br /><br />"
    ),
);
