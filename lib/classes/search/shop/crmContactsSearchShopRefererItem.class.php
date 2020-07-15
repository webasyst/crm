<?php

wa('shop');     // cause use shop models

class crmContactsSearchShopRefererItem
{
    protected $options;

    public function __construct($options = array()) {
        $this->options = $options;
    }
    public function getHtml()
    {
        $val_item = $this->getValItem();
        $val_item['val'] = str_replace('\\/', '/', $val_item['val']);
        return crmHelper::renderViewAction(new crmContactSearchShopItemAction(array(
            'id' => $this->getId(),
            'op' => $val_item['op'],
            'val' => $val_item['val'],
            'select_options' => $this->getReferers()
        )));
    }

    protected function getId()
    {
        return 'shop.customers.referer';
    }

    protected function getReferers()
    {
        $traffic_sources = wa('shop')->getConfig()->getOption('traffic_sources');
        $m = new waModel();
        $op = new shopOrderParamsModel();
        $refers = array();
        foreach ($op->getByField('name', 'referer_host', 'value') as $item) {
            if ($item['value']) {
                $refers[$item['value']] = $item['value'];
            }
        }
        foreach ($traffic_sources as $source_id => $source_param) {
            $refers[$source_id] = $source_id;
        }
        return array_values($refers);
    }

    protected function getConds()
    {
        return ifset($this->options['conds'], array());
    }

    public function getValItem()
    {
        $conds = $this->getConds();
        $val_item = array('val' => '', 'op' => '=');
        if (isset($conds['shop']['customers']['referer'])) {
            $val_item = $conds['shop']['customers']['referer'];
        } else if (isset($conds['referer'])) {
            $val_item = $conds['referer'];
        }
        return $val_item;
    }

    public function join()
    {
        $val_item = $this->getValItem();
        $m = new waModel();
        $val = $m->escape($val_item['val']);
        return array(
            'table' => 'shop_order_params',
            'on' => ":table.order_id = :tbl_order.id AND :table.name = 'referer_host' AND :table.value = '{$val}'"
        );
    }
}
