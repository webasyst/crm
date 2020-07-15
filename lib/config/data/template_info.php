<?php

$locale = wa()->getLocale() == 'ru_RU' ? 'ru_RU' : 'en_US';
$im = new crmInvoiceModel();
$itm = new crmInvoiceItemsModel();
$content = array();

$content['company']['logo_url'] = null;
if ($locale == 'ru_RU') {
    $content['company']['name'] = 'ООО "СтройКомплект"';
    $content['company']['address'] = 'Москва, 1-я ул. Строителей, 16';
    $content['company']['phone'] = '+7 (495) 000-00-00';
} else {
    $content['company']['name'] = 'InterLab LLC';
    $content['company']['address'] = '24 Real Road Newark DE 19713 USA';
    $content['company']['phone'] = '+1 (302) 000-00-00';
}

$content['invoice'] = $im->getEmptyRow();
$content['invoice']['items'][] = $itm->getEmptyRow();
$content['invoice']['items'][] = $itm->getEmptyRow();
$content['invoice']['items'][] = $itm->getEmptyRow();
$content['invoice']['number'] = 'C-0001';
$content['invoice']['invoice_date'] = date('Y-m-d');

if ($locale == 'ru_RU') {
    $content['invoice']['amount'] = 4100;
    $content['invoice']['currency_id'] = 'RUB';
    $content['invoice']['tax_name'] = 'НДС';
    $content['invoice']['tax_type'] = 'INCLUDE';
    $content['invoice']['tax_percent'] = 20;
    $content['invoice']['tax_amount'] = 702;
    $content['invoice']['comment'] = '<p>Отгрузка производится в течение 5 рабочих дней после поступления оплаты.</p>';
    $content['invoice']['items'][0]['name'] = 'Вентилятор бытовой';
    $content['invoice']['items'][0]['quantity'] = 1;
    $content['invoice']['items'][0]['price'] = 100;
    $content['invoice']['items'][0]['tax_type'] = 'INCLUDE';
    $content['invoice']['items'][0]['tax_percent'] = 20;
    $content['invoice']['items'][1]['name'] = 'Кабель питания';
    $content['invoice']['items'][1]['quantity'] = 2;
    $content['invoice']['items'][1]['price'] = 100;
    $content['invoice']['items'][1]['tax_type'] = 'NONE';
    $content['invoice']['items'][1]['tax_percent'] = 0;
    $content['invoice']['items'][2]['name'] = 'Холодильник компактный';
    $content['invoice']['items'][2]['quantity'] = 1;
    $content['invoice']['items'][2]['price'] = 3800;
    $content['invoice']['items'][2]['tax_type'] = 'INCLUDE';
    $content['invoice']['items'][2]['tax_percent'] = 20;
} else {
    $content['invoice']['currency_id'] = 'USD';
    $content['invoice']['amount'] = 410;
    $content['invoice']['tax_name'] = 'VAT';
    $content['invoice']['tax_type'] = 'NONE';
    $content['invoice']['comment'] = '<p>Shipment will be completed within 5 working days after receipt of payment.</p>';
    $content['invoice']['items'][0]['name'] = 'Household Fan';
    $content['invoice']['items'][0]['quantity'] = 1;
    $content['invoice']['items'][0]['price'] = 10;
    $content['invoice']['items'][0]['tax_type'] = 'NONE';
    $content['invoice']['items'][0]['tax_percent'] = 0;
    $content['invoice']['items'][1]['name'] = 'Power cable';
    $content['invoice']['items'][1]['quantity'] = 2;
    $content['invoice']['items'][1]['price'] = 10;
    $content['invoice']['items'][1]['tax_type'] = 'NONE';
    $content['invoice']['items'][1]['tax_percent'] = 0;
    $content['invoice']['items'][2]['name'] = 'Compact refrigerator';
    $content['invoice']['items'][2]['quantity'] = 1;
    $content['invoice']['items'][2]['price'] = 380;
    $content['invoice']['items'][2]['tax_type'] = 'NONE';
    $content['invoice']['items'][2]['tax_percent'] = 0;
}
$content['link'] = wa()->getRouteUrl('crm/frontend/invoice', array('hash' => _w('non-working_public_link')), true);

return $content;
