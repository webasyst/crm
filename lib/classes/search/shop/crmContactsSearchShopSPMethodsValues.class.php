<?php

class crmContactsSearchShopSPMethodsValues
{
    protected $options = array();

    public function __construct($options = array()) {
        $this->options = $options;
        $this->options['type'] = isset($this->options['type']) ? $this->options['type'] : 'shipping';
    }

    protected function getMethods($key = null)
    {
        wa('shop');
        $m = new shopPluginModel();
        return $m->select('id AS value, name')
            ->where(
                'type = :0',
                array($this->options['type'])
            )->fetchAll($key);
    }


    public function getValues()
    {
        return $this->getMethods();
    }

    public function where($val_item)
    {
        $val = '';
        if (is_array($val_item)) {
            if (isset($val_item['val'])) {
                $val = $val_item['val'];
            } else {
                $key = key($val_item);
                $val_item = $val_item[$key];
                $val = $key;
            }
        } else {
            $val = $val_item;
        }
        if ($val) {
            $m = new waModel();
            $val = $m->escape($val);
            $type = $m->escape($this->options['type']);
            return ":table.name = '{$type}_id' AND :table.value = '{$val}'";
        }
        return '';
    }

    public function extra($val_item)
    {
        $val = '';
        if (is_array($val_item) && isset($val_item['val'])) {
            $val = $val_item['val'];
        } else if (is_string($val_item)) {
            $val = $val_item;
        }
        $values = $this->getMethods('value');
        if (isset($values[$val])) {
            return array('name' => $values[$val]['name']);
        }
        return array('name' => $val);
    }
}
