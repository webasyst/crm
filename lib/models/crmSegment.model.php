<?php

class crmSegmentModel extends crmModel
{
    const TYPE_CATEGORY = 'category';
    const TYPE_SEARCH = 'search';
    const ICON_TYPE_CATEGORY = 'contact';
    const ICON_TYPE_SEARCH = 'search';
    const ICON_DEFAULT = 'folder';

    protected $table = 'crm_segment';

    /**
     * @var waContactCategoryModel
     */
    protected $ccm;

    /**
     * @var waContactCategoriesModel
     */
    protected $ccsm;

    /**
     * @var crmRights
     */
    protected $crm_rights;

    public function getSegment($id)
    {
        $id = (int) $id;
        $segments = $this->getSegments($id);
        return ifset($segments[$id]);
    }

    public function getSegments($ids)
    {
        $ids = $this->dropNotPositiveInts($this->toIntArray($ids));
        if (empty($ids)) {
            return array();
        }
        return $this->findSegments(array(
            'where' => '`cs`.`id` IN(:ids)',
            'bind_params' => array(
                'ids' => $ids
            ),
            'has_category_type' => true,
            'order_by' => "`cs`.`shared` DESC, sort"
        ));
    }

    /**
     * Gets all common segments and merges them with the model settings
     * @param $model
     * @return array
     */
    public function getMergedSegments($model)
    {
        $segments = $this->getAllSegments(array(
            'type' => 'category',
            'shared' => [0, 1],
            'archived' => 0
        ));

        $model_segments = $model->getParam('segments');

        if (!$model_segments) {
            return $segments;
        }

        foreach ($segments as &$segment) {
            if (in_array($segment['category_id'], $model_segments)) {
                $segment['checked'] = true;
            }
        }

        unset($segment);

        return $segments;
    }

    public function getAllSegments($filter = array())
    {
        $options = array(
            'has_category_type' => true,
            'order_by' => "`cs`.`shared` DESC, `cs`.`sort`"
        );

        $where = array();
        $bind_params = array();
        foreach ($filter as $field_id => $filter_value) {
            if ($this->fieldExists($field_id)) {

                $filter_value = (array) $filter_value;
                if ($field_id === 'type' && !in_array('category', $filter_value)) {
                    $options['has_category_type'] = false;
                }

                $where[] = "`cs`.`{$field_id}` IN(:cs_{$field_id})";
                $bind_params["cs_{$field_id}"] = $filter_value;
            }
        }

        if ($where) {
            $options['where'] = join(" AND ", $where);
            $options['bind_params'] = $bind_params;
        }

        return $this->findSegments($options);
    }

    public function getByContact($contact_id)
    {
        $ccm = new waContactCategoriesModel();
        $contact_categories = $ccm->getContactCategories($contact_id);
        if (!$contact_categories) {
            return array();
        }

        $where = $this->getWhereByField(array(
            'category_id' => array_keys($contact_categories),
            'archived' => 0,
        ), 'cs');

        return $this->findSegments(array(
            'has_category_type' => true,
            'where' => $where,
        ));
    }

    /**
     * @param array $data
     * @return bool|int|resource
     */
    public function add($data)
    {
        $type = ifset($data['type']);
        if (!$type) {
            $type = self::TYPE_CATEGORY;
        }
        $data['type'] = $type;

        $data['contact_id'] = wa()->getUser()->getId();
        $data['create_datetime'] = date('Y-m-d H:i:s');
        $data['name'] = trim((string) ifset($data['name']));
        $data['hash'] = ifset($data['hash']);

        $sort = $this->select('MAX(sort)')->where('shared = 0')->fetchField() + 1;
        $data['sort'] = $sort;

        if ($type === self::TYPE_CATEGORY) {

            $contacts = crmHelper::toIntArray(ifset($data['contacts']));
            $contacts = crmHelper::dropNotPositive($contacts);

            $category_id = $this->getContactCategoryModel()->insert(array(
                'name' => $data['name'],
                'system_id' => null,
                'app_id' => null,
                'icon' => ifset($data['icon']),
                'cnt' => count($contacts)
            ));

            if ($contacts) {
                $this->getContactCategoriesModel()->add($contacts, $category_id);
            }

            unset($data['name'], $data['hash']);

            $data['category_id'] = $category_id;
        } else if ($type === self::TYPE_SEARCH && !empty($data['hash'])) {
            $collection = new crmContactsCollection($data['hash']);
            $data['count'] = $collection->count();
        }

        return $this->insert($data);
    }

