<?php

class crmTagModel extends crmModel
{
    protected $table = 'crm_tag';

    const CLOUD_MAX_SIZE = 150;
    const CLOUD_MIN_SIZE = 80;
    const CLOUD_MAX_OPACITY = 100;
    const CLOUD_MIN_OPACITY = 30;

    public function getCloud($key = null, $limit = 0, $tag_names = [], $tag_ids = [])
    {
        $where = ['count > 0'];
        $conditions = [];
        if (!empty($tag_names)) {
            $where[] = 'name IN (:names)';
            $conditions = ['names' => $tag_names];
        }
        if (!empty($tag_ids)) {
            $where[] = 'id IN (:ids)';
            $conditions = ['ids' => $tag_ids];
        }
        $query = $this->where(join(' AND ', $where), $conditions);

        if ($limit) {
            $query->order('count DESC');
            $query->limit((int)$limit);
        } else {
            $query->order('name');
        }
        $tags = $query->fetchAll($key);
        if (!empty($tags)) {
            $first = current($tags);
            $max_count = $min_count = $first['count'];
            foreach ($tags as $tag) {
                if ($tag['count'] > $max_count) {
                    $max_count = $tag['count'];
                }
                if ($tag['count'] < $min_count) {
                    $min_count = $tag['count'];
                }
            }
            $diff = $max_count - $min_count;
            if ($diff > 0) {
                $step_size = (self::CLOUD_MAX_SIZE - self::CLOUD_MIN_SIZE) / $diff;
                $step_opacity = (self::CLOUD_MAX_OPACITY - self::CLOUD_MIN_OPACITY) / $diff;
            }
            foreach ($tags as &$tag) {
                if ($diff > 0) {
                    $tag['size'] = ceil(self::CLOUD_MIN_SIZE + ($tag['count'] - $min_count) * $step_size);
                    $tag['opacity'] = number_format((self::CLOUD_MIN_OPACITY + ($tag['count'] - $min_count) * $step_opacity) / 100, 2, '.', '');
                } else {
                    $tag['size'] = ceil((self::CLOUD_MAX_SIZE + self::CLOUD_MIN_SIZE) / 2);
                    $tag['opacity'] = number_format(self::CLOUD_MAX_OPACITY, 2, '.', '');
                }

                $tag['bg_color'] = $this->getBgTagColor($tag['color']);
                /* Кажется это нигде не используется
                if (strpos($tag['name'], '/') !== false) {
                    $tag['uri_name'] = explode('/', $tag['name']);
                    $tag['uri_name'] = array_map('urlencode', $tag['uri_name']);
                    $tag['uri_name'] = implode('/', $tag['uri_name']);
                } else {
                    $tag['uri_name'] = urlencode($tag['name']);
                }
                */
            }
            unset($tag);
        }
        return $tags;
    }

    public function getCloudFast($key = null)
    {
        $query = $this->where('count > 0');
        $query->order('name');
        $tags = $query->fetchAll($key);
        return $tags;
    }

    /**
     * @param int $limit
     * @return array
     */
    public function getPopularTags($limit = 10)
    {
        return $this->getAllOrderedAndLimited('count DESC', $limit, 'id');
    }

    /**
     * @param int $limit
     * @return array
     */
    public function getPopularTagsSort($limit = 10)
    {
        $tags = $this->getAllOrderedAndLimited('count DESC', $limit, 'id');
        uasort($tags, function ($a, $b) {
            return (mb_strtolower($a['name']) > mb_strtolower($b['name']) ? 1 : -1);
        });

        return $tags;
    }

    /**
     * @param $term
     * @param int $limit
     * @return array
     */
    public function getByTerm($term, $limit = 10)
    {
        return $this->select('*')
            ->where("name LIKE ?", array("{$term}%"))
            ->limit($limit)
            ->fetchAll('id');
    }

