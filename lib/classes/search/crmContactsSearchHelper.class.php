<?php

class crmContactsSearchHelper
{
    protected static $config;
    private static $fetches_cache = array();

    /**
     *
     * @var waModel
     */
    protected static $model;

    public static function getConfig($options = false)
    {
        if (!self::$config) {

            $event_result = wa('crm')->event('search.config');
            foreach ($event_result as $app_id => $res) {
                if (empty($res)) {
                    unset($event_result[$app_id]);
                }
            }
            $data = array_merge(self::getRawConfig(), $event_result);
            if ($data) {
                $items = [];
                $enabled = [
                    'contact_info',
                    'activity'
                ];
                $user = wa()->getUser();
                $app_ids = array_keys(wa()->getApps());
                $crm_plugin_ids = array_keys(wa('crm')->getConfig()->getPlugins());
                foreach ($data as $app_name => $_item) {
                    if (preg_match('/-plugin$/', $app_name)) {
                        $app_id = preg_replace('/-plugin$/', '', $app_name);
                        if (in_array($app_id, $crm_plugin_ids)) {
                            // crm plugin case
                            $items[$app_name] = $_item;
                            continue;
                        }
                        
                        $parts = explode('_', $app_id);
                        if (count($parts) == 1) {
                            // invalid case - non existed plugin
                            continue;
                        }
                        array_pop($parts);
                        $potential_app_ids = array_reduce($parts, function($res, $p) {
                            $res[] = empty($res) ? $p : end($res) . '_' . $p;
                            return $res;
                        }, []);
                        $found_app_ids = array_filter($potential_app_ids, function($p) use ($app_ids) {
                            return in_array($p, $app_ids);
                        });
                        if (empty($found_app_ids)) {
                            // invalid case - non existed app
                            continue;
                        }

                        $app_id = $found_app_ids[0];
                        if ($user->getRights($app_id)) {
                            $items[$app_name] = $_item;
                        }
                    } elseif (in_array($app_name, $enabled) || $user->getRights($app_name)) {
                        $items[$app_name] = $_item;
                    }
                }
                self::$config = $items;
            }
        }

        $data = self::$config;

        if (!$options) {
            $options = array(
                'unwrap_contact_field' => true,
                'unwrap_values_class_instance' => true
            );
        }

        foreach ($data as $section_id => &$section) {
            if (!empty($section['items'])) {

                foreach ($section['items'] as $item_id => &$item) {
                    if (!empty($item['items'])) {

                        foreach ($item['items'] as $itm_id => &$itm) {
                            if (!empty($itm[':values']) && !empty($options['unwrap_values_class_instance'])) {
                                self::includeValuesClassInstance($itm);
                            }
                            if (!empty($itm['field_id']) && !empty($options['unwrap_contact_field'])) {

                                // throw away not existed field
                                $exists = self::includeField($itm);
                                if (!$exists) {
                                    unset($item['items'][$itm_id]);
                                }

                                if (isset($itm['items']) && !is_array($itm['items'])) {
                                    $itm['items'] = array();
                                }
                            }
                        }
                        unset($itm);

                        if (!empty($item[':values']) && !empty($options['unwrap_values_class_instance'])) {
                            self::includeValuesClassInstance($item);
                        }
                        if (!empty($item['field_id']) && !empty($options['unwrap_contact_field'])) {

                            // throw away not existed field
                            $exists = self::includeField($item);
                            if (!$exists) {
                                unset($section['items'][$item_id]);
                            }

                            if (!empty($item['field']) && $item['field'] instanceof waContactCompositeField) {
                                foreach ($item['items'] as $itm_id => &$itm) {
                                    if (!empty($itm['items'][':values']) && !empty($options['unwrap_values_class_instance'])) {
                                        self::includeValuesClassInstance($itm);
                                    }
                                }
                            }
                        }
                    }
                    unset($item);
                }
            }
            unset($section);
        }
        return $data;
    }

    /**
     * Include field information into item
     * @param array &$item
     * @return bool - If field not exist return FALSE
     * @throws waException
     */
    private static function includeField(&$item)
    {
        $f = waContactFields::get($item['field_id'], 'all');
        if (!$f) {
            // fields does not exist
            return false;
        }

        $item['name'] = $f->getName();
        if (empty($item['type'])) {
            if ($f instanceof waContactSelectField || $f instanceof waContactRegionField || $f instanceof waContactCountryField) {
                $item['type'] = 'Select';
                $item['options'] = $f->getOptions();
            } else if ($f instanceof waContactCompositeField) {
                $item['type'] = 'Composite';
                $item['field'] = $f;
            } else {
                $item['type'] = 'Input';
            }
        }
        $item['field'] = $f;

        return true;
    }

    private static function includeValuesClassInstance(&$item, $options = array())
    {
        if (!empty($item['items'][':values']['class']) && class_exists($item['items'][':values']['class']) && empty($item['items'][':values']['instance'])) {
            $item['items'][':values']['options'] = ifset($item['items'][':values']['options'], array());
            $item['items'][':values']['options'] = array_merge($options, $item['items'][':values']['options']);
            $item['items'][':values']['instance'] = new $item['items'][':values']['class']($item['items'][':values']['options']);
        }
    }

    private static function includeItemClassInstance(&$item, $options = array())
    {
        if (!empty($item[':class']) && class_exists($item[':class']) && empty($item['instance'])) {
            $item['options'] = ifset($item['options'], array());
            $item['options'] = array_merge($options, $item['options']);
            $item['instance'] = new $item[':class']($item['options']);
        }
    }

