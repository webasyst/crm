<?php

$locale = wa()->getLocale() == 'ru_RU' ? 'ru_RU' : 'en_US';
$dm = new crmDealModel();
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

$content['deal'] = $dm->getEmptyRow();
$content['deal']['number'] = 'D-0001';
$content['deal']['create_datetime'] = date('Y-m-d H:i:s');
$content['deal']['expected_date'] = date('Y-m-d', strtotime('+10 days'));
$content['deal']['url'] = wa()->getRootUrl(true).wa()->getConfig()->getBackendUrl().'crm/deal/1/';
$content['deal']['funnel'] = $content['deal']['stage'] = $content['deal']['before_funnel'] = $content['deal']['before_stage'] = array();
$content['deal']['limit_hours'] = 5;

if ($locale == 'ru_RU') {
    $content['deal']['name'] = 'Тестовая сделка';
    $content['deal']['description'] = 'Описание тестовой сделки';
    $content['deal']['amount'] = 4100;
    $content['deal']['currency_id'] = 'RUB';
    $content['deal']['funnel']['name'] = 'Тестовая воронка';
    $content['deal']['stage']['name'] = 'Тестовая стадия';
    $content['deal']['before_funnel']['name'] = 'Прошлая воронка';
    $content['deal']['before_stage']['name'] = 'Прошлая стадия';
} else {
    $content['deal']['name'] = 'Test deal';
    $content['deal']['description'] = 'Test description';
    $content['deal']['amount'] = 410;
    $content['deal']['currency_id'] = 'USD';
    $content['deal']['funnel']['name'] = 'Test funnel';
    $content['deal']['stage']['name'] = 'Test stage';
    $content['deal']['before_funnel']['name'] = 'Last funnel';
    $content['deal']['before_stage']['name'] = 'Last stage';
}

return $content;
