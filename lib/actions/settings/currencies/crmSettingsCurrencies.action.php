<?php

class crmSettingsCurrenciesAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $cm = new crmCurrencyModel();
        $crm_currencies = $cm->getAll('code');

        $currency_list = waCurrency::getAll('all');
        uasort($currency_list, array($this, 'sortCurrency'));

        $shop_currencies = array();
        $use_shop_currencies = $use_shop_available = false;
        $is_shop_supported = crmShop::isIntegrationSupported(crmShop::INTEGRATION_SYNC_CURRENCIES);
        if ($is_shop_supported) {
            wa('shop');
            $scm = new shopCurrencyModel();
            $shop_currencies = $scm->getAll('code');

            $asm = new waAppSettingsModel();
            $use_shop_available = !$crm_currencies || isset($shop_currencies[$asm->get('crm', 'currency')]) || isset($crm_currencies[$asm->get('shop', 'currency')]);
            $use_shop_currencies = $use_shop_available ? $asm->get('crm', 'use_shop_currencies') : false;
        }

        $this->view->assign(array(
            'currency_list'       => $currency_list,
            'currencies'          => $crm_currencies,
            'currency'            => waCurrency::getInfo($this->getConfig()->getCurrency()),
            'use_shop_currencies' => $use_shop_currencies,
            'use_shop_available'  => $use_shop_available,
            'is_shop_supported'   => $is_shop_supported,
            'shop_currency'       => reset($shop_currencies),
        ));
    }

    public function sortCurrency($a, $b)
    {
        if ($a['code'] === $b['code']) {
            return 0;
        }
        return $a['code'] > $b['code'] ? 1 : -1;
    }
}
