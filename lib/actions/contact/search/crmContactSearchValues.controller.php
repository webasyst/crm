<?php

class crmContactSearchValuesController extends waJsonController
{  
    public function execute()
    {
        $id = $this->getRequest()->request('id');
        $offset = $this->getRequest()->request('offset', null, 'int');
        $limit = $this->getRequest()->request('limit', null, 'int');
        $term = $this->getRequest()->request('term', null, waRequest::TYPE_STRING_TRIM);
        
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
        
        $item = crmContactsSearchHelper::getItem($id, null, array(
            'unwrap_values' => $options
        ));
        
        $term_safe = htmlspecialchars($term);
        $values = ifset($item['items']['values'], array());
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