    /**
     * @param int $id
     * @param array $data
     */
    public function update($id, $data)
    {
        $segment = $this->getSegment($id);
        if (!$segment) {
            return;
        }

        if (!$this->getCrmRights()->canEditSegment($segment)) {
            return;
        }

        if (isset($data['shared']) && $data['shared'] == '0') {
            $data['contact_id'] = wa()->getUser()->getId();
        }

        if ($segment['type'] === self::TYPE_CATEGORY) {
            $category_data = array();
            foreach (array('name' => 'name', 'icon' => 'icon', 'count' => 'cnt') as $f1 => $f2) {
                if (!empty($data[$f1])) {
                    $category_data[$f2] = $data[$f1];
                    unset($data[$f1]);
                }
            }
            if (!$category_data) {
                return;
            }
            $this->getContactCategoryModel()->updateById($segment['category_id'], $category_data);

            if (isset($data['hash'])) {
                unset($data['hash']);
            }
        }

        if (!$data) {
            return;
        }

        $this->updateById($id, $data);

        if (isset($data['shared']) && $segment['shared'] != $data['shared']) {
            $this->move($id);
        }
    }

    public function updateCount($id, $count)
    {
        $this->updateById($id, array('count' => $count));
    }

    public function delete($id)
    {
        $segment = $this->getSegment($id);
        if (!$segment) {
            return;
        }
        if (!$this->getCrmRights()->canEditSegment($segment)) {
            return;
        }
        $this->deleteSegments(array($segment));
    }

    protected function deleteSegments($segments)
    {
        if (!$segments) {
            return;
        }

        $segment_ids = array();
        $category_ids = array();

        foreach ($segments as $segment) {
            $segment_ids[] = (int) ifset($segment['id']);
            if ($segment['type'] === self::TYPE_CATEGORY) {
                $category_ids[] = (int) ifset($segment['category_id']);
            }
        }

        $category_ids = crmHelper::dropNotPositive($category_ids);
        if ($category_ids) {
            $this->getContactCategoryModel()->delete($category_ids);
        }

        $segment_ids = crmHelper::dropNotPositive($segment_ids);
        $this->deleteById($segment_ids);
    }

    public function move($id, $before_id = null)
    {
        $item = $this->getById($id);
        if (!$item) {
            return false;
        }
        if (!$before_id) {
            $sort = $this->select('MAX(sort)')->
                where('shared = :0',
                    array(
                        $item['shared']
                    )
                )->fetchField() + 1;
        } else {
            $before = $this->getById($before_id);
            if (!$before) {
                return false;
            }
            $sort = $before['sort'];
            if ($item['shared']) {
                if (!$this->exec(
                    "UPDATE `{$this->table}` SET sort = sort + 1 WHERE sort >= :0 AND shared = 1",
                    array(
                        $sort
                    )))
                {
                    return false;
                }
            } else {
                $contact_id = array(
                    wa()->getUser()->getId()
                );
                if (wa()->getUser()->isAdmin()) {
                    $contact_id[] = 0;
                }
                if (!$this->exec(
                    "UPDATE `{$this->table}` SET sort = sort + 1 WHERE sort >= :0 AND shared = 0 AND contact_id IN (:1)",
                    array(
                        $sort,
                        $contact_id
                    )))
                {
                    return false;
                }
            }
        }
        if (!$this->exec(
            "UPDATE `{$this->table}` SET sort = :0 WHERE id = :1",
            array(
                $sort,
                $item['id']
            )
        ))
        {
            return false;
        }

        return true;
    }

