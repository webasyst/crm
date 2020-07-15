<?php

class crmContactsSearchShopOrderStatesValues
{
    public function getValues()
    {
        wa('shop');
        $workflow = new shopWorkflow();
        $states = array();
        foreach ($workflow->getAllStates() as $state_id => $state) {
            $states[] = array(
                'value' => $state_id,
                'name' => _wd('shop', $state->getName())
            );
        }
        return $states;
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
            return ":tbl_order.state_id = '{$val}'";
        }
        return '';
    }
}
