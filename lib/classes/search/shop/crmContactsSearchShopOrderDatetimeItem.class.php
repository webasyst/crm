<?php

class crmContactsSearchShopOrderDatetimeItem
{
    protected $options;

    public function __construct($options = array()) {
        $this->options = $options;
    }
    public function getHtml()
    {
        $val_item = $this->parseVal($this->getValItem());
        return crmHelper::renderViewAction(new crmContactSearchShopItemAction(array(
            'id' => $this->getId(),
            'op' => $val_item['op'],
            'val' => $val_item['val'],
            'mode' => $val_item['mode']
        )));
    }

    protected function getId()
    {
        return $this->getType() === 'last' ? 'shop.customers.last_order_datetime' : 'shop.customers.first_order_datetime';
    }


    protected function getConds()
    {
        return ifset($this->options['conds'], array());
    }

    protected function getType()
    {
        return ifset($this->options['type'], 'last');
    }

    public function getValItem()
    {
        $conds = $this->getConds();
        $key = $this->getType() === 'last' ? 'last_order_datetime' : 'first_order_datetime';
        $val_item = array(
            'op' => '', 'val' => ''
        );
        if (isset($conds['shop']['customers'][$key])) {
            $val_item = $conds['shop']['customers'][$key];
        } else if (isset($conds[$key])) {
            $val_item = $conds[$key];
        }
        return $val_item;
    }

    public function parseVal($val_item)
    {
        $res = array(
            'val' => ifset($val_item['val'], ''),
            'op' => ifset($val_item['op'], ''),
            'mode' => 'offset'
        );
        if (!in_array($res['val'], array('-30d', '-90d', '-180d', '-365d', ''))) {
            $res['mode'] = 'date';
        }
        return $res;
    }

    public function having($val_item = '')
    {
        $val_item = $this->parseVal($val_item);
        $val = trim($val_item['val']);
        $op = $val_item['op'];
        if (!in_array($op, array('<', '>', '=', '>=', '<='))) {
            return '';
        }
        $agr_func = $this->getType() === 'last' ? 'MAX' : 'MIN';
        if (preg_match('/^[\d]{4,}-[\d]{2,}-[\d]{2,}$/', $val)) {
            return "{$agr_func}(:tbl_order.create_datetime) {$op} '{$val}'";
        } else {
            if (!is_numeric(substr($val, 0, -1))) {
                return '';
            }
            $quantifier = substr($val, -1);
            $shift = substr($val, 0, -1);
            if ($quantifier === 'd') {
                $date = date('Y-m-d', strtotime("{$shift} days"));
                return "{$agr_func}(:tbl_order.create_datetime) {$op} '{$date}'";
            } else {
                return '';
            }
        }
    }

    public function getTitle()
    {
        $val_item = $this->parseVal($this->getValItem());
        if ($val_item['mode'] === 'offset') {
            switch ($val_item['val']) {
                case '-30d':
                    return '30 days ago';           // _wp('30 days ago')
                case '-90d':
                    return '90 days ago';           // _wp('90 days ago')
                case '-180d':
                    return '180 days ago';          // _wp('180 days ago')
                case '-365d':
                    return '365 days ago';
                default:
                    return $val_item['val'];
            }
        } else {
            return date('d.m.Y', strtotime($val_item['val']));
        }
    }

}
