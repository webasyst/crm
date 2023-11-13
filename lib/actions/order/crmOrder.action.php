<?php

class crmOrderAction extends waViewAction
{
    const ORDERS_UPDATE_LIST = 60000;

    public function execute()
    {
        if (wa()->whichUI('crm') !== '1.3' && wa()->appExists('shop')) {
            wa('shop');
            (new shopOrderAction)->execute();
            $this->view->assign([
                'timeout' => self::ORDERS_UPDATE_LIST
            ]);
        }
    }

    public static function checkSkipUpdateLastPage()
    {
        waRequest::setParam('skip_update_last_page', '1');
    }
}
