<?php

class crmContactsSearchCreateMethodValues
{
    /**
     * @var waContactModel
     */
    private $model;


    private $conds = null;

    private $default_options = array(
        'limit' => 50,
        'offset' => 0,
        'order' => array('count', 'DESC'),
        'autocomplete' => [
            'term' => '',
        ],
    );


    public function __construct($conds = null)
    {
        $this->conds = $conds;
        $this->model = new waContactModel();
    }

    private function mixOptions($options, $default_options)
    {
        $options = array_merge($default_options, $options);
        if (is_array($options['order'])) {
            $options['order'] = implode(' ', $options['order']);
        }
        return $options;
    }

    public function getValues($options = array())
    {
        $options = $this->mixOptions($options, $this->default_options);
        $bind_params = [
            'offset' => $options['offset'],
            'limit' => $options['limit'],
        ];
        $term = trim(ifset($options['autocomplete']['term'], ''));
        $sql_t = "SELECT DISTINCT
                CONCAT(create_app_id, '.', create_method) AS value,
                IF(LENGTH(create_method) > 0,
                        CONCAT(create_method, ' (' , create_app_id, ')'),
                        create_app_id) AS name
                FROM `wa_contact`";
        if ($term) {
            $sql_t .= " WHERE create_app_id LIKE '%l:term%' OR create_method LIKE '%l:term%'";
            $bind_params['term'] = $term;
        }
        $sql_t .= " ORDER BY name LIMIT i:offset, i:limit";
        $values = $this->model->query($sql_t, $bind_params)->fetchAll();
        if (!$values) {
            return array();
        }

        return $values;
    }

    public function count($options = array())
    {
        return $this->model->query("SELECT DISTINCT create_app_id, create_method FROM `wa_contact`")->fetchField();
    }

    public function limit()
    {
        return $this->default_options['limit'];
    }

    /**
     * @param mixed $val_item
     */
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
            $term = explode(".", $val);
            $where = array();
            if (empty($term[0])) {
                $where[] = "(c.create_app_id IS NULL OR c.create_app_id = '')";
            } else {
                $term[0] = $this->model->escape($term[0]);
                $where[] = "c.create_app_id = '{$term[0]}'";
            }
            if (empty($term[1])) {
                $where[] = "(c.create_method IS NULL OR c.create_method = '')";
            } else {
                $term[1] = $this->model->escape($term[1]);
                $where[] = "c.create_method = '{$term[1]}'";
            }
            return implode(' AND ', $where);
        }
        return '';
    }

    public function getHighlightTerm($conds)
    {
        return false;
    }

    public function extra($conds)
    {
        $extra = array();
        if ($conds && is_string($conds)) {
            $term = explode(".", $conds);
            if (count($term) > 1) {
                $item = $this->model->getByField(array(
                    'create_app_id' => $term[0],
                    'create_method' => $term[1]
                ));
                if ($item) {
                    $extra['name'] = $item['create_method'] ? $item['create_method'] . " ({$item['create_app_id']})" : $item['create_app_id'];
                }
            }
        }

        return $extra;
    }
}
