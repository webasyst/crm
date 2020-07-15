<?php

wa('shop');     // cause use shop models

class crmContactsSearchShopStorefrontItem
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
            'select_options' => $this->getStorefronts()
        )));
    }

    protected function getId()
    {
        return 'shop.customers.storefront';
    }

    protected function getStorefronts()
    {
        foreach (wa()->getRouting()->getByApp('shop') as $domain => $domain_routes) {
            foreach ($domain_routes as $route) {
                $url = rtrim($domain.'/'.$route['url'], '/*');
                $storefronts[] = array(
                    'id' => $url,
                    'name' => $url
                );
            }
        }
        $storefronts[] = array(
            'id' => ':backend',
            'name' => _w('Backend')
        );
        return $storefronts;
    }

    protected function getConds()
    {
        return ifset($this->options['conds'], array());
    }

    public function getValItem()
    {
        $conds = $this->getConds();
        $val_item = array('val' => '', 'op' => '=');
        if (isset($conds['shop']['customers']['storefront'])) {
            $val_item = $conds['shop']['customers']['storefront'];
        } else if (isset($conds['storefront'])) {
            $val_item = $conds['storefront'];
        }
        return $val_item;
    }

    public function join()
    {
        $val_item = $this->getValItem();
        if ($val_item['val'] === ':backend') {
            return array(
                'table' => 'shop_order_params',
                'type' => 'left_join',
                'on' => ":table.order_id = :tbl_order.id AND :table.name = 'storefront'"
            );
        } else {
            return array(
                'table' => 'shop_order_params',
                'on' => ":table.order_id = :tbl_order.id AND :table.name = 'storefront'"
            );
        }
    }

    public function where()
    {
        $val_item = $this->getValItem();
        if ($val_item['val'] === ':backend') {
            return ':table.value IS NULL';
        } else {
            $m = new waModel();
            $val = $m->escape(urldecode($val_item['val']));
            $val = trim($val, '/');
            $val = "'".$val."', '" . $val . "/'";
            return ":table.value IN({$val})";
        }
    }

    public function getTitle()
    {
        $val_item = $this->getValItem();
        return $val_item['val'] !== ':backend' ? urldecode($val_item['val']) : 'Backend';
    }
}