    /**
     * @param int|int[] $segment_id
     * @param int|int[] $contact_id
     * @return array
     */
    public function addTo($segment_id, $contact_id)
    {
        $segment_ids = crmHelper::toIntArray($segment_id);
        $segment_ids = crmHelper::dropNotPositive($segment_ids);
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$segment_ids || !$contact_ids) {
            return [];
        }

        $category_ids = $this->extractCategoryIdsForEdit($segment_ids);
        if (!$category_ids) {
            return [];
        }

        $this->getContactCategoriesModel()->add($contact_ids, $category_ids);
        $this->getContactCategoryModel()->recalcCounters($category_ids);
        $counters = $this->getContactCategoryModel()
                        ->select('id, cnt')
                        ->where('id IN(:ids)', array('ids' => $category_ids))
                        ->fetchAll();
        foreach ($counters as $counter) {
            $this->updateByField('category_id', $counter['id'], array('count' => $counter['cnt']));
        }

        return $counters;
    }

    /**
     * @param int|array[]int $segment_id
     * @param int|array[]int $contact_id
     */
    public function assignWith($segment_id, $contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$contact_ids) {
            return;
        }

        $category_ids = $this->getContactCategoriesModel()
            ->select('category_id')
            ->where('contact_id = :contact_id',
                array('contact_id' => $contact_ids))
            ->fetchAll(null, true);

        if ($category_ids) {

            // first, check rights
            $segments = $this->findSegments(array(
                'where' => '`cs`.`category_id` IN(:category_ids)',
                'bind_params' => array(
                    'category_ids' => $category_ids
                ),
                'has_category_type' => true
            ));
            $segments = $this->getCrmRights()->dropUnallowedToEditSegments($segments);
            $category_ids = waUtils::getFieldValues($segments, 'category_id');
            $category_ids = crmHelper::toIntArray($category_ids);
            $category_ids = crmHelper::dropNotPositive($category_ids);

            // now we can delete
            $this->deleteFromCategories($category_ids, $contact_ids);
        }

        $segment_ids = crmHelper::toIntArray($segment_id);
        $segment_ids = crmHelper::dropNotPositive($segment_ids);
        if (!$segment_ids) {
            return;
        }

        $this->addTo($segment_id, $contact_id);

    }

    /**
     * @param int|int[] $segment_id
     * @param int|int[] $contact_id
     * @return array
     */
    public function deleteFrom($segment_id, $contact_id)
    {
        $segment_ids = crmHelper::toIntArray($segment_id);
        $segment_ids = crmHelper::dropNotPositive($segment_ids);
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$segment_ids || !$contact_ids) {
            return [];
        }

        $category_ids = $this->extractCategoryIdsForEdit($segment_ids);

        return $this->deleteFromCategories($category_ids, $contact_ids);
    }

    /**
     * @param $category_ids
     * @param $contact_ids
     * @return array
     */
    protected function deleteFromCategories($category_ids, $contact_ids)
    {
        $category_ids = crmHelper::toIntArray($category_ids);
        $category_ids = crmHelper::dropNotPositive($category_ids);
        $contact_ids = crmHelper::toIntArray($contact_ids);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$category_ids || !$contact_ids) {
            return [];
        }

        $ccm = new waContactCategoriesModel();
        $ccm->remove($contact_ids, $category_ids);

        $this->getContactCategoryModel()->recalcCounters($category_ids);
        $counters = $this->getContactCategoryModel()
            ->select('id, cnt')
            ->where('id IN(:ids)', array('ids' => $category_ids))
            ->fetchAll();
        foreach ($counters as $counter) {
            $this->updateByField('category_id', $counter['id'], array('count' => $counter['cnt']));
        }

        return $counters;
    }

    /**
     * @param int $contact_id
     * @param array[]int $segment_ids
     * @return array
     */
    public function dropNotAssigned($contact_id, $segment_ids)
    {
        $contact_id = (int) $contact_id;
        $segment_ids = crmHelper::toIntArray($segment_ids);
        $segment_ids = crmHelper::dropNotPositive($segment_ids);
        if ($contact_id <= 0 || !$segment_ids) {
            return array();
        }

        $category_segment_map = $this
            ->select('id, category_id')
            ->where('id IN (:ids) AND type = :type',
                array('ids' => $segment_ids, 'type' => self::TYPE_CATEGORY))
            ->fetchAll('category_id', true);

        $category_ids = array_keys($category_segment_map);

        $category_ids = $this->getContactCategoriesModel()
            ->select('category_id')
            ->where('category_id IN (:ids) AND contact_id = :contact_id',
                array('ids' => $category_ids, 'contact_id' => $contact_id))
            ->fetchAll(null, true);

        $segment_ids = array();
        foreach ($category_ids as $category_id) {
            if (isset($category_segment_map[$category_id])) {
                $segment_ids[] = $category_segment_map[$category_id];
            }
        }

        return $segment_ids;
    }

    /**
     * @param array $segment_ids
     * @return array
     */
    protected function extractCategoryIdsForEdit($segment_ids)
    {
        $segment_ids = crmHelper::toIntArray($segment_ids);
        $segment_ids = crmHelper::dropNotPositive($segment_ids);
        if (!$segment_ids) {
            return array();
        }

        $segments = $this->findSegments(array(
            'where' => '`cs`.`id` IN(:ids) AND `cs`.`type` = :type',
            'bind_params' => array(
                'ids' => $segment_ids,
                'type' => self::TYPE_CATEGORY
            ),
            'has_category_type' => true
        ));
        if (!$segments) {
            return array();
        }

        $segments = $this->getCrmRights()->dropUnallowedToEditSegments($segments);
        if (!$segments) {
            return array();
        }

        $category_ids = waUtils::getFieldValues($segments, 'category_id');
        $category_ids = crmHelper::toIntArray($category_ids);
        $category_ids = crmHelper::dropNotPositive($category_ids);
        if (!$category_ids) {
            return array();
        }

        return $category_ids;
    }

    public static function getIcons($ui = '1.3')
    {
        $icons_1_3 = [
            'contact',
            'search',
            'user',
            'folder',
            'notebook',
            'lock',
            'lock-unlocked',
            'broom',
            'star',
            'livejournal',
            'lightning',
            'light-bulb',
            'pictures',
            'reports',
            'books',
            'marker',
            'lens',
            'alarm-clock',
            'animal-monkey',
            'anchor',
            'bean',
            'car',
            'disk',
            'cookie',
            'burn',
            'clapperboard',
            'bug',
            'clock',
            'cup',
            'home',
            'fruit',
            'luggage',
            'guitar',
            'smiley',
            'sport-soccer',
            'target',
            'medal',
            'phone',
            'store',
            'basket',
            'pencil',
            'lifebuoy',
            'screen'
        ];
        if ($ui === '1.3') {
            return $icons_1_3;
        }

        $icons_2_0 = [
            'user-friends',
            'search',
            'user',
            'folder',
            'file',
            'lock',
            'lock-open',
            'brush',
            'star',
            'pencil-alt',
            'bolt',
            'lightbulb',
            'images',
            'chart-bar',
            'book',
            'map-marker-alt',
            'camera',
            'hourglass-end',
            'cat',
            'anchor',
            'seeding',
            'car-alt',
            'save',
            'cookie',
            'radiation-alt',
            'film',
            'bug',
            'clock',
            'coffee',
            'home',
            'apple-alt',
            'briefcase',
            'guitar',
            'smile',
            'futbol',
            'bullseye',
            'award',
            'phone-alt',
            'store',
            'shopping-cart',
            'pen-alt',
            'life-ring',
            'columns',
            'seedling'
        ];
        if ($ui === '2.0') {
            return $icons_2_0;
        }

        if ($ui === '1.3,2.0' || $ui === '1.3, 2.0') {
            return array_keys(array_flip($icons_1_3) + array_flip($icons_2_0));
        }

        return $icons_1_3;
    }

    protected function findSegments($options)
    {
        $user = wa()->getUser();
        $contact_ids = array($user->getId());
        if ($user->isAdmin()) {
            $contact_ids[] = 0;
        }

        $al = '`cs`';
        $table = "`{$this->table}`";

        $has_category_type = (bool) ifset($options['has_category_type']);

        $join = '';
        $fields = '*';
        if ($has_category_type) {
            $fields = array();
            foreach (array_keys($this->getMetadata()) as $field_id) {
                if (in_array($field_id, array('name', 'icon'))) {
                    $field = "IF({$al}.type = :type, cc.`{$field_id}`, {$al}.`{$field_id}`) AS `{$field_id}`";
                    $fields[] = str_replace(":type", '"' . self::TYPE_CATEGORY . '"', $field);
                } else {
                    $fields[] = "{$al}.`{$field_id}`";
                }
            }
            $fields[] = '`cc`.`system_id`';
            $fields[] = '`cc`.`app_id`';
            $fields = implode(',', $fields);

            $join = "LEFT JOIN `wa_contact_category` `cc` ON {$al}.`category_id` = `cc`.`id`";
        }

        $select = "SELECT {$fields}";
        $from = "FROM {$table} {$al}";
        $where = trim((string) ifset($options['where']));
        $where = $where ? " AND {$where}" : '';
        $order_by = trim((string) ifset($options['order_by']));
        $order_by = $order_by ? "ORDER BY {$order_by}" : '';
        $limit = trim((string) ifset($options['limit']));
        $limit = $limit ? "LIMIT {$limit}" : '';

        $sql = join(' ', array(
            $select,
            $from,
            $join,
            "WHERE (contact_id IN (:contact_ids) OR shared > 0) {$where}",
            $order_by,
            $limit
        ));

        $bind_params = (array) ifset($options['bind_params']);
        $bind_params['contact_ids'] = $contact_ids;

        $segments = $this->query($sql, $bind_params)->fetchAll('id');
        $this->workup($segments);
        return $segments;
    }

    public function syncWithCategories()
    {
        $al = '`cs`';
        $table = "`{$this->table}`";

        $sql = "DELETE {$al}
                FROM {$table} {$al}
                  LEFT JOIN `wa_contact_category` `cc`
                    ON {$al}.`category_id` = `cc`.`id`
                WHERE {$al}.`category_id` IS NOT NULL
                  AND `cc`.`id` IS NULL";
        $this->query($sql);

        $sql = "SELECT `cc`.`id`
                FROM `wa_contact_category` `cc`
                  LEFT JOIN {$table} {$al}
                    ON {$al}.`category_id` = `cc`.`id`
                WHERE {$al}.`category_id` IS NULL
                  AND `cc`.`system_id` IS NULL";

        $category_ids = $this->query($sql)->fetchAll(null, true);
        if (!$category_ids) {
            return;
        }

        $sort = $this->select('MAX(sort)')->where('shared = 1')->fetchField();
        $sort = $sort ? 0 : $sort + 1;

        $views = array();
        if ($this->isContactsProInstalled() && $this->checkModel('contactsViewModel')) {
            $model = new contactsViewModel();
            $views = $model->select('`category_id`, `create_datetime`, `contact_id`, `shared`, `count`, `icon`')
                        ->where('category_id IN (:ids)', array(
                            'ids' => $category_ids
                        ))->fetchAll('category_id', true);
        }

        foreach ($category_ids as $category_id) {
            $insert_item = array(
                'type' => self::TYPE_CATEGORY,
                'sort' => $sort++,
                'create_datetime' => date('Y-m-d H:i:s'),
                'shared' => 1,
                'category_id' => $category_id
            );
            $insert_item = array_merge($insert_item, (array)ifset($views[$category_id]));

            // CAUSE MULTIPLE INSERT HAS OWN "REQUIREMENTS" TO ARRAY KEYS FOR WORK, DO SINGLE INSERT
            // DON'T TOUCH IT!
            $this->insert($insert_item);
        }
    }

    public function getEmptyRowOfType($type = null)
    {
        $segment = $this->getEmptyRow();
        if ($type === self::TYPE_CATEGORY) {
            $segment['icon'] = self::ICON_TYPE_CATEGORY;
        } elseif ($type === self::TYPE_SEARCH) {
            $segment['icon'] = self::ICON_TYPE_SEARCH;
        } else {
            $segment['icon'] = self::ICON_DEFAULT;
        }
        $segment['type'] = $type;
        return $segment;
    }

    public function getContactLinksCount($contact_id)
    {
        return 0;
    }

    public function unsetContactLinks($contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$contact_ids) {
            return;
        }

        // DELETE ONLY PERSONAL SEGMENTS
        $segments = $this->getByField(array(
            'contact_id' => $contact_ids,
            'shared' => 0
        ), true);
        $this->deleteSegments($segments);

        // DELETE FROM CATEGORIES
        $where = $this->getWhereByField('contact_id', $contact_ids);
        $category_ids = $this->getContactCategoriesModel()->select('category_id')->where($where)->fetchAll(null, true);
        $this->getContactCategoriesModel()->deleteByField('contact_id', $contact_ids);
        $this->getContactCategoryModel()->recalcCounters($category_ids);

    }

    /**
     * @return waContactCategoryModel
     */
    protected function getContactCategoryModel()
    {
        return $this->ccm != null ? $this->ccm : ($this->ccm = new waContactCategoryModel());
    }

    /**
     * @return waContactCategoriesModel
     */
    protected function getContactCategoriesModel()
    {
        return $this->ccsm !== null ? $this->ccsm : ($this->ccsm = new waContactCategoriesModel());
    }

    protected function isContactsProInstalled()
    {
        static $is_pro_installed;
        if ($is_pro_installed !== null) {
            return $is_pro_installed;
        }
        $is_pro_installed = false;
        if (wa()->appExists('contacts')) {
            $plugins = wa('contacts')->getConfig()->getPlugins();
            $is_pro_installed = !empty($plugins['pro']);
        }
        return $is_pro_installed;
    }

    protected function checkModel($class_name)
    {
        if (!class_exists($class_name)) {
            return false;
        }
        try {
            new $class_name();
        } catch (waDbException $e) {
            return false;
        }
        return true;
    }

    private function workup(&$segments)
    {
        foreach ($segments as &$segment) {
            if ($segment['type'] === self::TYPE_CATEGORY) {
                if (!empty($segment['system_id']) && wa()->appExists($segment['system_id'])) {
                    $app = wa()->getAppInfo($segment['system_id']);
                    $segment['name'] = $app['name'];
                    $segment['icon'] = wa()->getRootUrl(true).$app['icon'][16];
                }
            }

            if ($segment['icon']) {
                $icon = $segment['icon'];
            } else if ($segment['type'] === self::TYPE_SEARCH) {
                $icon = self::ICON_TYPE_SEARCH;
            } else if ($segment['type'] === self::TYPE_CATEGORY) {
                $icon = self::ICON_TYPE_CATEGORY;
            } else {
                $icon = self::ICON_DEFAULT;
            }
            $segment['icon'] = $icon;
            
            $segment['icon_path'] = null;
            if ($icon) {
                $icon = trim($icon);
                if (strpos($icon, 'http://') === 0 || strpos($icon, 'https://') === 0) {
                    $segment['icon_path'] = $icon;
                }
            }

            // bind personal counters counters
            $user = wa()->getUser();
            $is_admin = $user->isAdmin();
            $is_shared = $segment['shared'] ? true : false;
            if (!$is_admin && $is_shared) {
                $counters = $this->getSegmentCountModel()->getCounters(array_keys($segments), $user->getId());
                foreach ($counters as $segment_id => $counter) {
                    if ($counter !== null) {
                        $segments[$segment_id]['count'] = $counter;
                    }
                }
            }
        }
    }

    private function toIntArray($items)
    {
        return array_map('intval', (array) $items);
    }

    private function dropNotPositiveInts($items)
    {
        $res = array();
        foreach ($items as $item) {
            if ($item > 0) {
                $res[] = $item;
            }
        }
        return $res;
    }
}
