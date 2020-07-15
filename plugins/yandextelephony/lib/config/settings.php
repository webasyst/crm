<?php

$routing = wa()->getRouting()->getByApp('crm');
$callback_url = !$routing ? _wd('crm_yandextelephony', 'Add settlement for CRM') : rtrim(wa()->getRouteUrl('crm', array(
    'plugin' => 'yandextelephony',
    'module' => 'frontend',
    'action' => 'callback',
), true), '/');

return array(
    'api_key' => array(
        'title'        => 'API Key',
        'control_type' => waHtmlControl::INPUT,
        'description'  => sprintf(_wd("crm_yandextelephony", "Create the API Key in the %ssettings%s %s of the Yandex.Telephony integration API."), '<a href="https://yandex.mightycall.ru/MightyCall/Api#/key" target="_blank">', '</a>', '<i class="icon10 new-window"></i>').'<br><br>',
    ),
    'user_key' => array(
        'title' => 'User Key',
        'control_type' => waHtmlControl::INPUT,
        'description' => sprintf(_wd("crm_yandextelephony", "Use the User Key of one of %syour users%s %s"), '<a href="https://yandex.mightycall.ru/MightyCall/Api#/key" target="_blank">', '</a>', '<i class="icon10 new-window"></i>').'<br>',
    ),
    'webhook' => array(
        'title' => _wd('crm_yandextelephony', 'Call notifications link'),
        'control_type' => waHtmlControl::CUSTOM,
        'description' => '<input type="text" readonly value="'.$callback_url.'" /><br>'.sprintf(_wd("crm_yandextelephony", "Specify this address for webhook %sin the settings%s %s of the integration API"), '<a href="https://yandex.mightycall.ru/MightyCall/Api#/webhooks" target="_blank">', '</a>', '<i class="icon10 new-window"></i>').'<br><br>',
    ),
);
