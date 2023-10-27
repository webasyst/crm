<?php

class crmContactsSearchRegionValues
{
    /**
     * @var waCountryModel
     */
    private $model;
    protected $conds;

    private $default_options = array(
        'limit' => 10,
        'offset' => 0,
        'order' => array('count', 'DESC'),
        'autocomplete' => null   // or array with autocomplete options
    );


    public function __construct($conds = null) {
        $this->model = new waRegionModel();
        $this->conds = $conds;

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
        $sql_t = "SELECT
            IF(r.name IS NOT NULL, r.name, dr.value) AS name,
            IF(r.code IS NOT NULL, r.code, dr.value) AS value,
            r.country_iso3,
            COUNT( DISTINCT dr.contact_id ) count
        FROM `wa_contact_data` dr
        JOIN `wa_contact_data` dc ON dr.contact_id = dc.contact_id
        LEFT JOIN `wa_region` r ON r.code = dr.value AND r.country_iso3 = dc.value
        WHERE dr.field = 'address:region' AND dc.field = 'address:country' :autocomplete
        GROUP BY
            IF(r.name IS NOT NULL, r.name, dr.value),
            IF(r.code IS NOT NULL, r.code, dr.value),
            r.country_iso3
        ORDER BY :order
        LIMIT :offset, :limit";

        if (!empty($options['autocomplete']['term'])) {
            $sql_t = str_replace(
                ':autocomplete',
                "AND (dr.value LIKE '%{$options['autocomplete']['term']}%' OR r.name LIKE '%{$options['autocomplete']['term']}%')",
                $sql_t
            );
        } else {
            $sql_t = str_replace(':autocomplete',  '',  $sql_t);
        }

        $sql = str_replace(
            array(':order', ':offset', ':limit'),
            array($options['order'], $options['offset'], $options['limit']),
            $sql_t
        );

        $values = $this->model->query($sql)->fetchAll();
        $wa_url = wa_url();
        foreach ($values as &$v) {
            if ($v['country_iso3']) {
                $v['icon'] = $wa_url.'wa-content/img/country/'.$v['country_iso3'].'.gif';
                $v['value'] = $v['country_iso3'] . ':' . $v['value'];
            }
        }
        return $values;
    }

    public function count($options)
    {
        $sql_t = "SELECT COUNT(DISTINCT value)
            FROM wa_contact_data
            WHERE field = 'address:region' :autocomplete";
        $term = $this->model->escape(ifset($options['autocomplte']['term'], ''), 'like');
        if ($term) {
            $sql = str_replace(":autocomplete", "AND value LIKE '%{$term}%'", $sql_t);
        } else {
            $sql = str_replace(":autocomplete", '', $sql_t);
        }
        return $this->model->query($sql)->fetchField();
    }

    public function limit()
    {
        return $this->default_options['limit'];
    }

    public function getHighlightTerm($conds)
    {
        if ($conds && is_string($conds)) {
            $term = $conds;
            if (strpos($term, ":") === false) {
                if (substr($term, 0, 1) === '~') {
                    return substr($term, 1);
                } else {
                    return $term;
                }
            }
            $term = explode(":", $term);
            $region = $this->model->getByField(array(
                'country_iso3' => $term[0],
                'code' => $term[1]
            ));
            if ($region) {
                return $region['name'];
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
            if (strpos($term, ":") === false) {
                $extra['name'] = $term;
                return $extra;
            }
            $term = explode(":", $term);
            $region = $this->model->getByField(array(
                'country_iso3' => $term[0],
                'code' => $term[1]
            ));
            if ($region) {
                $extra['name'] = $region['name'];
                $extra['icon'] = wa_url() . 'wa-content/img/country/'.$region['country_iso3'].'.gif';
            }
        }

        return $extra;
    }
}
