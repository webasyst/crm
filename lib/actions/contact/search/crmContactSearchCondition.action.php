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
        $unwrap_values = array(
            'when_readonly' => true
        );
        if ($id === 'contact_info.creating') {
            $unwrap_values = true;
        }
        $item = crmContactsSearchHelper::getItem($id, $hash, array(
            'unwrap_values' => $unwrap_values
        ));
        
        crmContactsSearchHelper::setContactItems($id);
        
        $count = '';
        $this->view->assign(array(
            'id' => $id,
            'condition' => $item,
            'count' => $count,
            'conds' => $conds,
            'extra' => $extra,
            'category_set_values' => $this->getCategorySetValues(),
            'search_segment_set_values' => $this->getSearchSegmentSetValues()
        ));
    }
    
    public function isNumericArray($ar)
    {
        return is_array($ar) && count(array_filter(array_keys($ar), "is_numeric")) === count($ar);
    }

    protected function getCategorySetValues()
    {
        $values_provider = new crmContactsSearchSegmentValues();
        $rows = $values_provider->getValues([
            'limit' => 500
        ]);
        $result = [];
        foreach ((array) $rows as $row) {
            if (empty($row['value']) || !wa_is_int($row['value'])) {
                continue;
            }
            $result[] = [
                'id' => (int) $row['value'],
                'name' => ifset($row, 'name', '')
            ];
        }
        return $result;
    }

    protected function getSearchSegmentSetValues()
    {
        $exclude_id = $this->getCurrentSegmentId();
        $values_provider = new crmContactsSearchSearchSegmentValues();
        $rows = $values_provider->getValues([
            'limit' => 500,
            'exclude_id' => $exclude_id
        ]);
        $result = [];
        foreach ((array) $rows as $row) {
            if (empty($row['value']) || !wa_is_int($row['value'])) {
                continue;
            }
            $result[] = [
                'id' => (int) $row['value'],
                'name' => ifset($row, 'name', '')
            ];
        }
        return $result;
    }

    protected function getCurrentSegmentId()
    {
        $id = (int) $this->getRequest()->param('segment_id');
        if ($id > 0) {
            return $id;
        }

        $id = (int) $this->getRequest()->request('segment_id', 0, waRequest::TYPE_INT);
        return $id > 0 ? $id : 0;
    }

}
