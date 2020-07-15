<?php

wa('shop');     // cause use shop models

class crmContactsSearchShopUtmCampaignItem
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
            'select_options' => $this->getUtmCampaign()
        )));
    }

    protected function getId()
    {
        return 'shop.customers.utm_campaign';
    }

    protected function getUtmCampaign()
    {
        $omp = new shopOrderParamsModel();
        return array_merge(array(
            array(
                'id' => ':any',
                'name' => _w('Any UTM campaign')
            )),
            $omp->getAllUtmCampaign()
        );
    }

    protected function getConds()
    {
        return ifset($this->options['conds'], array());
    }

    public function getValItem()
    {
        $conds = $this->getConds();
        $val_item = array('val' => '', 'op' => '=');
        if (isset($conds['shop']['customers']['utm_campaign'])) {
            $val_item = $conds['shop']['customers']['utm_campaign'];
        } else if (isset($conds['utm_campaign'])) {
            $val_item = $conds['utm_campaign'];
        }
        return $val_item;
    }

    public function join()
    {
        $val_item = $this->getValItem();
        if ($val_item['val'] !== ':any') {
            $m = new waModel();
            $val = $m->escape($val_item['val']);
            return array(
                'table' => 'shop_order_params',
                'on' => ":table.order_id = :tbl_order.id AND :table.name = 'utm_campaign' AND :table.value = '{$val}'"
            );
        } else {
            return array(
                'table' => 'shop_order_params',
                'on' => ":table.order_id = :tbl_order.id AND :table.name = 'utm_campaign'"
            );
        }
    }

    public function getTitle()
    {
        $val_item = $this->getValItem();
        if ($val_item['val'] === ':any') {
            return 'Any UTM campaign';          // _wp('Any UTM campaign')
        }
        return $val_item['val'];
    }
}
