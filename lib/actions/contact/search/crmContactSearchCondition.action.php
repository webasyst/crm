<?php

class crmContactSearchConditionAction extends waViewAction
{  
    public function execute()
    {
        $id = $this->getRequest()->get('id');
        if (!$id) {
            throw new waException("Unknown condition id");
        }
       
        $hash = $this->getRequest()->get('hash', null);
        $hash_ar = crmContactsSearchHelper::parseHash($hash);
        $conds = $hash_ar['conds'];
        $p = &$conds;
        foreach (explode('.', $id) as $k) {
            if (isset($p[$k])) {
                $p = &$p[$k];
            }
        }
        $conds = $p;
        
        if ($this->isNumericArray($conds)) {
            $conds = reset($conds);
        }
        
        $extra = array();
        if (is_array($conds) && isset($conds['country'])) {
            $country = $conds['country'];
            $m = new waCountryModel();
            $country = $m->getByField('iso3letter', $country);
            if ($country) {
                if (wa()->getLocale() !== 'en_US') {
                    $country['name'] = _ws($country['name']);
                }
                $extra = $country;
            }
        }
        $item = crmContactsSearchHelper::getItem($id, $hash, array(
            'unwrap_values' => array(
                'when_readonly' => true
            )
        ));
        
        crmContactsSearchHelper::setContactItems($id);
        
        $count = '';
        $this->view->assign(array(
            'id' => $id,
            'condition' => $item,
            'count' => $count,
            'conds' => $conds,
            'extra' => $extra
        ));
    }
    
    public function isNumericArray($ar)
    {
        return is_array($ar) && count(array_filter(array_keys($ar), "is_numeric")) === count($ar);
    }
    
}
