<?php

class crmContactsSearchShopNumberOfOrdersItem
{
    protected $options;

    public function __construct($options = array()) {
        $this->options = $options;
    }
    public function getHtml()
    {
        $conds = $this->options['conds'];
        if (isset($conds['shop']['customers']['number_of_orders'])) {
            $val_item = $conds['shop']['customers']['number_of_orders'];
        } else if (isset($conds['number_of_orders'])) {
            $val_item = $conds['number_of_orders'];
        }
        return crmHelper::renderViewAction(new crmContactSearchShopItemAction(array(
            'id' => 'shop.customers.number_of_orders',
            'op' => ifset($val_item['op'], ''),
            'val' => ifset($val_item['val'], '')
        )));
    }

    public function where($val_item = '')
    {
        $val = (int) ifset($val_item['val'], '');
        $op = ifset($val_item['op'], '');
        if ($val && in_array($op, array('=', '>', '<', '>=', '<='))) {
            return ":tbl_customer.number_of_orders {$op} '{$val}'";
        }
        return '';
    }
}