    private static function getRawConfig($with_apps = true)
    {
        $config = wa('crm')->getConfig();

        $data = array();
        $files = array(
            $config->getConfigPath('search/search.php'),
            $config->getAppConfigPath('search/search')
        );

        foreach ($files as $file) {
            if (file_exists($file)) {
                $data = include($file);
                break;
            }
        }

        if (!$with_apps) {
            return $data;
        }

        $user = wa()->getUser();
        $apps = $user->getApps();
        foreach ($apps as $app_id => $app) {
            if (wa()->appExists($app_id)) {
                $files = array(
                    $config->getConfigPath("search/search_{$app_id}.php"),
                    $config->getAppConfigPath("search/search_{$app_id}")
                );
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        $app_data = include($file);
                        $data = array_merge($data, array($app_id => $app_data));
                    }
                }
            }
        }
        return $data;
    }


    protected static function updateItem($id, $item_config)
    {
        $config = self::getRawConfig(false);
        $keys = array();
        foreach (explode('.', $id) as $k) {
            $keys[] = $k;
            $keys[] = 'items';
        }
        array_pop($keys);

        $p = &$config;
        foreach ($keys as $key) {
            if (!isset($p[$key])) {
                $p[$key] = array();
            }
            $p = &$p[$key];
        }

        $p = array_merge($p, $item_config);

        unset($p);

        waUtils::varExportToFile($config, wa('crm')->getConfig()->getConfigPath('search/search.php'), true);

    }

    protected static function removeItem($id)
    {
        $config = self::getRawConfig(false);
        $keys = array();
        foreach (explode('.', $id) as $k) {
            $keys[] = $k;
            $keys[] = 'items';
        }
        array_pop($keys);
        array_pop($keys);

        $p = &$config;
        foreach ($keys as $key) {
            if (!isset($p[$key])) {
                return;
            }
            $p = &$p[$key];
        }

        if (isset($p[$k])) {
            unset($p[$k]);
        }

        unset($p);

        self::recursiveClean($config);

        waUtils::varExportToFile($config, wa('crm')->getConfig()->getConfigPath('search/search.php'), true);

    }

    public static function sortItems($item_ids = array(), $parent_id = null)
    {
        if (waConfig::get('is_template')) {
            return;
        }
        $config = self::getRawConfig();

        $keys = array();
        if ($parent_id) {
            foreach (explode('.', $parent_id) as $k) {
                $keys[] = $k;
                $keys[] = 'items';
            }
        }

        $p = &$config;
        foreach ($keys as $key) {
            if (!isset($p[$key])) {
                $p[$key] = array();
            }
            $p = &$p[$key];
        }

        $items = array();
        foreach ($item_ids as $item_id) {
            if (isset($p[$item_id])) {
                $items[$item_id] = $p[$item_id];
                unset($p[$item_id]);
            }
        }
        foreach ($p as $item_id => $item) {
            $items[$item_id] = $item;
        }
        $p = $items;

        unset($p);

        waUtils::varExportToFile($config, wa('crm')->getConfig()->getConfigPath('search/search.php'), true);
    }

    public static function getItem($id, $hash = null, $options = array())
    {
        $config = crmContactsSearchHelper::getConfig();

        $options = array_merge(array(
            'unwrap_values' => true,
            'unwrap_class' => true,
            'count' => true
        ), $options);

        $keys = array();
        foreach (explode('.', $id) as $k) {
            $keys[] = $k;
            $keys[] = 'items';
        }
        array_pop($keys);

        $p = &$config;
        foreach ($keys as $key) {
            if (!isset($p[$key])) {
                return null;
            }
            $p = &$p[$key];
        }

        $hash_ar = crmContactsSearchHelper::parseHash($hash);
        $conds = $hash_ar['conds'];
        $c = &$conds;
        foreach (explode('.', $id) as $k) {
            if (isset($c[$k])) {
                $c = &$c[$k];
            }
        }
        $conds = $c;

        if (!empty($p['field_id']) && $p['type'] === 'Composite') {
            if (isset($p['field']) && $p['field'] instanceof waContactField) {
                $p['info'] = $p['field']->getInfo();
                if ($p['field'] instanceof waContactCompositeField) {
                    $fields = $p['field']->getFields();
                    foreach ($fields as $f_id => $f) {
                        if ($f instanceof waContactHiddenField) {
                            continue;
                        }
                        $fld = ifset($p['info']['fields'][$f_id], array());
                        if (isset($p['items'][$f_id])) {
                            $p['items'][$f_id] = self::getItem("{$id}.{$f_id}", $hash, $options);
                            $fld = array_merge($fld, $p['items'][$f_id]);
                        }
                        if ($f instanceof waContactSelectField || $f instanceof waContactCountryField) {
                            $fld['type'] = 'Select';
                            $fld['options'] = $f->getOptions();
                        } else if ($f instanceof waContactCompositeField) {
                            $fld['type'] = 'Composite';
                            $fld['field'] = $f;
                        } else {
                            $fld['type'] = 'Input';
                        }
                        $p['info']['fields'][$f_id] = $fld;
                    }
                }
            }
        }

        if (isset($p['children'])) {
            foreach ($p['items'] as $item_id => $item) {
                $p['items'][$item_id] = self::getItem("{$id}.{$item_id}", $hash, $options);
            }
        } else if (isset($p['items'])) {

            $values = null;
            foreach ($p['items'] as $item_id => &$item) {
                if (strpos($item_id, ':') === 0) {
                    if ($item_id === ':values') {
                        $when_readonly = null;
                        if (is_array($options['unwrap_values']) && isset($options['unwrap_values']['when_readonly'])) {
                            $when_readonly = !!$options['unwrap_values']['when_readonly'];
                        }
                        $unwrap_values = $options['unwrap_values'] ? true : false;
                        if ($unwrap_values &&
                            ($when_readonly === null || ($when_readonly === true && !empty($p['readonly'])))
                        )
                        {
                            $values = self::unwrapValues(
                                $p,
                                is_array($options['unwrap_values']) ? $options['unwrap_values'] : array()
                            );
                        }
                        if (isset($item['autocomplete'])) {
                            $p['autocomplete'] = true;
                        }
                        if ($options['count']) {
                            $count = self::countOfValues(
                                $p,
                                is_array($options['unwrap_values']) ? $options['unwrap_values'] : array()
                            );
                            if ($count !== null) {
                                $p['count'] = $count;
                            }
                        }
                        if (isset($conds['val']) && isset($item['instance'])) {
                            if (method_exists($item['instance'], 'extra')) {
                                $p['extra'] = $item['instance']->extra($conds['val']);
                            }
                        }
                        if (isset($item['limit'])) {
                            $p['limit'] = $item['limit'];
                        }

                        if (isset($conds['val'])) {
                            $p['extra']['name'] = self::getNameOfValue($p, $conds['val']);
                        }
                        //unset($p['items'][$item_id]);
                    }
                    continue;
                }

                if (isset($item['items'])) {
                    if (is_array($item['items'])) {
                        $item = self::getItem("{$id}.{$item_id}", $hash, $options);
                    } else {
                        //$item['items'] = self::makeItemsListFromQuery($item['items']);
                    }
                }
//                if (isset($item['field']) && $item['field'] instanceof waContactField) {
//                    $item['id'] = $id . '.' . $item_id;
//                }

                if (isset($item[':class']) && !empty($options['unwrap_class'])) {
                    self::includeItemClassInstance($item, array(
                        'conds' => $conds
                    ));
                }

                if (isset($conds[$item_id]['val'])) {
                    $item['extra'] = $conds[$item_id];
                    $item['extra']['name'] = self::getNameOfValue($item, $conds[$item_id]['val']);
                }

                if (isset($item['instance']) && method_exists($item['instance'], 'getHtml')) {
                    $item['html'] = $item['instance']->getHtml();
                }


                $item['id'] = $id . '.' . $item_id;
            }
            unset($item);


            if($values !== null) {
                $p['items']['values'] = $values;
            }

        }

        $p['id'] = $id;

        return $p;

    }

    public static function addJoin(&$item, waContactsCollection $collection, $replace = array(), $join_type = 'join')
    {
        if (($join_type === 'join' || $join_type === 'left_join') && isset($item[$join_type])) {

            $add_join_already = ifset($item['add_join_already'], false);
            $add_left_join_already = ifset($item['add_left_join_already'], false);

            if (($join_type === 'join' && !$add_join_already) || ($join_type === 'left_join' && !$add_left_join_already)) {
                $join = $item[$join_type];
                $table = ifset($join['table']);
                $where = ifset($join['where']);
                $on = ifset($join['on']);
                if ($where || $on) {
                    foreach ($replace as $k => $v) {
                        if ($v !== null) {
                            if ($where) {
                                $where = str_replace($k, $v, $where);
                            }
                            if ($on) {
                                $on = str_replace($k, $v, $on);
                            }
                        }
                    }
                }
                $options = ifset($join['options'], array());
            }
            if ($join_type === 'join') {
                $item['add_join_already'] = true;
                $al = $collection->addJoin($table, $on, $where, $options);
                return $al;
            } else {
                $item['add_left_join_already'] = true;
                $al = $collection->addLeftJoin($table, $on, $where, $options);
                return $al;
            }
        }
        return null;
    }

    public static function addLeftJoin(&$item, waContactsCollection $collection, $replace = array())
    {
        return self::addJoin($item, $collection, $replace, 'left_join');
    }

    public static function addWhere(&$item, waContactsCollection $collection, $replace = array())
    {
        $where = ifset($item['where']);
        $add_where_already = ifset($item['add_where_already'], false);
        if (!$add_where_already && $where && is_string($where)) {
            foreach ($replace as $k => $v) {
                if ($v !== null) {
                    $where = str_replace($k, $v, $where);
                } else if (strstr($where, $k) !== false) {
                    return false;       // bad where (with placeholders)
                }
            }
            $collection->addWhere($where);
            $item['add_where_already'] = true;
        }
        return true;
    }

    public static function addHaving(&$item, waContactsCollection $collection, $replace = array())
    {
        $having = ifset($item['having']);
        if ($having && is_string($having)) {
            foreach ($replace as $k => $v) {
                if ($v !== null) {
                    $having = str_replace($k, $v, $having);
                } else if (strstr($having, $k) !== false) {
                    return false;       // bad having (with placeholders)
                }
            }
            $collection->addHaving($having);
            $item['add_having_already'] = true;
        }
        return true;
    }

    public static function addExtWhere($item, waContactsCollection $collection, $options = array())
    {
        $replace = ifset($options['replace'], array());
        $val_item = ifset($options['val_item'], array('op' => '=', 'val' => ''));
        $where = ifset($item['where']);
        if (is_array($where)) {
            if (isset($where[$val_item['op']])) {
                $where = $where[$val_item['op']];
            } else {
                $where = '';
            }
        }
        if (is_array($where)) {
            if (isset($where[$val_item['val']])) {
                $where = $where[$val_item['val']];
            } else {
                $where = '';
            }
        }
        if ($where) {
            foreach ($replace as $k => $v) {
                if ($v !== null) {
                    $where = str_replace($k, $v, $where);
                }
            }
            $m = new waModel();
            if (preg_match("/\s+like\s+/i", $where)) {
                $val_item['val'] = $m->escape($val_item['val'], 'like');
            } else {
                $val_item['val'] = $m->escape($val_item['val']);
            }
            $where = str_replace(":value", $val_item['val'], $where);
            $collection->addWhere($where);
        }
        return true;
    }

    public static function addExtHaving($item, waContactsCollection $collection, $options = array())
    {
        $replace = ifset($options['replace'], array());
        $val_item = ifset($options['val_item'], array('op' => '=', 'val' => ''));
        $having = ifset($item['having']);
        if (is_array($having)) {
            if (isset($having[$val_item['op']])) {
                $having = $having[$val_item['op']];
            } else {
                $having = '';
            }
        }
        if (is_array($having)) {
            if (isset($having[$val_item['val']])) {
                $having = $having[$val_item['val']];
            } else {
                $having = '';
            }
        }
        if ($having) {
            foreach ($replace as $k => $v) {
                if ($v !== null) {
                    $having = str_replace($k, $v, $having);
                }
            }
            $m = new waModel();
            if (preg_match("/\s+like\s+/i", $having)) {
                $val_item['val'] = $m->escape($val_item['val'], 'like');
            } else {
                $val_item['val'] = $m->escape($val_item['val']);
            }
            $having = str_replace(":value", $val_item['val'], $having);
            $collection->addHaving($having);
        }
        return true;
    }

    public static function addClassWhere(&$item, waContactsCollection $collection, $val_item, $replace = array(), $options = array())
    {
        $where = '';
        $add_where_already = ifset($item['items'][':values']['add_where_already'], false);
        if (!$add_where_already) {
            self::includeValuesClassInstance($item);
            $instance = ifset($item['items'][':values']['instance']);
            if ($instance && method_exists($instance, 'where')) {
                $where = $instance->where($val_item);
            }
            if ($where) {
                $itm = array('where' => $where);
                self::addWhere($itm, $collection, $replace);
                $item['items'][':values']['add_where_already'] = ifset($itm['add_where_already'], false);
            }
        }

        $where = '';
        $add_where_already = ifset($item['add_where_already'], false);
        if (!$add_where_already) {
            self::includeItemClassInstance($item, $options);
            $instance = ifset($item['instance']);
            if ($instance && method_exists($instance, 'where')) {
                $where = $instance->where($val_item);
            }
            if ($where) {
                $itm = array('where' => $where);
                self::addWhere($itm, $collection, $replace);
                $item['add_where_already'] = ifset($itm['add_where_already'], false);
            }
        }
        return true;
    }

    public static function addClassHaving(&$item, waContactsCollection $collection, $val_item, $replace = array(), $options = array())
    {
        $having = '';
        $add_having_already = ifset($item['items'][':values']['add_having_already'], false);
        if (!$add_having_already) {
            self::includeValuesClassInstance($item);
            $instance = ifset($item['items'][':values']['instance']);
            if ($instance && method_exists($instance, 'having')) {
                $having = $instance->having($val_item);
            }
            if ($having) {
                $itm = array('having' => $having);
                self::addHaving($itm, $collection, $replace);
                $item['items'][':values']['add_having_already'] = ifset($itm['add_having_already'], false);
            }
        }

        $having = '';
        $add_having_already = ifset($item['add_having_already'], false);
        if (!$add_having_already) {
            self::includeItemClassInstance($item, $options);
            $instance = ifset($item['instance']);
            if ($instance && method_exists($instance, 'having')) {
                $having = $instance->having($val_item);
            }
            if ($having) {
                $itm = array('having' => $having);
                self::addHaving($itm, $collection, $replace);
                $item['add_having_already'] = ifset($itm['add_having_already'], false);
            }
        }
        return true;
    }

    public static function addClassJoin(&$item, waContactsCollection $collection, $replace = array(), $options = array())
    {
        $join = '';
        $add_join_already = ifset($item['items'][':values']['add_join_already'], false);
        $add_left_join_already = ifset($item['items'][':values']['add_left_join_already'], false);
        if (!$add_join_already || !$add_left_join_already) {
            self::includeValuesClassInstance($item, $options);
            $instance = ifset($item['items'][':values']['instance']);
            if ($instance && method_exists($instance, 'join')) {
                $join = $instance->join();
            }
            if ($join) {
                $join_type = ifset($join['type'], 'join');
                if (($join_type === 'join' && !$add_join_already) || ($join_type === 'left_join' && !$add_left_join_already)) {
                    $it = array($join_type => $join);
                    $res = self::addJoin($it, $collection, $replace, $join_type);
                    $item['items'][':values']['add_join_already'] = ifset($it['add_join_already'], false);
                    $item['items'][':values']['add_left_join_already'] = ifset($it['add_left_join_already'], false);
                    return $res;
                }
            }
        }

        $join = '';
        $add_join_already = ifset($item['add_join_already'], false);
        $add_left_join_already = ifset($item['add_left_join_already'], false);
        if (!$add_join_already || !$add_left_join_already) {
            self::includeItemClassInstance($item, $options);
            $instance = ifset($item['instance']);
            if ($instance && method_exists($instance, 'join')) {
                $join = $instance->join();
            }
            if ($join) {
                $join_type = ifset($join['type'], 'join');
                if (($join_type === 'join' && !$add_join_already) || ($join_type === 'left_join' && !$add_left_join_already)) {
                    $it = array($join_type => $join);
                    $res = self::addJoin($it, $collection, $replace, $join_type);
                    $item['add_join_already'] = ifset($it['add_join_already'], false);
                    $item['add_left_join_already'] = ifset($it['add_left_join_already'], false);
                    return $res;
                }
            }
        }
        return null;
    }

    public static function addWhereForPeriodItem($cond, $item, waContactsCollection $collection, $replace = array())
    {
        $where = '0';
        if ($cond['op'] === '=') {
            $period = explode('--', $cond['val']);
            if (!isset($period[1])) {
                $period[1] = date('Y-m-d');
            }
            $replace[':0'] = $period[0];
            $replace[':1'] = $period[1];
            if (isset($item['where'][':between'])) {
                $where = $item['where'][':between'];
            }
        } else {
            $replace[':?'] = $cond['val'];
            if ($cond['op'] === '<=' && isset($item['where'][':lt'])) {
                $where = $item['where'][':lt'];
            }
            if ($cond['op'] === '>=' && isset($item['where'][':gt'])) {
                $where = $item['where'][':gt'];
            }
        }
        $itm = array('where' => $where);
        return crmContactsSearchHelper::addWhere($itm, $collection, $replace);
    }

    private static function unwrapValues($item, $options = array(), $no_index = true)
    {
        $values = array();

        if (!empty($options) && !is_array($options)) {
            $options = array();
        }

        if (isset($item['items'][':values'])) {
            self::includeValuesClassInstance($item);
            $instance = ifset($item['items'][':values']['instance']);
            if ($instance && method_exists($instance, 'getValues')) {
                $values = $instance->getValues($options);
            } else {

                $offset = 0;
                if (isset($options['offset'])) {
                    $offset = (int) $options['offset'];
                }

                $limit = 10;
                if (isset($options['limit'])) {
                    $limit = (int) $options['limit'];
                } else if (isset($item['items'][':values']['limit'])) {
                    $limit = (int) $item['items'][':values']['limit'];
                }

                $sql = is_string($item['items'][':values']) ? $item['items'][':values'] : ifset($item['items'][':values']['sql']);
                $sql = str_replace(':limit', "{$offset}, {$limit}", $sql);

                if ($sql) {
                    if (!empty($options['autocomplete'])) {
                        if (isset($options['autocomplete']['term'])) {
                            $m = self::getModel();
                            $term = $m->escape($options['autocomplete']['term'], 'like');
                            $autocomplete = str_replace(
                                ":term",
                                $term,
                                ifset($item['items'][':values']['autocomplete'], "value LIKE ':term%'")
                            );
                            $sql = str_replace(":autocomplete", $autocomplete, $sql);
                        }
                    } else {
                        $sql = str_replace(":autocomplete", "", $sql);
                    }
                    $values = self::makeItemsListFromQuery($sql, $no_index);

                    $names = array();
                    if (!empty($item['items'][':values']['names'])) {
                        $names = $item['items'][':values']['names'];
                    } else if (isset($item['field']) && is_object($item['field']) && method_exists($item['field'], 'getOptions')) {
                        $names = $item['field']->getOptions();
                    }
                    if ($names) {
                        foreach ($values as &$v) {
                            if (isset($v['name']) && isset($names[$v['name']])) {
                                $v['name'] = $names[$v['name']];
                            }
                        }
                        unset($v);
                    }
                }
            }
        }
        return $values;
    }

    private static function countOfValues($item, $options = array())
    {
        $count = null;

        if (!empty($options) && !is_array($options)) {
            $options = array();
        }

        if (isset($item['items'][':values'])) {

            self::includeValuesClassInstance($item);
            $instance = ifset($item['items'][':values']['instance']);
            if ($instance && method_exists($instance, 'count')) {
                $count = $instance->count($options);
            } else {

                $sql = str_replace(':limit', "", ifset($item['items'][':values']['count'], ''));

                if ($sql) {
                    $m = self::getModel();
                    if (!empty($options['autocomplete']['term'])) {
                        $term = $m->escape($options['autocomplete']['term'], 'like');
                        $autocomplete = str_replace(
                            ":term",
                            $term,
                            ifset($item['items'][':values']['autocomplete'], "value LIKE ':term%'")
                        );
                        $sql = str_replace(":autocomplete", $autocomplete, $sql);
                    } else {
                        $sql = str_replace(":autocomplete", "", $sql);
                    }
                    $count = $m->query($sql)->fetchField();
                }
            }
        }

        return $count;

    }


    public static function addGroupBy($item, waContactsCollection $collection)
    {
        if (!empty($item['group_by'])) {
            $collection->setGroupBy($item['group_by'] === 1 ? 'c.id' : $item['group_by']);
            return true;
        }
    }

    public static function isNumericArray($ar)
    {
        return is_array($ar) && count(array_filter(array_keys($ar), "is_numeric")) === count($ar);
    }

    public static function countForItem($item, $hash = null)
    {
        if (is_string($item)) {
            $item = self::getItem($item);
        }

        $c = null;
        $hash_ar = crmContactsSearchHelper::parseHash($hash);

        $need_count = false;

        if (isset($item['field_id'])) {
            if ($hash_ar['query']) {
                $c = new contactsCollection("search/{$hash_ar['query']}");
                $need_count = true;
            }
        }
        if (!$c) {
            $c = new contactsCollection();
        }
        $conds = $hash_ar['conds'];

        $id = $item['id'];
        $p = &$conds;
        foreach (explode('.', $id) as $k) {
            if (isset($p[$k])) {
                $p = &$p[$k];
            }
        }
        $conds = $p;


        if (!self::isNumericArray($conds)) {
            $conds_ar = array($conds);
        } else {
            $conds_ar = $conds;
        }
        foreach ($conds_ar as $conds) {

            // contrapositive case
            if (!empty($conds['not']))
            {
                unset($conds['not']);
                $h = array();
                foreach ($conds as $k => $v) {
                    $h[] = "{$id}.{$k}={$v}";
                }
                if (empty($h)) {
                    $h[] = "{$id}=1";
                }
                $h = implode("&", $h);
                $search_collection = new self($h);
                $sub_sql = $search_collection->getSQL();
                $item['where'] = "c.id NOT IN (SELECT DISTINCT c.id {$sub_sql})";
                self::addWhere($item, $c);
                $need_count = true;
            }
            else  // positive case
            {

                if (self::addWhere($item, $c)) {
                    $need_count = true;
                }
                if (self::addGroupBy($item, $c)) {
                    $need_count = true;
                }

                $alias = self::addJoin($item, $c);
                if (!$alias) {
                    $alias = self::addLeftJoin($item, $c);
                }
                if ($alias) {
                    $need_count = true;
                }

                if (is_array($conds)) {
                    foreach ($conds as $key => $val) {
                        $it = ifset($item['items'][$key], array());
                        if (!$it) {
                            continue;
                        }
                        // parse contact info field item

                        if (!empty($item['field'])) {
                            $storage = $item['field']->getStorage();
                            if ($storage && $storage instanceof waContactStorage)
                            {
                                $table = $storage->getModel()->getTableName();
                                if (!empty($it['join']) && empty($it['join']['table'])) {
                                    $it['join']['table'] = $table;
                                }
                                if (!empty($it['left_join']) && empty($it['left_join']['table'])) {
                                    $it['join']['table'] = $table;
                                }
                                $al = self::addJoin($it, $c);
                                if (!$al) {
                                    $al = self::addLeftJoin($it, $c);
                                }
                                if ($al) {
                                    $need_count = true;
                                }

                                if (self::addWhere($it, $c)) {
                                    $need_count = true;
                                }
                            }
                        } else {
                            if (self::addWhere($it, $c, array(
                                ':table' => $alias,
                                ':items' => "'".implode("','", (array) $val)."'"
                            ))) {
                                $need_count = true;
                            }

                            if (self::addJoin($it, $c, array(
                                ':parent_table' => $alias,
                                ':items' => "'".implode("','", (array) $val)."'"
                            ))) {
                                $need_count = true;
                            }
                        }

                        if (isset($it['items'])) {
                            if (is_array($val)) {
                                foreach ($val as $k => $v) {
                                    $i = ifset($it['items'][$k]);
                                    if (!$i) {
                                        continue;
                                    }
                                    if (self::addWhere($i, $c)) {
                                        $need_count = true;
                                    }
                                }
                            } else {
                                $i = ifset($it['items'][$val]);
                                if ($i) {
                                    if (self::addWhere($i, $c)) {
                                        $need_count = true;
                                    }
                                }
                            }
                        }

                    }
                }
            }
        }

        if ($need_count) {
            return $c->count();
        } else {
            return '';
        }

    }

    private static function getModel()
    {
        if (!self::$model) {
            self::$model = new waModel();
        }
        return self::$model;
    }

    private static function fetchAll($sql)
    {
        if (!$sql) {
            return array();
        }
        $key = md5($sql);
        if (!isset(self::$fetches_cache[$key])) {
            $m = self::getModel();
            self::$fetches_cache[$key] = $m->query($sql)->fetchAll();
        }
        return self::$fetches_cache[$key];
    }

    public static function makeItemsListFromQuery($sql, $no_index = false)
    {
        $list = array();
        foreach (self::fetchAll($sql) as $item) {
            if (count($item) === 1) {
                $item = array_values($item);
                if (!$no_index) {
                    $list[$item[0]] = array(
                        'name' => $item[0]
                    );
                } else {
                    $list[] = array(
                        'name' => $item[0]
                    );
                }
            } else {
                $item_values = array_values($item);
                if (!isset($item['id'])) {
                    if (isset($item['value'])) {
                        $item['id'] = $item['value'];
                    } else {
                        $item['id'] = $item_values[0];;
                    }
                }
                if (!isset($item['name'])) {
                    $item['name'] = $item_values[1];
                }
                if (!$no_index) {
                    $list[$item['id']] = $item;
                } else {
                    $list[] = $item;
                }
            }
        }
        return $list;
    }

    public static function parseStr($str)
    {
        $ar = array();
        if (!$str) {
            return $ar;
        }

        $escapedBS = 'ESCAPED_BACKSLASH';
        while(FALSE !== strpos($str, $escapedBS)) {
            $escapedBS .= rand(0, 9);
        }
        $escapedAmp = 'ESCAPED_AMPERSAND';
        while(FALSE !== strpos($str, $escapedAmp)) {
            $escapedAmp .= rand(0, 9);
        }
        $str = str_replace('\\&', $escapedAmp, str_replace('\\\\', $escapedBS, $str));

        foreach (explode('&', $str) as $p) {
            $t = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<|@=)/uis", $p, 2, PREG_SPLIT_DELIM_CAPTURE);
            if (count($t) === 1) {
                continue;
            }
            $t[0] = trim($t[0]);
            $t[2] = str_replace($escapedAmp, '&', str_replace($escapedBS, '\\', trim($t[2])));
            $ar[$t[0]] = array(
                'op' => $t[1],
                'val' => $t[2]
            );
        }
        return $ar;
    }

    private static function recursiveClean(&$ar)
    {
        foreach ($ar as $k => $v) {
            if (empty($v)) {
                unset($ar[$k]);
            } else if (is_array($v)) {
                self::recursiveClean($ar[$k]);
                if (empty($ar[$k])) {
                    unset($ar[$k]);
                }
            }
        }
    }

    private static function getNameOfValue($item, $value, $conds = null)
    {
        $name = null;
        if (isset($item['field']) && is_object($item['field']) && method_exists($item['field'], 'getOptions')) {
            $options = $item['field']->getOptions();
            if (is_string($value) && isset($options[$value])) {
                $name = htmlspecialchars($options[$value]);
            }
        } else if (isset($item['items'][':values'])) {
            self::includeValuesClassInstance($item);
            $instance = ifset($item['items'][':values']['instance']);
            if ($instance && method_exists($instance, 'extra')) {
                $extras = $instance->extra($value);
                if (isset($extras['name'])) {
                    $name = $extras['name'];
                }
            } else if (isset($item['items'][':values']['sql'])) {
                $values = self::unwrapValues($item, array(), false);
                if (is_string($value) && isset($values[$value])) {
                    $name = $values[$value]['name'];
                }
            }
        } elseif (isset($item['items'])) {
            $itms = array();
            if (is_string($item['items'])) {
                $itms = crmContactsSearchHelper::makeItemsListFromQuery($item['items']);
            } else if (is_array($item['items'])) {
                $itms = $item['items'];
            }
            if (self::isNumericArray($itms)) {
                $map_itms = array();
                foreach ($itms as $itm) {
                    if (isset($itm['value'])) {
                        $map_itms[$itm['value']] = $itm;
                    } else {
                        $map_itms[""] = $itm;
                    }
                }
            } else {
                $map_itms = $itms;
            }
            if (is_string($value) && isset($map_itms[$value])) {
                if (is_string($map_itms[$value])) {
                    $name = $map_itms[$value];
                } else if (is_array($map_itms[$value]) && isset($map_itms[$value]['name'])) {
                    $name = _wp($map_itms[$value]['name']);
                }
            }
        } else {
            if (isset($item[':class'])) {
                self::includeItemClassInstance($item, array(
                    'conds' => $conds
                ));
                if (isset($item['instance']) && method_exists($item['instance'], 'getTitle')) {
                    $name = $item['instance']->getTitle();
                }
            }
        }

        return $name !== null ? $name : $value;
    }

    private static function getPeriodTitle($val_item)
    {
        $title = array(
            $val_item['op']
        );
        if ($val_item['op'] === '=') {
            $period = explode('--', $val_item['val']);
            if (count($period) < 2) {
                $title[] = date('d.m.Y', strtotime($period[0]));
            } else {
                if ($period[0] === $period[1]) {
                    $title[] = date('d.m.Y', strtotime($period[0]));
                } else {
                    $title[] = date('d.m.Y', strtotime($period[0])) . '–' . date('d.m.Y', strtotime($period[1]));
                }
            }
        } else {
            $title[] = date('d.m.Y', strtotime(substr($val_item['val'], 1)));
        }
        return $title;
    }

    public static function parseHash($hash)
    {
        if (!$hash) {
            return array(
                'conds' => array(),
                'title' => ''
            );
        }
        $hash_ar = self::parseStr($hash);

        $conds = array();
        $conds_ex = array();        // with extra params
        foreach ($hash_ar as $key => $value) {
            $p = &$conds;
            $pe = &$conds_ex;
            foreach (explode('.', $key) as $k) {
                if (preg_match("/\[(\d+)\]$/", $k, $m)) {
                    $k_prefix = preg_replace("/\[\d+\]$/", "", $k);
                    if (!isset($p[$k_prefix][$m[1]])) {
                        $p[$k_prefix][$m[1]] = array();
                        $pe[$k_prefix][$m[1]] = array();
                    }
                    $p = &$p[$k_prefix][$m[1]];
                    $pe = &$pe[$k_prefix][$m[1]];
                } else {
                    if (!isset($p[$k])) {
                        $p[$k] = array();
                        $pe[$k] = array();
                    }
                    $p = &$p[$k];
                    $pe = &$pe[$k];
                }
            }
            $p = array(
                'val' => self::unescape($value['val']),
                'op' => $value['op']
            );
            $pe = array(
                'val' => $p['val'],
                'op' => $p['op'],
                'op_str' => $value['op'] === '*=' ? '≈' : $value['op']
            );
        }
        unset($p);
        unset($pe);

        $config = self::getConfig();
        $title = array();

        $cm = new waContactModel();

        // look for <field_id> statements, turn they to contact_info.<field_id> statement
        foreach ($conds_ex as $section_id => $section) {
            if (isset($config[$section_id])) {
                continue;
            }

            // maybe it is field_id (as in original collection, like this phone*=7925 or email=test@test.com)
            $field_id = $section_id;
            if (waContactFields::get($field_id) || $cm->fieldExists($field_id)) {
                $conds['contact_info'][$field_id] = $conds[$field_id];
                $conds_ex['contact_info'][$field_id] = $conds_ex[$field_id];
            }

            unset($conds[$section_id], $conds_ex[$section_id]);

        }

        foreach ($conds_ex as $section_id => $section) {
            if (!isset($config[$section_id])) {
                unset($conds[$section_id]);
                continue;
            }
            foreach ($section as $item_id => $cond) {
                if (!isset($config[$section_id]['items'][$item_id])) {
                    unset($conds[$section_id][$item_id]);
                    continue;
                }
                $item = $config[$section_id]['items'][$item_id];

                if (isset($item['field_id'])) {
                    $title_item = array();
                    $title_item[] =_wp(ifset($item['name'], 'Unknown'));        // _wp('Unknown')
                    if (isset($item['type'])) {
                        if ($item['type'] !== 'Composite') {

                            if (!isset($conds_ex[$section_id][$item_id])) {
                                continue;
                            }

                            $val_item = $conds_ex[$section_id][$item_id];
                            if (isset($val_item['val']))
                            {
                                $title_item[] = $val_item['op_str'];
                                $title_item[] = self::getNameOfValue($item, $val_item['val']);
                            }
                            else
                            {
                                foreach ($val_item as $k => $v_item) {
                                    if (!isset($v_item['val'])) {
                                        continue;
                                    }
                                    if (isset($item['items'][":{$k}"]['name'])) {
                                        if ($k === 'period') {
                                            $title_item = array_merge($title_item, self::getPeriodTitle($v_item));
                                        } else {
                                            $title_item[] = $v_item['op_str'];
                                            $title_item[] =_wp($item['items'][":{$k}"]['name']);
                                            $title_item[] = $v_item['val'];
                                        }
                                    } else if (isset($item['items'][$k]['name'])) {
                                        $title_item[] = $v_item['op_str'];
                                        $title_item[] = _wp($item['items'][$k]['name']);
                                    }
                                }
                                $title[] = $title_item;
                                continue;
                            }

                        } else if ($item['type'] === 'Composite') {
                            if (empty($item['field'])) {
                                continue;
                            }
                            $subtitle = array();
                            foreach ($item['field']->getFields() as $fld_id => $fld) {
                                if ($fld instanceof waContactHiddenField) {
                                    continue;
                                }
                                if (!isset($conds_ex[$section_id][$item_id][$fld_id])) {
                                    continue;
                                }
                                $subtitle_item = array();
                                $val_item = $conds_ex[$section_id][$item_id][$fld_id];

                                if (isset($val_item['val']))
                                {
                                    $subtitle_item[] = isset($item['items'][$fld_id]['name']) ? _wp($item['items'][$fld_id]['name']) : $fld->getName();
                                    $subtitle_item[] = $val_item['op_str'];
                                    $fld_item = array();
                                    if (isset($item['items'][$fld_id])) {
                                        $fld_item = $item['items'][$fld_id];
                                    }
                                    $fld_item['field'] = $fld;
                                    $subtitle_item[] = self::getNameOfValue($fld_item, $val_item['val']);
                                }
                                else
                                {
                                    foreach ($val_item as $k => $v_item) {
                                        if (!isset($v_item['val'])) {
                                            continue;
                                        }
                                        if (isset($item['items'][$fld_id]['items'][":{$k}"]['name'])) {
                                            if ($k === 'period') {
                                                $title_item = self::getPeriodTitle($v_item);
                                            } else {
                                                $title_item[] = $v_item['op_str'];
                                                $title_item[] =_wp($item['items'][":{$k}"]['name']);
                                                $title_item[] = $v_item['val'];
                                            }
                                        } else if (isset($item['items'][$fld_id]['items'][$k]['name'])) {
                                            $subtitle_item[] = isset($item['items'][$fld_id]['name']) ? _wp($item['items'][$fld_id]['name']) : $fld->getName();
                                            $subtitle_item[] = $v_item['op_str'];
                                            $subtitle_item[] = _wp($item['items'][$fld_id]['items'][$k]['name']);
                                        }
                                    }
                                }
                                if ($subtitle_item) {
                                    $subtitle[] = implode('', $subtitle_item);
                                }
                            }
                            if ($subtitle) {
                                $title_item[] = ' ('.implode(', ', $subtitle).')';
                            }
                        }
                    }
                    $title[] = $title_item;
                } else {

                    if (!empty($item['multi'])) {

                        // items are interpreted as conditions-items
                        if (isset($item['items'])) {
                            $title_item = array();
                            if (!isset($item['title']) || $item['title']) {
                                $title_item[] = _wp(ifset($item['name'], 'Unknown'));      // _wp('Unknown')
                            }
                            $subtitle = array();
                            foreach ($item['items'] as $itm_id => $itm) {
                                if (isset($conds_ex[$section_id][$item_id]) && is_array($conds_ex[$section_id][$item_id])) {
                                    if (!isset($conds_ex[$section_id][$item_id][$itm_id])) {
                                        continue;
                                    }
                                    $val_item = $conds_ex[$section_id][$item_id][$itm_id];

                                    $subtitle_item = array(_wp($itm['name']));
                                    if (isset($val_item['val'])) {
                                        if (isset($itm['items'][':period'])) {
                                            $subtitle_item = array_merge($subtitle_item, self::getPeriodTitle($val_item));
                                        } else {
                                            $subtitle_item[] = $val_item['op_str'];
                                            $subtitle_item[] = self::getNameOfValue($itm, $val_item['val'], $conds);
                                        }
                                    } else {
                                        foreach ($val_item as $k => $v_item) {
                                            if (!isset($v_item['val'])) {
                                                continue;
                                            }
                                            if ($k === 'period') {
                                                $subtitle_item = array_merge($subtitle_item, self::getPeriodTitle($v_item));
                                            } else {
                                                if (isset($itm['items'][$k])) {
                                                    $subtitle_item[] = $v_item['op_str'];
                                                    $subtitle_item[] = self::getNameOfValue($itm['items'][$k], $v_item['val']);
                                                } else {

                                                }
                                            }
                                        }
                                    }
                                    $subtitle[] = implode('', $subtitle_item);
                                }
                            }
                        }

                        if ($subtitle) {
                            if ($title_item) {
                                $title_item[] = ' (' . implode(', ', $subtitle) . ')';
                            } else {
                                $title_item[] = implode(', ', $subtitle);
                            }
                        }

                        $title[] = $title_item;

                    } else {

                        // special for contact_info.name branch
                        if ($section_id === 'contact_info' && $item_id === 'name') {
                            $subtitle = array();
                            if (isset($item['items'])) {
                                foreach ($item['items'] as $itm_id => $itm) {
                                    if (!isset($conds_ex[$section_id][$item_id][$itm_id])) {
                                        continue;
                                    }
                                    $val_item = $conds_ex[$section_id][$item_id][$itm_id];
                                    if (isset($val_item['val'])) {
                                        $subtitle[] = _wp($itm['name']) . $val_item['op_str'] . $val_item['val'];
                                    } else {
                                        if (isset($itm['items'])) {
                                            foreach ($itm['items'] as $it_id => $it) {
                                                if (isset($conds_ex[$section_id][$item_id][$itm_id][$it_id])) {
                                                    $subtitle[] = _wp($itm['name']) . '=' . _wp($it['name']);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $title[] = array(implode(', ', $subtitle));
                        } else if (isset($item['items'])) {
                            $title_item = array();
                            if (!isset($item['title']) || $item['title']) {
                                $title_item[] = _wp(ifset($item['name'], 'Unknown'));      // _wp('Unknown')
                            }
                            $subtitle = array();
                            foreach ($item['items'] as $itm_id => $itm) {
                                if (isset($conds_ex[$section_id][$item_id]) && is_array($conds_ex[$section_id][$item_id])) {
                                    if (isset($conds_ex[$section_id][$item_id][$itm_id]) ||
                                        ($itm_id[0] === ':' && isset($conds_ex[$section_id][$item_id][substr($itm_id, 1)]))
                                    )
                                    {
                                        if ($itm_id[0] === ':') {
                                            $val_item = $conds_ex[$section_id][$item_id][substr($itm_id, 1)];
                                        } else {
                                            $val_item = $conds_ex[$section_id][$item_id][$itm_id];
                                        }
                                        $val = $val_item['val'];
                                        $op = $val_item['op'];
                                        if (is_array($val)) {
                                            foreach ($val as $k => $v) {
                                                if (isset($item['items'][$itm_id]['items'][$k])) {
                                                    $subtitle[] = _wp($itm['name']) . '=' . _wp($item['items'][$itm_id]['items'][$k]['name']);
                                                    continue;
                                                }
                                            }
                                        } else {
                                            if ($itm_id === ':period') {
                                                $title_item = array_merge($title_item, self::getPeriodTitle($val_item));
                                            } else {
                                                $title_item[] = '=';
                                                $title_item[] = _wp($itm['name']);
                                            }
                                        }
                                    }
                                }
                            }
                            if ($subtitle) {
                                $title_item[] = '=';
                                $title_item[] = implode(', ', $subtitle);
                            }
                            $title[] = $title_item;
                        } else {
                            $conds_ar = $conds_ex[$section_id][$item_id];
                            if (!self::isNumericArray($conds_ar)) {
                                $conds_ar = array($conds_ar);
                            }
                            foreach ($conds_ar as $i => $cond_ar) {
                                $title_item = array(_wp(ifset($item['name'], 'Unknown')));      // _wp('Unknown')
                                if (is_array($cond_ar)) {
                                    $subtitle = array();
                                    foreach ($cond_ar as $it_id => $val) {
                                        if ($it_id !== 'not') {
                                            $subtitle[] =
                                                _wp(ifset($item['items'][$it_id]['name'], $it_id)) .
                                                '=' .
                                                $val;
                                        } else {
                                            $title_item[0] = _w('Not') . ' ' . $title_item[0];
                                        }
                                    }
                                    if ($subtitle) {
                                        $title_item[] = ' (' . implode(', ', $subtitle) . ')';
                                    }
                                }
                                $title[] = $title_item;
                            }
                        }
                    }
                }
            }
        }
        foreach ($title as $k => $title_item) {
            if ($title_item) {
                $title[$k] = implode('', $title_item);
            } else {
                unset($title[$k]);
            }
        }
        //self::recursiveClean($conds_ex);
        return array(
            'conds' => $conds,
            'title' => implode(', ', $title)
        );
    }

    public static function getMetrics()
    {
        $config = self::getConfig();
        if (!$config) {
            return false;
        }
        $metrics = array();
        foreach ($config as $section_id => $section) {
            foreach ($section['items'] as $item_id => $item) {
                if (!empty($item['join'])) {
                    foreach ($item['join'] as $j => $join) {
                        if ($j === 'fields') {
                            foreach ($join as $field_id => $field) {
                                if (!empty($field['metric'])) {
                                    $metric_id = "{$section_id}.{$item_id}.{$field_id}";
                                    $metrics[$metric_id] = array(
                                        'id' => $metric_id,
                                        'name' => $field['name']
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
        return $metrics;
    }

    public static function getMetricFields($fields = array())
    {
        if (!$fields) {
            return array();
        }
        $metrics = self::getMetrics();
        $metric_fields = array();
        foreach ($fields as $f_id) {
            if (isset($metrics[$f_id])) {
                $metric_fields[$f_id] = array(
                    'id' => $f_id,
                    'name' => $metrics[$f_id]['name'],
                    'type' => 'Custom'
                );
            }
        }
        return $metric_fields;
    }

    public static function getMetricSql($metric, $filter)
    {
        $config = self::getConfig();
        if (!$config || !$metric) {
            return false;
        }
        $metric = explode('.', $metric);
        $sql = array(
            'from' => '',
            'contact_id' => 'contact_id',
            'metric' => '',
            'group_by' => ''
        );
        foreach ($config as $section_id => $section) {
            if ($metric[0] !== $section_id) {
                continue;
            }
            foreach ($section['items'] as $item_id => $item) {
                if ($metric[1] !== $item_id) {
                    continue;
                }
                if (!empty($item['join'])) {
                    foreach ($item['join'] as $j => $join) {
                        if ($j === 'table') {
                            $sql['from'] = $join;
                            $sql['contact_id'] = 'contact_id';
                        }
                        if ($j === 'on') {
                            $on = array_map('trim', explode('=', $join));
                            if (isset($on[1]) && strpos($on[1], '.') !== false) {
                                $pos = strpos($on[1], '.');
                                $sql['contact_id'] = substr($on[1], $pos + 1);
                            }
                        }
                        if ($j === 'fields') {
                            foreach ($join as $field_id => $field) {
                                if (!empty($field['metric']) && $field_id === $metric[2]) {
                                    $field['sql'] = str_replace(':table.', '', $field['sql']);
                                    $sql['metric'] = "{$field['sql']}";
                                }
                            }
                        }
                    }
                }
                if ($item['group_by']) {
                    $sql['group_by'] = 1;
                }
            }
        }

        $sql['where'] = 1;
        if ($filter) {
            $sql['where'] = "{$sql['contact_id']} IN (".  implode(',', $filter).")";
        }
        $res = "SELECT {$sql['contact_id']} AS contact_id, {$sql['metric']} AS metric
            FROM {$sql['from']} WHERE {$sql['where']} ";
        if ($sql['group_by']) {
            $res .= " GROUP BY contact_id";
        }
        return $res;
    }

    public static function addMetrics(&$contacts, $custom_fields = array())
    {
        $m = self::getModel();
        $contact_ids = array();
        foreach ($contacts as $c) {
            $contact_ids[] = $c['id'];
        }
        $metrics = self::getMetrics();
        foreach ($custom_fields as $f) {
            if ($f['type'] !== 'Custom') {
                continue;
            }
            $f_id = $f['id'];
            if (isset($metrics[$f_id])) {
                $sql = self::getMetricSql($f_id, $contact_ids);
                $res = $m->query($sql)->fetchAll('contact_id');
                foreach ($contacts as &$c) {
                    $c_id = $c['id'];
                    if (isset($res[$c_id])) {
                        $c[$f_id] = $res[$c_id]['metric'];
                    } else {
                        $c[$f_id] = '';
                    }
                }
                unset($c);
            }
        }
    }

    /**
     *
     * @param array|string $item_id Array of IDs or one ID
     */
    public static function setContactItems($item_id)
    {
        $user = wa()->getUser();
        $app_id = 'crm';
        $name = 'search_form_items';
        if (is_array($item_id)) {
            $user->setSettings($app_id, $name, implode(',', $item_id));
        } else {
            $item_ids = $user->getSettings($app_id, $name);
            if ($item_ids) {
                $item_ids = explode(',', $item_ids);
            }
            if (!is_array($item_ids) || !$item_ids) {
                $item_ids = array();
            }
            $k = array_search($item_id, $item_ids);
            if ($k === false) {
                $item_ids[] = $item_id;
                $user->setSettings($app_id, $name, implode(',', $item_ids));
            }
        }
    }

    public static function getContactItems()
    {
        $item_ids = wa()->getUser()->getSettings('crm', 'search_form_items');
        if ($item_ids) {
            return explode(',', $item_ids);
        } else {
            return array();
        }
    }

    /**
     *
     * @param array|string|null $item_id
     */
    public static function delContactItems($item_id = null)
    {
        $user = wa()->getUser();
        $app_id = 'crm';
        $name = 'search_form_items';
        if ($item_id) {
            $item_ids = $user->getSettings($app_id, $name);
            if ($item_ids) {
                $item_ids = explode(',', $item_ids);
                if (is_array($item_ids) && $item_ids) {
                    foreach ((array) $item_id as $it_id) {
                        $k = array_search($it_id, $item_ids);
                        if ($k !== false) {
                            unset($item_ids[$k]);
                        }
                    }
                    if ($item_ids) {
                        $user->setSettings($app_id, $name, implode(',', $item_ids));
                    } else {
                        $user->delSettings($app_id, $name);
                    }
                } else {
                    $user->delSettings($app_id, $name);
                }
            }
        } else {
            $user->delSettings($app_id, $name);
        }
    }

    public static function escape($val, $slash = false)
    {
        if ($slash) {
            return str_replace('/', '\\/', str_replace('&', '\\&', str_replace('\\', '\\\\', $val)));
        } else {
            return str_replace('&', '\\&', str_replace('\\', '\\\\', $val));
        }
    }

    public static function unescape($val, $slash = false)
    {
        if ($slash) {
            return str_replace('\\\\', '\\', str_replace('\\&', '&', str_replace('\/', '/', $val)));
        } else {
            return str_replace('\\\\', '\\', str_replace('\\&', '&', $val));
        }
    }


    public static function prepare(waContactsCollection $collection, $hash, $auto_title)
    {
        $hash_ar = crmContactsSearchHelper::parseHash($hash);

        $conds = $hash_ar['conds'];
        $title = $hash_ar['title'];

        if (empty($conds)) {
            $collection->addWhere(0);
        }

        $m = new waModel();
        $config = crmContactsSearchHelper::getConfig();
        $highlight_terms = array();
        if (isset($conds['contact_info'])) {
            $query = array();
            foreach ($conds['contact_info'] as $item_id => $val_item) {
                if ($item_id === 'name') {
                    foreach ($val_item as $itm_id => $vl_item) {
                        if (isset($vl_item['val'])) {
                            $query[] = $itm_id . $vl_item['op'] . self::escape($vl_item['val']);
                            $highlight_terms[] = $vl_item['val'];
                            unset($conds['contact_info'][$item_id][$itm_id]);
                        }
                    }
                } else {
                    if (isset($config['contact_info']['items'][$item_id]['field_id'])) {
                        if (isset($val_item['val'])) {
                            if ($config['contact_info']['items'][$item_id]['field_id'] === 'phone') {
                                $phone_val = trim($val_item['val']);
                                $has_plus_prefix = substr($phone_val, 0, 1) === '+';
                                $pure_val = preg_replace("/[^\d]+/", "", $phone_val);
                                $pure_val = $has_plus_prefix ? ('+' . $pure_val) : $pure_val;
                                $query[] = $item_id . $val_item['op'] . self::escape($pure_val);
                                $highlight_terms[] = $pure_val;
                                $phone_field = waContactFields::get('phone');
                                if ($phone_field instanceof waContactPhoneField) {
                                    $highlight_terms[] = $phone_field->format($pure_val, 'html');
                                }
                            } else {
                                $query[] = $item_id . $val_item['op'] . self::escape($val_item['val']);
                                $highlight_terms[] = $val_item['val'];
                            }
                            unset($conds['contact_info'][$item_id]);
                        } else {
                            $item = $config['contact_info']['items'][$item_id];
                            $field = $item['field'];
                            $composite = $field && $field instanceof waContactCompositeField;
                            foreach ($val_item as $itm_id => $vl_item) {
                                if (isset($vl_item['val'])) {
                                    if ($composite) {
                                        $t = $vl_item['val'];
                                        $itm = null;
                                        if (isset($item['items'][$itm_id])) {
                                            $itm = $item['items'][$itm_id];
                                        } else {
                                            $sub_f = $item['field']->getFields($itm_id);
                                            if ($sub_f) {
                                                $itm = array(
                                                    'name' => $sub_f->getName()
                                                );
                                            }
                                        }
                                        if (isset($itm['items'][':values']['instance']) && method_exists($itm['items'][':values']['instance'], 'getHighlightTerm')) {
                                            $t = $itm['items'][':values']['instance']->getHighlightTerm($t);
                                        }
                                        $highlight_terms[] = $t;
                                        $query[] = $field->getId() . ':' . $itm_id . $vl_item['op'] . self::escape($vl_item['val'], true);
                                        unset($conds['contact_info'][$item_id][$itm_id]);
                                    } else {
                                        // no such caces: blank, not_blank or :period etc
                                        if (!isset($item['items'][$itm_id]) && !isset($item['items'][":{$itm_id}"])) {
                                            $highlight_terms[] = $vl_item['val'];
                                            $query[] = $itm_id . $vl_item['op'] .  self::escape($vl_item['val'], true);
                                            unset($conds['contact_info'][$item_id][$itm_id]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($query) {
                $collection->setHash('search/' . implode('&', $query));
                $collection->prepare(false, $auto_title);
            }
        }

        if (empty($conds)) {
            $collection->addWhere(0);
        }

        foreach ($conds as $section_id => $section) {

            $section_join_aliases = array();

            if (!empty($config[$section_id]['joins'])) {
                foreach ($config[$section_id]['joins'] as $placeholder => $join) {
                    $it = array('join' => $join);
                    $al = self::addJoin($it, $collection, $section_join_aliases);
                    if ($al) {
                        $section_join_aliases[$placeholder] = $al;
                    }
                }
            }

            foreach ($section as $item_id => $conds_ar) {

                if (!crmContactsSearchHelper::isNumericArray($conds_ar)) {
                    $conds_ar = array($conds_ar);
                }

                foreach ($conds_ar as $cond) {
                    if (!isset($config[$section_id]['items'][$item_id])) {
                        return false;
                    }

                    $item = $config[$section_id]['items'][$item_id];

                    if (!empty($item['where'])) {
                        $collection->addWhere($item['where']);
                    }
                    if (!empty($item['group_by'])) {
                        $collection->setGroupBy($item['group_by'] === 1 ? 'c.id' : $item['group_by']);
                    }

                    $item_join_aliases = array();

                    if (!empty($item['joins'])) {
                        foreach ($item['joins'] as $placeholder => $join) {
                            $it = array('join' => $join);
                            $alias = self::addJoin($it, $collection, $item_join_aliases);
                            if ($alias) {
                                $item_join_aliases[$placeholder] = $alias;
                            }
                        }
                    }

                    if (!empty($item['join']) || !empty($item['left_join'])) {
                        $alias = self::addJoin($item, $collection, $section_join_aliases);
                        if (!$alias) {
                            $alias = self::addLeftJoin($item, $collection, $section_join_aliases);
                        }
                        $item_join_aliases[':parent_table'] = $alias;
                    }

                    if (isset($item['items'])) {
                        foreach ($item['items'] as $it_id => &$it) {
                            if (isset($cond[$it_id]) || ($it_id === ':period' && isset($cond['period']))) {
                                if (!empty($item['field'])) {
                                    $storage = $item['field']->getStorage();
                                    if ($storage && $storage instanceof waContactStorage)
                                    {
                                        $table = $storage->getModel()->getTableName();
                                        if (!empty($it['join']) && empty($item['join']['table'])) {
                                            $item['join']['table'] = $table;
                                        }
                                        if (!empty($item['left_join']) && empty($item['left_join']['table'])) {
                                            $item['join']['table'] = $table;
                                        }
                                        if (!crmContactsSearchHelper::addJoin($item, $collection, $section_join_aliases)) {
                                            crmContactsSearchHelper::addLeftJoin($item, $collection, $section_join_aliases);
                                        }
                                        if ($it_id === ':period') {
                                            crmContactsSearchHelper::addWhereForPeriodItem($cond['period'], $it, $collection, $section_join_aliases);
                                        } else {
                                            crmContactsSearchHelper::addWhere($it, $collection, $section_join_aliases);
                                            crmContactsSearchHelper::addHaving($it, $collection, $section_join_aliases);
                                        }
                                    }
                                } else {
                                    if (isset($cond['val'])) {
                                        $al = crmContactsSearchHelper::addJoin($it, $collection,
                                            $section_join_aliases + $item_join_aliases + array(
                                                ':items' => "'" . $m->escape($cond[$it_id]['val']) . "'"
                                            ));
                                        $replace = $section_join_aliases + $item_join_aliases + array(
                                                ':table' => $al,
                                                ':items' => "'" . $m->escape($cond[$it_id]['val']) . "'"
                                            );
                                        if ($it_id === ':period') {
                                            crmContactsSearchHelper::addWhereForPeriodItem($cond, $it, $collection, $replace);
                                        } else {
                                            crmContactsSearchHelper::addWhere($it, $collection, $replace);
                                            crmContactsSearchHelper::addHaving($it, $collection, $replace);
                                        }
                                    } else {
                                        foreach ($cond as $val_item_id => $val_item) {
                                            if ($val_item_id === $it_id || ($it_id[0] === ':' && substr($it_id, 1) === $val_item_id)) {
                                                if (isset($val_item['val'])) {
                                                    $replace = $section_join_aliases + $item_join_aliases + array(
                                                            ':items' => "'" . $m->escape($val_item['val']) . "'"
                                                        );
                                                    $al = crmContactsSearchHelper::addJoin($it, $collection, $replace);
                                                    if (!$al) {
                                                        $al = crmContactsSearchHelper::addClassJoin($it, $collection, $replace, array(
                                                            'conds' => $conds
                                                        ));
                                                    }
                                                    $replace = $section_join_aliases + $item_join_aliases + array(
                                                            ':table' => $al,
                                                            ':items' => "'" . $m->escape($val_item['val']) . "'"
                                                        );
                                                    if ($it_id === ':period' && $val_item_id === 'period') {
                                                        crmContactsSearchHelper::addWhereForPeriodItem($val_item, $it, $collection, $replace);
                                                    } else {
                                                        crmContactsSearchHelper::addExtWhere($it, $collection, array(
                                                            'replace' => $replace,
                                                            'val_item' => $val_item
                                                        ));
                                                        crmContactsSearchHelper::addExtHaving($it, $collection, array(
                                                            'replace' => $replace,
                                                            'val_item' => $val_item
                                                        ));
                                                    }
                                                    crmContactsSearchHelper::addClassWhere($it, $collection, $val_item, $replace, array(
                                                        'conds' => $conds
                                                    ));
                                                    crmContactsSearchHelper::addClassHaving($it, $collection, $val_item, $replace, array(
                                                        'conds' => $conds
                                                    ));
                                                } else {
                                                    foreach ($val_item as $vl_item_id => $vl_item) {
                                                        if (isset($vl_item['val']) && !isset($it['items'][$vl_item_id]) && !isset($it['items'][":{$vl_item_id}"]))        // no such cases: blank, not_blank or :period etc.
                                                        {
                                                            $replace = $section_join_aliases + $item_join_aliases + array(
                                                                    ':items' => "'" . $m->escape($vl_item['val']) . "'"
                                                                );
                                                            $al = crmContactsSearchHelper::addJoin($it, $collection, $replace);
                                                            if (!$al) {
                                                                crmContactsSearchHelper::addClassJoin($it, $collection, $replace, array(
                                                                    'conds' => $conds
                                                                ));
                                                            }

                                                            $replace = $section_join_aliases + $item_join_aliases + array(
                                                                    ':table' => $al,
                                                                    ':items' => "'" . $m->escape($vl_item['val']) . "'"
                                                                );
                                                            crmContactsSearchHelper::addExtWhere($it, $collection, array(
                                                                'replace' => $replace,
                                                                'val_item' => $vl_item
                                                            ));
                                                            crmContactsSearchHelper::addExtHaving($it, $collection, array(
                                                                'replace' => $replace,
                                                                'val_item' => $vl_item
                                                            ));
                                                            crmContactsSearchHelper::addClassWhere($it, $collection, array($vl_item_id => $vl_item), $replace, array(
                                                                'conds' => $conds
                                                            ));
                                                            crmContactsSearchHelper::addClassHaving($it, $collection, array($vl_item_id => $vl_item), $replace, array(
                                                                'conds' => $conds
                                                            ));
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    //$it['name'] = $cond[$it_id];
                                }
                                if (isset($it['items']) && is_array($it['items'])) {
                                    if (isset($cond[$it_id]['val'])) {
                                        $i = ifset($it['items'][$cond[$it_id]['val']]);
                                        if ($i) {
                                            crmContactsSearchHelper::addWhere($i, $collection);
                                            crmContactsSearchHelper::addHaving($i, $collection);
                                        } else {
                                            if (isset($it['items'][':values'])) {

                                                $replace = $section_join_aliases + $item_join_aliases + array(
                                                        ':items' => "'" . $m->escape($cond[$it_id]['val']) . "'",
                                                    );

                                                $al = crmContactsSearchHelper::addJoin($it['items'][':values'], $collection, $replace);
                                                if (!$al) {
                                                    $al = crmContactsSearchHelper::addClassJoin($it, $collection, $replace, array(
                                                        'conds' => $conds
                                                    ));
                                                }

                                                $replace = $section_join_aliases + $item_join_aliases + array(
                                                        ':table' => $al,
                                                        ':items' => "'" . $m->escape($cond[$it_id]['val']) . "'"
                                                    );
                                                crmContactsSearchHelper::addExtWhere($it['items'][':values'], $collection, array(
                                                    'replace' => $replace,
                                                    'val_item' => $cond[$it_id]
                                                ));
                                                crmContactsSearchHelper::addExtHaving($it['items'][':values'], $collection, array(
                                                    'replace' => $replace,
                                                    'val_item' => $cond[$it_id]
                                                ));
                                                crmContactsSearchHelper::addClassWhere($it, $collection, $cond[$it_id], $replace, array(
                                                    'conds' => $conds
                                                ));
                                                crmContactsSearchHelper::addClassHaving($it, $collection, $cond[$it_id], $replace, array(
                                                    'conds' => $conds
                                                ));
                                            }
                                            if (isset($it['items'][':period'])) {
                                                crmContactsSearchHelper::addWhereForPeriodItem($cond[$it_id], $it['items'][':period'], $collection, $replace);
                                            }
                                        }
                                    } else {
                                        foreach ($it['items'] as $i_id => $i) {
                                            if (isset($cond[$it_id][$i_id]) || ($i_id === ':period' && isset($cond[$it_id]['period']))) {
                                                if ($i_id === ':period') {
                                                    $al = crmContactsSearchHelper::addJoin($i, $collection, $section_join_aliases + $item_join_aliases);
                                                    if (!$al) {
                                                        $al = crmContactsSearchHelper::addLeftJoin($i, $collection, $section_join_aliases + $item_join_aliases);
                                                    }
                                                    crmContactsSearchHelper::addWhereForPeriodItem($cond[$it_id]['period'], $i, $collection,
                                                        $section_join_aliases + $item_join_aliases + array(
                                                            ':table' => $al
                                                        ));
                                                } else {
                                                    $al = crmContactsSearchHelper::addJoin($i, $collection, $section_join_aliases + $item_join_aliases);
                                                    if (!$al) {
                                                        $al = crmContactsSearchHelper::addLeftJoin($i, $collection, $section_join_aliases + $item_join_aliases);
                                                    }
                                                    crmContactsSearchHelper::addWhere($i, $collection, $section_join_aliases + $item_join_aliases + array(
                                                            ':table' => $al
                                                        ));
                                                    crmContactsSearchHelper::addHaving($i, $collection, $section_join_aliases + $item_join_aliases + array(
                                                            ':table' => $al
                                                        ));
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                        }
                        unset($it);
                    }

                }
            }
        }
        $collection->setGroupBy('c.id');

        if ($auto_title) {
            if (!$title) {
                $title = _wp('Search results');
            }
            $collection->setTitle($title);
        }

        $info = $collection->getInfo();
        $info['highlight_terms'] = $highlight_terms;
        $collection->setInfo($info);

    }

    public static function buildSearchHash($hash, $prefix = null)
    {
        if (empty($hash)) {
            return '';
        }
        $hash = (array) $hash;
        $res_str_hash = array();
        foreach ($hash as $key => $h) {
            if (is_array($h)) {
                if (isset($h['op']) && isset($h['val'])) {
                    if (is_array($h['val'])) {
                        $h['val'] = ifset($h['val'][0], '') . '--' . ifset($h['val'][1]);
                    }
                    $res_str_hash[] = ($prefix ? $prefix . '.' : '') . "{$key}{$h['op']}{$h['val']}";
                } else {
                    $res_str_hash[] = self::buildSearchHash($h, $prefix ? $prefix . '.' . $key : $key);
                }
            }
        }
        return implode('&', $res_str_hash);
    }

    /**
     * @param int|waContactField $field
     * @return bool
     */
    public static function isContactFieldEnabledForSearch($field)
    {
        if (is_object($field) && $field instanceof waContactField) {
            $field_id = $field->getId();
        } else {
            $field_id = $field;
        }
        $item = self::getItem("contact_info.{$field_id}", null, array(
            'unwrap_values' => false,
            'unwrap_class' => false,
            'count' => false
        ));
        return $item !== null;
    }

    /**
     * @param int|waContactField $field
     */
    public static function enableContactFieldForSearch($field)
    {
        if (waConfig::get('is_template')) {
            return;
        }
        if (is_string($field)) {
            $field = waContactFields::get($field, 'all');
        }
        if ($field instanceof waContactField) {
            $field_id = $field->getId();
            $storage = $field->getStorage();
            $table = null;
            if ($storage instanceof waContactStorage) {
                $model = $storage->getModel();
                if ($model instanceof waModel) {
                    $table = $model->getTableName();
                }
            }
            if ($table === 'wa_contact_data' || $table === 'wa_contact') {
                $table = 'wa_contact_data';
                $sql_t = "(SELECT COUNT(*) FROM `{$table}` WHERE contact_id = c.id AND field = '{$field_id}') :comparation ";
                $config = array(
                    'field_id' => $field_id,
                );
                $config['items'] = array(
                    'blank' => array(
                        'name' => 'Empty',
                        'where' => str_replace(':comparation', "= 0", $sql_t)
                    ),
                    'not_blank' => array(
                        'name' => 'Not empty',
                        'where' => str_replace(':comparation', "> 0", $sql_t)
                    ),
                    ':sep' => array(),
                    ':values' => array(
                        "autocomplete" => "AND value LIKE '%:term%'",
                        "limit" => 10,
                        "sql" => "SELECT value, value AS name, COUNT(*) count
                    FROM {$table}
                    WHERE field = '{$field_id}' :autocomplete
                    GROUP BY value
                    ORDER BY count DESC
                    LIMIT :limit",
                        "count" => "SELECT COUNT(DISTINCT value) FROM `{$table}` WHERE field = '{$field_id}'"
                    )
                );
                self::updateItem("contact_info.{$field_id}", $config);
            }
        }
    }

    /**
     * @param int|waContactField $field
     */
    public static function disableContactFieldForSearch($field)
    {
        if (waConfig::get('is_template')) {
            return;
        }
        if (is_object($field) && $field instanceof waContactField) {
            $field_id = $field->getId();
        } else {
            $field_id = $field;
        }
        self::removeItem("contact_info.{$field_id}");
    }
}
