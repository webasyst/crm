<?php

class crmShopCurrency_primaryHandler extends waEventHandler
{
    public function execute(&$params)
    {
        try {
            $currency = new crmCurrency();
            $currency->copy();
        } catch (waException $e) {
        }
    }
}
