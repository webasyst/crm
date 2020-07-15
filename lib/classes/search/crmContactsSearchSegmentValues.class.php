<?php

class crmContactsSearchSegmentValues
{
    /**
     * @var crmSegmentModel
     */
    private $sm;

    private $default_options = [
        'limit' => 10,
        'offset' => 0,
        'order' => ['cnt', 'DESC'],
        'autocomplete' => [
            'term' => '',
        ],
    ];

    public function __construct() {
        $this->sm = new crmSegmentModel();
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
        $term = trim(ifset($options['autocomplete']['term'], ''));
        $limit = intval(ifset($options['limit']));
        return $this->findSegments($term, $limit);
    }

    protected function findSegments($val_item, $limit = null)
    {
        $user = wa()->getUser();
        $contact_ids = [$user->getId()];
        if ($user->isAdmin()) {
            $contact_ids[] = 0;
        }

        $where = [
            'cs.type = :type',
            '(contact_id IN (:contact_ids) OR shared > 0)'
        ];
        $bind_params = [
            'type' => crmSegmentModel::TYPE_CATEGORY,
            'contact_ids' => $contact_ids
        ];

        $val = $this->getValFromValItem($val_item);

        if (wa_is_int($val)) {
            $where[] = 'cs.id = :id';
            $bind_params['id'] = $val;
        } elseif (strlen($val) > 0) {
            $where[] = "(cs.name LIKE '%l:term%' OR cc.name LIKE '%l:term%')";
            $bind_params['term'] = $val;
        }

        $where = "WHERE ". join(' AND ', $where);
        $limit = $limit !== null ? "LIMIT {$limit}" : '';

        $sql = "SELECT cs.id AS value, cs.name AS cs_name, cc.name AS cc_name, cc.cnt AS count 
                    FROM crm_segment cs
                    JOIN wa_contact_category cc ON cc.id = cs.category_id 
                    {$where}
                    {$limit}";

        $result = $this->sm->query($sql, $bind_params)->fetchAll();
        foreach ($result as &$item) {
            $item['name'] = $item['cs_name'];
            if (!$item['name']) {
                $item['name'] = $item['cc_name'];
            }
            unset($item['cs_name'], $item['cc_name']);
        }
        unset($item);

        return $result;
    }

    public function where($val_item = '')
    {
        $val = $this->getValFromValItem($val_item);
        if (!$val) {
            return ':segment.id = -1';
        }

        $segments = $this->findSegments($val_item, wa_is_int($val) ? 1 : null);
        if (!$segments) {
            return ':segment.id = -1';
        }

        $ids = waUtils::getFieldValues($segments, 'value');
        $ids = waUtils::toIntArray($ids);
        if (wa_is_int($val) && !in_array($val, $ids, true)) {
            return ':segment.id = -1';
        }

        $ids_str = join(',', $ids);

        return ":segment.id IN({$ids_str})";
    }

    public function extra($val_item)
    {
        $segments = $this->findSegments($val_item, 1);
        if (!$segments) {
            return ['name' => ''];
        }
        $segment = reset($segments);
        return ['name' => $segment['name']];
    }

    protected function getValFromValItem($val_item)
    {
        $val = $val_item;
        if (is_array($val_item)) {
            $val = ifset($val_item['val']);
        }

        $op = '=';
        if (is_array($val_item) && isset($val_item['op'])) {
            $op = $val_item['op'];
        }

        if (wa_is_int($val) && $val > 0 && $op == '=') {
            $val = intval($val);
        } elseif (is_string($val)) {
            $val = trim(strval($val));
        } else {
            $val = '';
        }

        return $val;
    }

}
