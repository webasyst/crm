<?php

class crmSettingsCurrenciesSortController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $codes = waRequest::post('codes', array(), waRequest::TYPE_ARRAY_TRIM);

        $this->validate($codes);

        $m = new crmCurrencyModel();
        $currencies = $m->getAll('code');
        $sort = 0;
        foreach ($codes as $c) {
            if (isset($currencies[$c])) {
                $m->updateById($c, array('sort' => $sort++));
            }
        }
    }

    private function validate($codes)
    {
        if (!$codes) {
            throw new waException('Empty data');
        }
    }
}
