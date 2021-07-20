<?php

class crmContactsSearchShopTotalSpentItem
{
    protected $options;
    protected $range_val;

    public function __construct($options = array()) {
        $this->options = $options;
    }
    public function getHtml()
    {
        return crmHelper::renderViewAction(new crmContactSearchShopItemAction(array(
            'id' => 'shop.customers.total_spent',
            'from' => $this->getRangeVal('from'),
            'to' => $this->getRangeVal('to')
        )));
    }

    public function getTitle()
    {
        $from = $this->getRangeVal('from');
        $to = $this->getRangeVal('to');
        if (($from || $from === 0 || $from === '0') && ($to || $to === 0 || $to === '0')) {
            return $from . '–' . $to;
        } else if ($from || $from === 0 || $from === '0') {
            return $from;
        } else {
            return $to;
        }
    }

    protected function getConds()
    {
        return ifset($this->options['conds'], array());
    }

    protected function getRangeVal($key = null)
    {
        if ($this->range_val === null) {
            $conds = $this->getConds();
            $val_item = '';
            if (isset($conds['shop']['customers']['total_spent'])) {
                $val_item = $conds['shop']['customers']['total_spent'];
            } else if (isset($conds['total_spent'])) {
                $val_item = $conds['total_spent'];
            }
            if ($val_item) {
                $this->range_val = $this->extra($val_item);
                $this->range_val['from'] = ifset($this->range_val['from'], '');
                $this->range_val['to'] = ifset($this->range_val['to'], '');
            }
        }
        if ($key !== null && $this->range_val !== null) {
            return ifset($this->range_val, $key, '');
        }
        return $this->range_val;
    }

    public function extra($val_item = '')
    {
        $op = '=';
        $val = '';
        if (is_array($val_item)) {
            $val = ifset($val_item['val'], '');
            $op = ifset($val_item['op'], '=');
            if ($op === '=') {
                $val = explode('--', $val);
                return array(
                    'from' => ifset($val[0], ''),
                    'to' => ifset($val[1], '')
                );
            } else if ($op === '>=') {
                return array(
                    'from' => $val,
                    'to' => ''
                );
            } else {
                return array(
                    'from' => '',
                    'to' => $val
                );
            }
        }
        return array(
            'from' => '',
            'to' => ''
        );
    }

    protected function getWhere($val_item)
    {
        $op = ifset($val_item['op'], '>=');
        $val = ifset($val_item['val'], '0');
        if ($op !== '<=' && $op !== '>=' && $op !== '=') {
            $op = '>=';
        }
        $m = new waModel();
        $where = "";
        if ($op === '=') {
            $val = explode('--', $val);
            $val[0] = $m->escape(ifset($val[0], '0'));
            $val[1] = $m->escape(ifset($val[1], '0'));
            $where = ":field >= '{$val[0]}' AND :field <= '{$val[1]}'";
        } else {
            $where = ":field {$op} '{$val}'";
        }
        return $where;
    }

    public function where($val_item = '')
    {
        $conds = $this->getConds();
        if (isset($conds['shop']['customers']['payed_orders'])) {
            return str_replace(':field', ':tbl_customer.total_spent', $this->getWhere($val_item));
        }
        return '';
    }

    public function having($val_item = '')
    {
        $conds = $this->getConds();
        if (!isset($conds['shop']['customers']['payed_orders'])) {
            return str_replace(':field', 'SUM(:tbl_order.total * :tbl_order.rate)', $this->getWhere($val_item));
        }
        return '';
    }
}
