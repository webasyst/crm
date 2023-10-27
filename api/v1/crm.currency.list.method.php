<?php

class crmCurrencyListMethod extends crmApiAbstractMethod
{
    public function execute()
    {
        $currencies = [];
        $crm_currencies = $this->getCurrencyModel()->getAll('code');
        $primary_currency = waCurrency::getInfo($this->getConfig()->getCurrency());
        $primary_currency_code = ifset($primary_currency, 'code', '');
        foreach ($crm_currencies as $code => $curr_data) {
            $currencies[] = [
                'id' => $code,
                'name' => ifset($curr_data, 'title', ''),
                'is_primary' => $code == $primary_currency_code,
                'rate' => (float) ifset($curr_data, 'rate', 0)
            ];
        }

        $this->response = $currencies;
    }
}