    public function getByContact($contact_id, $drop_negative = true)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        if ($drop_negative) {
            $contact_ids = crmHelper::dropNotPositive($contact_ids);
        }
        if (!$contact_ids) {
            return array();
        }
        $contact_tags = array_fill_keys($contact_ids, array());
        $map = $this->getContactTagsModel()->getByField(array('contact_id' => $contact_ids), true);
        $tag_ids = waUtils::getFieldValues($map, 'tag_id');
        $tags = $this->getById($tag_ids);
        $tags = array_map(function ($tag) {
            $tag['bg_color'] = $this->getBgTagColor($tag['color']);
            return $tag;
        }, $tags);
        foreach ($map as $item) {
            $tag = ifset($tags[$item['tag_id']]);
            if ($tag) {
                $contact_tags[$item['contact_id']][$item['tag_id']] = $tag;
            }
        }
        return is_array($contact_id) ? $contact_tags : ifset($contact_tags[(int) $contact_id], array());
    }

    public function recount($tag_id = null)
    {
        $cond = "
            GROUP BY t.id
            HAVING t.count != cnt
        ";
        if ($tag_id !== null) {
            $tag_ids = array();
            foreach ((array)$tag_id as $id) {
                $tag_ids[] = $id;
            }
            if (!$tag_ids) {
                return;
            }
            $cond = "
                WHERE t.id IN ('".implode("','", $this->escape($tag_ids))."')
                GROUP BY t.id
            ";
        }
        $sql = "
            UPDATE `{$this->table}` t JOIN (
                SELECT t.id, t.count, count(ct.contact_id) cnt
                FROM `{$this->table}` t
                LEFT JOIN `:table` ct ON ct.tag_id = t.id
                $cond
            ) r ON t.id = r.id
            SET t.count = r.cnt";

        $sql = str_replace(':table', $this->getContactTagsModel()->getTableName(), $sql);

        $this->exec($sql);

        $this->deleteByField(array(
            'count' => 0
        ));
    }

    /**
     *
     * @param int|array[]int $contact_id
     * @param array $tags array of strings
     * @return bool
     */
    public function assign($contact_id, $tags, $drop_negative = true)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        if ($drop_negative) {
            $contact_ids = crmHelper::dropNotPositive($contact_ids);
        }
        if (empty($contact_ids)) {
            return false;
        }

        $tag_ids = $this->getIds($tags);

        // simple kind of realization: delete all, than assign all
        $items = $this->getContactTagsModel()->getByContact($contact_ids, $drop_negative);
        $tag_ids_for_recount = waUtils::getFieldValues($items, 'tag_id');
        $this->getContactTagsModel()->deleteByContact($contact_ids, $drop_negative);

        $data = array();
        foreach ($contact_ids as $contact_id) {
            foreach ($tag_ids as $tag_id) {
                $data[] = array(
                    'contact_id' => $contact_id,
                    'tag_id' => $tag_id
                );
                $tag_ids_for_recount[] = $tag_id;
            }
        }

        $tag_ids_for_recount = array_unique($tag_ids_for_recount);

        $this->getContactTagsModel()->multipleInsert($data);

        $this->recount($tag_ids_for_recount);

        return true;
    }

    public function add($contact_id, $tags, $drop_negative = true)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        if ($drop_negative) {
            $contact_ids = crmHelper::dropNotPositive($contact_ids);
        }
        if (empty($contact_ids)) {
            return false;
        }

        $tag_ids = $this->getIds($tags);

        if (!$contact_ids || !$tag_ids) {
            return false;
        }

        $sql = "INSERT IGNORE INTO `:table` (contact_id, tag_id)";

        $values = array();
        foreach ($contact_ids as $fid) {
            foreach ($tag_ids as $tid) {
                $values[] = "({$fid}, {$tid})";
            }
        }
        $values = join(',', $values);
        $sql .= " VALUES {$values}";

        $sql = str_replace(':table', $this->getContactTagsModel()->getTableName(), $sql);

        $this->exec($sql);

        $this->recount($tag_ids);

        return true;

    }

    public function getIds($tags)
    {
        $result = array();
        foreach ($tags as $t) {
            $t = trim($t);
            if ($id = $this->getByName($t, true)) {
                $result[] = $id;
            } else {
                $result[] = $this->insert(array('name' => $t));
            }
        }
        return $result;
    }

    public function getByName($name, $return_id = false)
    {
        $sql = "SELECT * FROM ".$this->table." WHERE `name` LIKE '".$this->escape($name, 'like')."'";
        $row = $this->query($sql)->fetch();
        return $return_id ? (isset($row['id']) ? $row['id'] : null) : $row;
    }

    public function getContactLinksCount($contact_id)
    {
        return 0;
    }

    public function deleteUnattachedTags()
    {
        $sql = 'SELECT ct.id FROM crm_tag AS ct
                    LEFT JOIN crm_contact_tags AS cct on ct.id = cct.tag_id
                WHERE cct.tag_id IS NULL';
        $unattached_tag_ids = $this->query($sql)->fetchAll('id');
        if ($unattached_tag_ids) {
            $this->deleteById(array_keys($unattached_tag_ids));
        }
    }

    protected function getBgTagColor($color)
    {
        if (empty($color)) {
            return null;
        }
        
        $color = is_scalar($color) ? trim(strval($color)) : '';
        if (strlen($color) <= 0 || $color[0] != '#' || strlen($color) != 7) {
            return null;
        }

        $c = substr($color, 1);
        list($r, $g, $b) = array(hexdec($c[0].$c[1]), hexdec($c[2].$c[3]), hexdec($c[4].$c[5]));
        return 'rgba(' . $r . ',' . $g .  ',' . $b . ', 0.3)';
    }
}
