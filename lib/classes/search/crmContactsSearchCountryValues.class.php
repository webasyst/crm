<?php

class crmContactsSearchCountryValues
{
    /**
     * @var waCountryModel
     */
    private $model;
    
    private $default_options = array(
        'limit' => 10,
        'offset' => 0,
        'order' => array('count', 'DESC'),
        'autocomplte' => null   // or array with autocomplete options
    );


    public function __construct() {
        $this->model = new waCountryModel();
    }
    
    private function mixOptions($options, $default_options) 
    {
        $options = array_merge($default_options, $options);
        if (is_array($options['order'])) {
            $options['order'] = implode(' ', $options['order']);
        }
        return $options;
    }
    
    public function getValues($options)
    {
        $options = $this->mixOptions($options, $this->default_options);
        if (!empty($options['autocomplete']['term'])) {
            return $this->_getAutocompleteValues($options);
        } else {
            return $this->_getValues($options);
        }
    }

    private function _getValues($options)
    {        
        $sql = "SELECT 
            c.name AS country_name,
            IF (c.name IS NOT NULL, c.name, d.value) AS name,
            IF (c.iso3letter IS NOT NULL, c.iso3letter, d.value) AS value,
            iso3letter,
            COUNT( DISTINCT d.contact_id ) count
            FROM `wa_contact_data` d
            LEFT JOIN `wa_country` c ON c.iso3letter = d.value
            WHERE field = 'address:country'
            GROUP BY c.name, value
            ORDER BY {$options['order']}
            LIMIT {$options['offset']}, {$options['limit']}";
        $values = $this->model->query($sql)->fetchAll();
        $locale = wa()->getLocale();
        $wa_url = wa_url();
        foreach ($values as &$v) {
            if ($v['country_name'] && $locale !== 'en_US') {
                $v['name'] = _ws($v['country_name']);
            }
            if ($v['iso3letter']) {
                $v['icon'] = $wa_url.'wa-content/img/country/'.$v['iso3letter'].'.gif';
            }
        }
        
        return $values;
        
    }
    
    public function limit()
    {
        return $this->default_options['limit'];
    }
    
    private function _getAutocompleteValues($options)
    {
        $term = $this->model->escape(ifset($options['autocomplete']['term'], ''), 'like');
        
        if ($term) {
            
            $found = array();
            $found_cnt = 0;
            
            $term = mb_strtolower($term);
            
            $current_locale = wa()->getLocale();
            
            if ($current_locale !== 'en_US') {
                $l18_countries = $this->getCountries();
                foreach ($l18_countries as $item) {
                    if (mb_strpos(mb_strtolower($item['name']), $term) === 0) {
                        $found[] = $item;
                        $found_cnt += 1;
                        if ($found_cnt >= $options['limit']) {
                            break;
                        }
                    }
                }
            }

            if ($found_cnt < $options['limit']) {
                if ($current_locale === 'en_US') {
                    $sql = "SELECT
                        IF(c.name IS NOT NULL, c.name, value) AS name, 
                        IF(c.iso3letter IS NOT NULL, c.iso3letter, d.value) AS value, 
                        COUNT( DISTINCT d.contact_id ) count,
                        c.iso3letter
                    FROM `wa_contact_data` d
                    LEFT JOIN `wa_country` c ON c.iso3letter = d.value
                    WHERE field = 'address:country' AND (value LIKE '%{$term}%' OR name LIKE '%{$term}%')
                    GROUP BY c.name, value
                    ORDER BY count DESC
                    LIMIT {$options['offset']}, {$options['limit']}";
                    $found = $this->model->query($sql)->fetchAll();
                } else {
                    $sql = "SELECT value AS name, value, COUNT( DISTINCT d.contact_id ) count
                    FROM `wa_contact_data` d
                    WHERE field = 'address:country' AND value LIKE '%{$term}%'
                    GROUP BY value
                    ORDER BY count DESC
                    LIMIT {$options['offset']}, {$options['limit']}";
                    foreach ($this->model->query($sql)->fetchAll() as $c) {
                        $found[] = $c;
                    }
                    usort($found, array($this, 'sortByCount'));
                    $found = array_slice($found, 0, $options['limit']);
                }
            }
        } else {
            return $this->getValues();
        }
        
        $wa_url = wa_url();
        foreach ($found as &$v) {
            if (ifset($v['iso3letter'])) {
                $v['icon'] = $wa_url.'wa-content/img/country/'.$v['iso3letter'].'.gif';
            }
        }
        unset($v);
        
        return $found;
        
    }
    
    private function sortByCount($a, $b)
    {
        return ifset($b['count']) - ifset($a['count']);
    }


    public function count($options)
    {
        $sql_t = "SELECT COUNT(DISTINCT IF(c.name, c.name, value))
        FROM wa_contact_data d
        LEFT JOIN `wa_country` c ON c.iso3letter = d.value
        WHERE field = 'address:country' :autocomplete";
        $term = $this->model->escape(ifset($options['autocomplete']['term'], ''), 'like');
        if ($term) {
            $sql = str_replace(":autocomplete", "AND (value LIKE '%{$term}%' OR name LIKE '%{$term}%')", $sql_t);
        } else {
            $sql = str_replace(":autocomplete", '', $sql_t);
        }
        return $this->model->query($sql)->fetchField();
    }
    
    private function getCountries()
    {
        $sql = "SELECT c.name AS name, d.value, c.iso3letter, COUNT( DISTINCT d.contact_id ) count
        FROM `wa_contact_data` d
        JOIN `wa_country` c ON c.iso3letter = d.value
        WHERE field = 'address:country'
        GROUP BY c.iso3letter
        ORDER BY count DESC";
        $countries = $this->model->query($sql)->fetchAll();
        foreach ($countries as &$c) {
            $c['name'] = _ws($c['name']);
        }
        unset($c);
        
        return $countries;
    }
    
    public function getHighlightTerm($conds)
    {
        if ($conds && is_string($conds)) {
            $term = $conds;
            $country = $this->model->getByField('iso3letter', $term);
            if ($country) {
                return _ws($country['name']);
            }
            if (substr($conds, 0, 1) === '~') {
                return substr($conds, 1);
            } else {
                return $conds;
            }
        }
        
        return false;
    }
    
    public function extra($conds)
    {
        $extra = array();
        if ($conds && is_string($conds)) {
            $term = $conds;
            $item = $this->model->getByField('iso3letter', $term);
            if ($item) {
                $extra['name'] = _ws($item['name']);
                $extra['icon'] = wa_url() . 'wa-content/img/country/'.$item['iso3letter'].'.gif';
            }
        }
        
        return $extra;
    }
}
