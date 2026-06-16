<?php

class crmContactSearchValuesController extends waJsonController
{  
    public function execute()
    {
        $id = $this->getRequest()->request('id');
        if (is_string($id) && strpos($id, 'crm.category.category_set.') === 0) {
            // Category-set constructor rows reuse legacy segment autocomplete source.
            $id = 'crm.category.category';
        }
        if (is_string($id) && strpos($id, 'crm.search_segment.search_segment_set.') === 0) {
            $id = 'crm.search_segment.search_segment';
        }
        $offset = $this->getRequest()->request('offset', null, 'int');
        $limit = $this->getRequest()->request('limit', null, 'int');
        $term = $this->getRequest()->request('term', null, waRequest::TYPE_STRING_TRIM);
        if ($id === 'contact_info.phone' && $term !== null) {
            $term = waContactPhoneField::cleanPhoneNumber($term);
        }
        
        $options = array();
        if ($offset !== null) {
            $options['offset'] = $offset;
        }
        if ($limit !== null) {
            $options['limit'] = $limit;
        }
        if ($term !== null) {
            $options['autocomplete'] = array( 'term' => $term );
        }
        $segment_id = $this->getRequest()->request('segment_id', 0, waRequest::TYPE_INT);
        if ($segment_id > 0 && is_string($id) && $id === 'crm.search_segment.search_segment') {
            $options['exclude_id'] = $segment_id;
        }
        
        $item = crmContactsSearchHelper::getItem($id, null, array(
            'unwrap_values' => $options
        ));
        
        $term_safe = htmlspecialchars($term);
        $values = ifset($item['items']['values'], array());
        if ($id === 'contact_info.phone') {
            $values = $this->formatPhoneValues($values);
        }
        if ($this->getRequest()->request('highlight')) {
            foreach ($values as &$v) {
                $v['label'] = $this->prepare($v['name'], $term_safe);
            }
            unset($v);
        }
        
        $this->response = array(
            'id' => $id,
            'values' => $values,
            'count' => ifset($item['count'], 0),
            'offset' => $offset,
            'options' => $options
        );
    }

    protected function formatPhoneValues(array $values)
    {
        class_exists('waContactPhoneField');
        $formatter = new waContactPhoneFormatter();

        foreach ($values as &$value) {
            $phone = ifset($value['value'], ifset($value['name'], ''));
            if ($phone !== '') {
                $value['name'] = $formatter->format(waContactPhoneField::cleanPhoneNumber($phone));
            }
        }
        unset($value);

        return $values;
    }
    
    protected function prepare($str, $term_safe)
    {
        $str = htmlspecialchars($str);
        $reg = array();
        foreach (preg_split("/\s+/u", $term_safe) as $t) {
            $t = trim($t);
            if ($t) {
                $reg[] = preg_quote($t, '~');
            }
        }
        if ($reg) {
            $reg = implode('|', $reg);
            $str = preg_replace('~('.$reg.')~ui', '<span class="bold highlighted">\1</span>', $str);
        }
        return $str;
    }
    
}
