<?php

class crmSettingsCurrenciesPrimarySaveController extends crmJsonController
{
    public function execute()
    {
        $code = waRequest::post('code', null, waRequest::TYPE_STRING_TRIM);

        $this->validate($code);

        $currency = new crmCurrency();
        $currency->change($code);
    }

    private function validate($code)
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $m = new crmCurrencyModel();
        $currencies = $m->getAll('code');
        $currency_list = waCurrency::getAll();

        if (!$code || !isset($currencies[$code]) || !isset($currency_list[$code])) {
            throw new waException('Currency not found');
        }
    }
}
