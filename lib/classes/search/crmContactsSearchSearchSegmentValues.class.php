<?php

/**
 * Autocomplete / simple-condition metadata for dynamic CRM segments (crm_segment.type = search).
 * Actual filtering is applied in crmContactsCollection::applySearchSegmentSetFilter().
 */
class crmContactsSearchSearchSegmentValues
{
    /**
     * @var crmSegmentModel
     */
    private $sm;

    private $default_options = [
        'limit' => 10,
        'offset' => 0,
        'order' => ['sort', 'DESC'],
        'exclude_id' => 0,
        'autocomplete' => [
            'term' => '',
        ],
    ];

    public function __construct()
    {
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
        $exclude_id = (int) ifset($options['exclude_id'], 0);
        return $this->findSegments($term, $limit, $exclude_id);
    }

    protected function findSegments($val_item, $limit = null, $exclude_id = 0)
    {
        $user = wa()->getUser();
        $contact_ids = [$user->getId()];
        if ($user->isAdmin()) {
            $contact_ids[] = 0;
        }

        $where = [
            'cs.type = :type',
            'cs.archived = 0',
            "cs.hash <> 'crmSearch/'",
            '(cs.contact_id IN (:contact_ids) OR cs.shared > 0)',
        ];
        $bind_params = [
            'type' => crmSegmentModel::TYPE_SEARCH,
            'contact_ids' => $contact_ids,
        ];
        if ($exclude_id > 0) {
            $where[] = 'cs.id != :exclude_id';
            $bind_params['exclude_id'] = $exclude_id;
        }

        $val = $this->getValFromValItem($val_item);

        if (wa_is_int($val)) {
            $where[] = 'cs.id = :id';
            $bind_params['id'] = $val;
        } elseif (strlen($val) > 0) {
            $where[] = "cs.name LIKE '%l:term%'";
            $bind_params['term'] = $val;
        }

        $where_sql = 'WHERE '.join(' AND ', $where);
        $limit_sql = $limit !== null ? "LIMIT {$limit}" : '';

        $sql = "SELECT cs.id AS value, cs.name AS name, cs.count AS count
                    FROM crm_segment cs
                    {$where_sql}
                    ORDER BY cs.shared DESC, cs.sort
                    {$limit_sql}";

        $segments = $this->sm->query($sql, $bind_params)->fetchAll();
        if ($exclude_id > 0 && $segments) {
            $segments = $this->excludeRecursiveCandidates($segments, $exclude_id, $contact_ids);
        }

        return $segments;
    }

    public function where($val_item = '')
    {
        $val = $this->getValFromValItem($val_item);
        if (!$val) {
            return '0=1';
        }

        $segments = $this->findSegments($val_item, wa_is_int($val) ? 1 : null);
        if (!$segments) {
            return '0=1';
        }

        $ids = waUtils::getFieldValues($segments, 'value');
        $ids = waUtils::toIntArray($ids);
        if (wa_is_int($val) && !in_array($val, $ids, true)) {
            return '0=1';
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

    protected function excludeRecursiveCandidates(array $segments, $current_segment_id, array $contact_ids)
    {
        $current_segment_id = (int) $current_segment_id;
        if ($current_segment_id <= 0 || !$segments) {
            return $segments;
        }

        $forbidden_ids = $this->getRecursiveForbiddenIds($current_segment_id, $contact_ids);
        if (!$forbidden_ids) {
            return $segments;
        }

        $result = [];
        foreach ($segments as $segment) {
            $segment_id = (int) ifset($segment, 'value', 0);
            if ($segment_id <= 0 || isset($forbidden_ids[$segment_id])) {
                continue;
            }
            $result[] = $segment;
        }

        return $result;
    }

    protected function getRecursiveForbiddenIds($current_segment_id, array $contact_ids)
    {
        $sql = "SELECT cs.id, cs.hash
                FROM crm_segment cs
                WHERE cs.type = :type
                  AND cs.archived = 0
                  AND cs.hash <> 'crmSearch/'
                  AND (cs.contact_id IN (:contact_ids) OR cs.shared > 0)";
        $rows = $this->sm->query($sql, [
            'type' => crmSegmentModel::TYPE_SEARCH,
            'contact_ids' => $contact_ids
        ])->fetchAll('id');

        if (!$rows) {
            return [$current_segment_id => true];
        }

        $reverse_graph = [];
        foreach ($rows as $segment_id => $row) {
            $deps = $this->extractSearchSegmentDepsFromHash(ifset($row, 'hash', ''));
            foreach ($deps as $dep_id) {
                if (!isset($reverse_graph[$dep_id])) {
                    $reverse_graph[$dep_id] = [];
                }
                $reverse_graph[$dep_id][] = (int) $segment_id;
            }
        }

        $forbidden = [$current_segment_id => true];
        $queue = [$current_segment_id];
        while ($queue) {
            $id = (int) array_shift($queue);
            if (empty($reverse_graph[$id])) {
                continue;
            }
            foreach ($reverse_graph[$id] as $parent_id) {
                if (isset($forbidden[$parent_id])) {
                    continue;
                }
                $forbidden[$parent_id] = true;
                $queue[] = $parent_id;
            }
        }

        return $forbidden;
    }

    protected function normalizeSearchSegmentHash($hash)
    {
        $hash = trim((string) $hash);
        if ($hash === '' || $hash === 'crmSearch/') {
            return '';
        }
        if (strpos($hash, 'crmSearch/') === 0) {
            return substr($hash, strlen('crmSearch/'));
        }
        return $hash;
    }

    protected function extractSearchSegmentDepsFromHash($hash)
    {
        $deps = [];
        $hash = $this->normalizeSearchSegmentHash($hash);
        if ($hash === '') {
            return $deps;
        }

        $parsed = crmContactsSearchHelper::parseHash($hash);
        $conds = ifset($parsed, 'conds', []);

        $nodes = [
            ifset($conds, 'crm', 'search_segment', 'search_segment', []),
            ifset($conds, 'search_segment', 'search_segment', [])
        ];
        foreach ($nodes as $node) {
            if (!$node) {
                continue;
            }
            if (!is_array($node) || !crmContactsSearchHelper::isNumericArray($node)) {
                $node = [$node];
            }
            foreach ($node as $cond) {
                $segment_id = (int) ifset($cond, 'val', 0);
                if ($segment_id > 0) {
                    $deps[$segment_id] = true;
                }
            }
        }

        $set_nodes = [
            ifset($conds, 'crm', 'search_segment', 'search_segment_set', []),
            ifset($conds, 'search_segment', 'search_segment_set', [])
        ];
        foreach ($set_nodes as $set) {
            if (!$set || !is_array($set)) {
                continue;
            }
            foreach (['include_any', 'require_all', 'exclude_any'] as $key) {
                $val = trim((string) ifset($set, $key, 'val', ''));
                if ($val === '') {
                    continue;
                }
                foreach (explode(',', $val) as $id_str) {
                    $segment_id = (int) trim($id_str);
                    if ($segment_id > 0) {
                        $deps[$segment_id] = true;
                    }
                }
            }
        }

        return array_keys($deps);
    }
}
