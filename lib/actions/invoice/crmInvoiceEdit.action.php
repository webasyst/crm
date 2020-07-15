<?php

/**
 * HTML for invoice editor page.
 */
class crmInvoiceEditAction extends crmInvoiceIdAction
{
    public function execute()
    {
        parent::execute();
        if ($this->invoice['state_id'] != 'DRAFT' && $this->invoice['state_id'] != 'PENDING') {
            throw new waRightsException();
        }
        $shop_supported = crmConfig::isShopSupported();
        $supported_currencies = array();
        if ($shop_supported) {
            wa('shop');
            $scm = new shopCurrencyModel();
            $supported_currencies = array_keys($scm->getCurrencies());
        }

        $this->view->assign(array(
            'shop_supported'       => $shop_supported,
            'supported_currencies' => $supported_currencies,
            'has_shop_rights'      => crmShop::hasRights(),
        ));
    }
}