<?php

wa('shop');     // cause use shop models

class crmContactsSearchShopCouponItem
{
    protected $options;

    public function __construct($options = array()) {
        $this->options = $options;
    }
    public function getHtml()
    {
        $val_item = $this->getValItem();
        return crmHelper::renderViewAction(new crmContactSearchShopItemAction(array(
            'id' => $this->getId(),
            'op' => $val_item['op'],
            'val' => $val_item['val'],
            'select_options' => $this->getCoupons()
        )));
    }

    protected function getId()
    {
        return 'shop.customers.coupon';
    }

    protected function getCoupons()
    {
        $cm = new shopCustomerModel();
        $coupons = array(
            array(
                'id' => ':any',
                'name' => _w('Any discount coupon')
            )
        );
        foreach ($cm->getAllCoupons() as $c) {
            $coupons[] = array(
                'id' => $c['id'],
                'name' => $c['code'] ? $c['code'] : (_w('Coupon') . ' # ' . $c['id'])
            );
        }
        return $coupons;
    }

    protected function getConds()
    {
        return ifset($this->options['conds'], array());
    }

    public function getValItem()
    {
        $conds = $this->getConds();
        $val_item = array('val' => '', 'op' => '=');
        if (isset($conds['shop']['customers']['coupon'])) {
            $val_item = $conds['shop']['customers']['coupon'];
        } else if (isset($conds['coupon'])) {
            $val_item = $conds['coupon'];
        }
        return $val_item;
    }

    public function join()
    {
        $val_item = $this->getValItem();
        $on = ":table.order_id = :tbl_order.id AND :table.name = 'coupon_id'";
        if ($val_item['val'] !== ':any') {
            $coupon_id = (int) $val_item['val'];
            $on .= " AND :table.value = '{$coupon_id}'";
        }
        return array(
            'table' => 'shop_order_params',
            'on' => $on
        );
    }

    public function getTitle()
    {
        $val_item = $this->getValItem();
        if ($val_item['val'] === ':any') {
            return 'Any discount coupon';           // _wp('Any discount coupon')
        } else {
            $m = new shopCouponModel();
            $name = 'Coupon' . ' # ' . $val_item['val'];            // _wp('Coupon')
            $coupon = $m->getById($val_item['val']);
            if ($coupon) {
                $name = $coupon['code'];
            }
            return $name;
        }
    }

}
