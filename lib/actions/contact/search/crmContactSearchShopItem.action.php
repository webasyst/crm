<?php

class crmContactSearchShopItemAction extends waViewAction
{
    protected $params;
    public function __construct($params = null) {
        $this->params = $params;
        return parent::__construct($params);
    }
    public function execute()
    {
        $this->view->assign(array(
            'uniqid' => uniqid('crm_contacts_shop_search')
        ) + $this->params);
    }
}
