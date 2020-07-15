<?php

class crmSettingsCurrenciesSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $code = waRequest::post('code', null, waRequest::TYPE_STRING_TRIM);
        $rate = waRequest::post('rate', null, waRequest::TYPE_STRING_TRIM);

        $this->errors = array();
        $this->validate($code, $rate);
        if ($this->errors) {
            return;
        }

        $rate = round($rate, 8);

        $m = new crmCurrencyModel();
        $m->updateByField('code', $code, array('rate' => $rate));
    }

    private function validate($code, &$rate)
    {
        $m = new crmCurrencyModel();
        $currencies = $m->getAll('code');

        if (!$code || !isset($currencies[$code])) {
            throw new waException('Currency not found');
        }
        $rate = str_replace(',', '.', $rate);
        if (!$rate || !is_numeric($rate)) {
            $this->errors[] = array('name' => 'rate', 'value' => _w('Invalid rate'));
        }
    }
}
