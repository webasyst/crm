<?php

$routing = wa()->getRouting()->getByApp('crm');
$callback_url = !$routing ? _wd('crm_mango', 'Add settlement for CRM') : rtrim(wa()->getRouteUrl('crm', array(
    'plugin' => 'mango',
    'module' => 'frontend',
    'action' => 'callback',
    'event_type' => '',
    'event_name' => '',
), true), '/');

return array(
    'header' => array(
        'control_type' => waHtmlControl::CUSTOM,
        'description'  => '<span style="margin: -12px 0 6px -180px; display: inline-block; font-size: 14px;">'.sprintf(_wd("crm_mango", "These data are %sin your personal account%s %s on the “API Connection” page."), '<a href="https://lk.mango-office.ru/api-vpbx/settings" target="_blank">', '</a>', '<i class="icon10 new-window"></i>').'</span>',
    ),
    'api_key' => array(
        'title'        => 'API Key',
        'control_type' => waHtmlControl::INPUT,
        'description'  => _wd('crm_mango', 'The unique code of your PBX'),
    ),
    'sign_key' => array(
        'title' => 'Sign Key',
        'control_type' => waHtmlControl::INPUT,
        'description' => _wd('crm_mango', 'The key for creating a signature'),
    ),
    'webhook' => array(
        'title' => _wd('crm_mango', 'External system address'),
        'control_type' => waHtmlControl::CUSTOM,
        'description' => '<input type="text" readonly value="'.$callback_url.'" /><br><br>'
    ),
);
