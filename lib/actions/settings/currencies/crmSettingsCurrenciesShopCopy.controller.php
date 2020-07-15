<?php

class crmSettingsCurrenciesShopCopyController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm') || !crmConfig::isShopSupported()) {
            throw new waRightsException();
        }
        $asm = new waAppSettingsModel();

        $disable = waRequest::post('disable', null, waRequest::TYPE_INT);
        if (!$disable) {

            $rate = waRequest::request('rate', 1, waRequest::TYPE_STRING_TRIM);

            $asm->set('crm', 'use_shop_currencies', 1);

            $currency = new crmCurrency();
            $currency->copy($rate);

        } else {
            $asm->set('crm', 'use_shop_currencies', null);
        }
    }
}
