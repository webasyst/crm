<?php

class crmSettingsCurrenciesAddController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $code = waRequest::post('code', null, waRequest::TYPE_STRING_TRIM);
        $rate = waRequest::post('rate', null, waRequest::TYPE_STRING_TRIM);

        $this->validate($code, $rate);

        $m = new crmCurrencyModel();
        $currency = array(
            'code' => $code,
            'rate' => $rate,
            'sort' => $m->select('MAX(sort) mc')->fetchField('mc') + 1,
        );
        $m->insert($currency);

        $info = waCurrency::getInfo($code);
        $currency['title'] = ifempty($info['title'], $code);
        $currency['sign'] = ifempty($info['sign'], $code);

        $this->response = $currency;
    }

    private function validate($code, $rate)
    {
        $m = new crmCurrencyModel();
        $currencies = $m->getAll('code');
        $currency_list = waCurrency::getAll();

        if (!$code || isset($currencies[$code]) || !isset($currency_list[$code])) {
            throw new waException('Currency not found');
        }

        $rate = str_replace(',', '.', $rate);
        if (!$rate || !is_numeric($rate)) {
            throw new waException('Invalid rate');
        }
    }
}
