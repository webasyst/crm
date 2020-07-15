<?php

class crmSettingsCurrenciesDeleteController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $code = waRequest::post('code', null, waRequest::TYPE_STRING_TRIM);

        $this->validate($code);

        $m = new crmCurrencyModel();
        $m->deleteById($code);
    }

    private function validate($code)
    {
        $m = new crmCurrencyModel();
        $currencies = $m->getAll('code');

        if (!$code || !isset($currencies[$code])) {
            throw new waException('Currency not found');
        }
    }
}
