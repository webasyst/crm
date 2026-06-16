<?php

class crmContactsCollection extends waContactsCollection
{
    /**
     * @var waModel[]
     */
    protected $models;

    /**
     * @var crmRights
     */
    protected $crm_rights;

    public function segmentPrepare($id, $auto_title = true)
    {
        $id = (int) $id;
        if ($id <= 0) {
            $this->where[] = 0;
            return;
        }

        $sm = $this->getSegmentModel();
        $segment = $sm->getSegment($id);

        if (!$segment) {
            $this->where[] = 0;
            return;
        }

        $title = '';
        if ($auto_title) {
            $title = $segment['name'];
        }

        if ($segment['type'] === 'category') {
            $this->setHash('category/' . $segment['category_id']);
        } else {
            $this->setHash($segment['hash']);
        }
        $this->prepare(false, true);

        $this->setTitle($title);

        $info = $this->getInfo();
        $info['segment'] = $segment;

        if (empty($this->options['update_count_ignore'])) {
            self::updateSegmentCounter($this, $segment);
        }

        $info['segment']['count'] = $this->count();
        $this->setInfo($info);
    }

    protected static function updateSegmentCounter(crmContactsCollection $collection, $segment, $update_right_away = false)
    {
        $wcc = new waContactCategoryModel();
        $sm = new crmSegmentModel();
        $scm = new crmSegmentCountModel();

        $user = wa()->getUser();
        $is_admin = $user->isAdmin();
        $is_shared = $segment['shared'] ? true : false;

        $new_count = $collection->count();
        $old_counter = $scm->getCounters($segment['id'], wa()->getUser()->getId());

        if (!$is_shared) {
            if ($segment['count'] !== $new_count) {
                $sm->updateCount($segment['id'], $new_count);
            }
        } elseif (!$is_admin && $is_shared) {
            if (!$old_counter || $old_counter !== $new_count) {
                $scm->updateCount($segment['id'], wa()->getUser()->getId(), $new_count);
            }
            if ($new_count > $segment['count']) {
                $sm->updateCount($segment['id'], $new_count);
                $wcc->updateCount($segment['id'], $new_count);
            }
        } elseif ($is_admin) {
            if ($segment['count'] !== $new_count)
            $sm->updateCount($segment['id'], $new_count);
            $wcc->updateCount($segment['id'], $new_count);
        }
    }


    public function vaultPrepare($ids, $auto_title = true)
    {
        $ids = array_filter(array_map('intval', explode(',', $ids)));
        if (!$ids) {
            $this->where[] = 0;
            return;
        }

        $this->where[] = "c.crm_vault_id IN ('".join("','", $ids)."')";

        if (count($ids) != 1) {
            return;
        }

        $vault_model = new crmVaultModel();
        $vault = $vault_model->getById(reset($ids));
        if (!$vault) {
            return;
        }

        $info = $this->getInfo();
        $info['vault'] = $vault;
        $this->setInfo($info);

        if ($auto_title && count($ids) == 1) {
            $this->addTitle($vault['name']);
        }

        $this->setUpdateCount(array(
            'id' => $vault['id'],
            'count' => $vault['count'],
            'model' => $vault_model,
        ));
    }

    public function prepare($new = false, $auto_title = true)
    {
        $were_prepared = $this->prepared;
        parent::prepare($new, $auto_title);
        if (!$were_prepared && !empty($this->options['check_rights']) && !wa()->getUser()->isAdmin('crm')) {
            $rights = $this->getCrmRights();
            $this->where[] = "c.crm_vault_id IN (".join(',', $rights->getAvailableVaultIds()).")";
        }
    }

    public function tagPrepare($id, $auto_title)
    {
        $id = (int) $id;
        if ($id <= 0) {
            $this->where[] = 0;
            return;
        }

        $tm = new crmTagModel();
        $tag = $tm->getById($id);
        if (!$tag) {
            $this->where[] = 0;
            return;
        }

        $al = $this->addJoin('crm_contact_tags');
        $this->addWhere("{$al}.tag_id={$tag['id']}");

        $title = '';
        if ($auto_title) {
            $title = $tag['name'];
        }

        $this->addTitle($title);

        $info = $this->getInfo();
        $info['tag'] = $tag;
        $this->setInfo($info);
    }

    public function importPrepare($date, $auto_title)
    {
        $m = new waModel();
        $this->addWhere("c.create_method = 'import'");
        $this->addWhere('c.create_contact_id = '.wa()->getUser()->getId());
        $this->addWhere("c.create_datetime >= '".$m->escape($date)."'");
    }

    public function crmSearchPrepare($query, $auto_title = true)
    {
        if (empty($query)) {
            $this->addWhere(0);
        }
        return crmContactsSearchHelper::prepare($this, $query, $auto_title);
    }

    public function applyCategorySetFilter(array $segment_set)
    {
        $segment_ids = array_values(array_unique(array_merge(
            (array) ifset($segment_set, 'include_any', []),
            (array) ifset($segment_set, 'require_all', []),
            (array) ifset($segment_set, 'exclude_any', [])
        )));
        if (!$segment_ids) {
            return;
        }

        $sm = $this->getSegmentModel();
        $segment_rows = $sm->select('id, category_id')
            ->where('type = ?', crmSegmentModel::TYPE_CATEGORY)
            ->where('id IN (:ids)', ['ids' => $segment_ids])
            ->fetchAll('id');
        if (!$segment_rows) {
            $this->addWhere(0);
            return;
        }

        $to_category_ids = function ($ids) use ($segment_rows) {
            $res = [];
            foreach ((array) $ids as $segment_id) {
                if (!isset($segment_rows[$segment_id])) {
                    continue;
                }
                $category_id = (int) ifset($segment_rows[$segment_id], 'category_id', 0);
                if ($category_id > 0) {
                    $res[] = $category_id;
                }
            }
            return array_values(array_unique($res));
        };

        $include_any = $to_category_ids($segment_set['include_any']);
        $require_all = $to_category_ids($segment_set['require_all']);
        $exclude_any = $to_category_ids($segment_set['exclude_any']);

        // Deterministic conflict handling: exclusion has priority.
        if ($exclude_any) {
            $include_any = array_values(array_diff($include_any, $exclude_any));
            $require_all = array_values(array_diff($require_all, $exclude_any));
        }

        if ($include_any) {
            $include_ids = join(',', $include_any);
            $this->addWhere("EXISTS (SELECT 1 FROM wa_contact_categories wcc_any WHERE wcc_any.contact_id = c.id AND wcc_any.category_id IN ({$include_ids}))");
        }

        if ($require_all) {
            $require_ids = join(',', $require_all);
            $required_count = count($require_all);
            $this->addWhere("c.id IN (
                SELECT wcc_all.contact_id
                FROM wa_contact_categories wcc_all
                WHERE wcc_all.category_id IN ({$require_ids})
                GROUP BY wcc_all.contact_id
                HAVING COUNT(DISTINCT wcc_all.category_id) = {$required_count}
            )");
        }

        if ($exclude_any) {
            $exclude_ids = join(',', $exclude_any);
            // NOT EXISTS preserves uncategorized contacts.
            $this->addWhere("NOT EXISTS (SELECT 1 FROM wa_contact_categories wcc_ex WHERE wcc_ex.contact_id = c.id AND wcc_ex.category_id IN ({$exclude_ids}))");
        }
    }

    /**
     * Filter by dynamic segments (crm_segment.type = search) using each segment's saved hash.
     * Semantics mirror applyCategorySetFilter: include_any = OR, require_all = AND of memberships, exclude_any = must match none.
     *
     * @param array $segment_set keys: include_any, require_all, exclude_any — lists of positive segment ids
     */
    public function applySearchSegmentSetFilter(array $segment_set)
    {
        $segment_ids = array_values(array_unique(array_merge(
            (array) ifset($segment_set, 'include_any', []),
            (array) ifset($segment_set, 'require_all', []),
            (array) ifset($segment_set, 'exclude_any', [])
        )));
        $segment_ids = array_values(array_filter($segment_ids, function ($id) {
            return (int) $id > 0;
        }));
        if (!$segment_ids) {
            return;
        }

        $sm = $this->getSegmentModel();
        $user = wa()->getUser();
        $contact_ids = [$user->getId()];
        if ($user->isAdmin()) {
            $contact_ids[] = 0;
        }

        $segment_rows = $sm->select('id, hash')
            ->where('type = ?', crmSegmentModel::TYPE_SEARCH)
            ->where('archived = 0')
            ->where('(contact_id IN (:cids) OR shared > 0)', ['cids' => $contact_ids])
            ->where('id IN (:ids)', ['ids' => $segment_ids])
            ->fetchAll('id');

        if (!$segment_rows) {
            $this->addWhere(0);
            return;
        }

        $to_hashes = function ($ids) use ($segment_rows) {
            $res = [];
            foreach ((array) $ids as $segment_id) {
                if (!isset($segment_rows[$segment_id])) {
                    continue;
                }
                $hash = trim((string) ifset($segment_rows[$segment_id], 'hash', ''));
                if ($hash !== '') {
                    $res[(int) $segment_id] = $hash;
                }
            }
            return $res;
        };

        $include_any = $to_hashes((array) ifset($segment_set, 'include_any', []));
        $require_all = $to_hashes((array) ifset($segment_set, 'require_all', []));
        $exclude_any = $to_hashes((array) ifset($segment_set, 'exclude_any', []));

        foreach (array_keys($exclude_any) as $ex_id) {
            unset($include_any[$ex_id], $require_all[$ex_id]);
        }

        $build_in = function (array $id_to_hash) {
            $parts = [];
            foreach ($id_to_hash as $hash) {
                $sub = $this->buildSearchSegmentContactIdSubquerySql($hash);
                if ($sub !== '') {
                    $parts[] = "c.id IN ({$sub})";
                }
            }
            return $parts;
        };

        $requested_include = self::dropNotPositiveInts((array) ifset($segment_set, 'include_any', []));
        if ($requested_include && !$include_any) {
            $this->addWhere(0);
            return;
        }
        $requested_require = self::dropNotPositiveInts((array) ifset($segment_set, 'require_all', []));
        if ($requested_require && count($require_all) < count(array_unique($requested_require))) {
            $this->addWhere(0);
            return;
        }
        $requested_exclude = self::dropNotPositiveInts((array) ifset($segment_set, 'exclude_any', []));
        if ($requested_exclude && count($exclude_any) < count(array_unique($requested_exclude))) {
            $this->addWhere(0);
            return;
        }

        $include_parts = $build_in($include_any);
        if ($include_parts) {
            $this->addWhere('(' . implode(' OR ', $include_parts) . ')');
        }

        foreach ($require_all as $hash) {
            $sub = $this->buildSearchSegmentContactIdSubquerySql($hash);
            if ($sub !== '') {
                $this->addWhere("c.id IN ({$sub})");
            } else {
                $this->addWhere(0);
                return;
            }
        }

        foreach ($exclude_any as $hash) {
            $sub = $this->buildSearchSegmentContactIdSubquerySql($hash);
            if ($sub !== '') {
                $this->addWhere("NOT (c.id IN ({$sub}))");
            }
        }
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    protected static function dropNotPositiveInts(array $ids)
    {
        $res = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $res[] = $id;
            }
        }
        return $res;
    }

    /**
     * @param string $hash
     * @return string SQL subquery selecting contact ids (no outer parens), or empty string if nothing matches
     */
    protected function buildSearchSegmentContactIdSubquerySql($hash)
    {
        $hash = trim((string) $hash);
        if ($hash === '') {
            return '';
        }

        $nested = new crmContactsCollection($hash, $this->options);
        $nested->prepare(false, false);
        $from_sql = $nested->getSQL();
        if ($from_sql === '') {
            return '';
        }

        return 'SELECT DISTINCT c.id ' . $from_sql;
    }

    public function getContacts($fields = null, $offset = 0, $limit = 50)
    {
        if ($fields === null) {
            $fields = wa('crm')->getConfig()->getContactFields();
        }

        $fields_ar = array();
        foreach (explode(',', $fields) as $field) {
            $field = trim($field);
            if ($field) {
                if ($field === 'birthday') {
                    $fields .= ',birth_day,birth_month,birth_year';
                } elseif ($field === 'is_editable') {
                    $fields .= ',crm_vault_id';
                }
                $fields_ar[] = $field;
            }
        }
        $contacts = parent::getContacts($fields, $offset, $limit);

        return $this->workupContacts($contacts, $fields_ar);
    }

    /**
     * Extract formatted contact names from list of contacts
     * @param array $contacts
     * @return array
     */
    public static function extractNames($contacts)
    {
        $names = array();
        foreach ($contacts as $index => $contact) {
            $names[$index] = waContactNameField::formatName($contact);
        }
        return $names;
    }

    protected function workupContacts($contacts, $fields = array())
    {
        if (!$contacts || !$fields) {
            return $contacts;
        }

        $fields = array_fill_keys($fields, 1);
        $contact_ids = array_keys($contacts);

        if (isset($fields['tags'])) {
            $tm = new crmTagModel();
            $tags = $tm->getByContact($contact_ids);
            foreach ($contacts as &$contact) {
                $contact['tags'] = (array) ifset($tags[$contact['id']]);
            }
            unset($contact);
        }
        if (isset($fields['birthday'])) {
            foreach ($contacts as &$contact) {
                $contact['birthday'] = [
                    'data' => [
                        'year'  => ifset($contact, 'birth_year', null),
                        'month' => ifset($contact, 'birth_month', null),
                        'day'   => ifset($contact, 'birth_day', null)
                    ]
                ];
            }
            unset($contact);
        }
        if (isset($fields['is_editable'])) {
            $rights = $this->getCrmRights();
            $editable_contact_ids = array_column($rights->dropUnallowedContacts($contacts, 'edit'), 'id');
            foreach ($contacts as &$contact) {
                $contact['is_editable'] = in_array($contact['id'], $editable_contact_ids);
            }
            unset($contact);
        }

        if (!empty($this->options['full_email_info']) && (isset($fields['email']) || isset($fields['*']))) {
            $cem = new waContactEmailsModel();
            $emails = $cem->select('*')->where('contact_id IN (:ids)', array('ids' => $contact_ids))->fetchAll();
            if ($emails) {
                foreach ($emails as $email) {
                    $contact_id = $email['contact_id'];
                    $sort = $email['sort'];
                    unset($email['contact_id'], $email['sort'], $email['id']);
                    $contacts[$contact_id]['email'] = (array)ifset($contacts[$contact_id]['email']);
                    $contacts[$contact_id]['email'][$sort] = $email;
                }
                $contacts[$contact_id]['email'] = array_values($contacts[$contact_id]['email']);
            }
        }

        return $contacts;
    }

    /**
     * @return crmSegmentModel
     */
    protected function getSegmentModel()
    {
        return !empty($this->models['sm']) ? $this->models['sm'] : ($this->models['sm'] = new crmSegmentModel());
    }

    /**
     * @return crmSegmentCountModel
     */
    protected function getSegmentCountModel()
    {
        return !empty($this->models['scm']) ? $this->models['scm'] : ($this->models['scm'] = new crmSegmentCountModel());
    }

    /**
     * @param $condition
     * @param bool $or
     * @return $this
     */
    public function addWhere($condition, $or = false)
    {
        // reset count, because we have new condition
        $this->count = null;
        return parent::addWhere($condition, $or);
    }

    public function orderBy($field, $order = 'ASC')
    {
        if ($field === 'crm_last_log_datetime') {
            if (strtolower(trim($order)) !== 'asc') {
                $order = 'DESC';
            }
            $this->order_by = 'IFNULL(c.crm_last_log_datetime, c.create_datetime) ' . $order;
            return $this->order_by;
        }

        if ($field === 'name') {
            if (strtolower(trim($order)) !== 'asc') {
                $order = 'DESC';
            }
            $this->order_by = 'IFNULL(NULLIF(c.lastname, \'\'), c.name) ' . $order . ', c.firstname ' . $order . ', c.middlename ' . $order;
            return $this->order_by;
        }
        
        if (!$field || $field == '~data' || $field[0] != '~' || false === strpos($field, '.')) {
            return parent::orderBy($field, $order);
        }

        $field = str_replace('`', '', substr($field, 1));
        $field = explode('.', $field, 2);
        $field = "`{$field[0]}`.`{$field[1]}`";

        if (strtolower(trim($order)) == 'desc') {
            $order = 'DESC';
        } else {
            $order = 'ASC';
        }

        $this->order_by = $field." ".$order;
        return $this->order_by;
    }

    protected function getTableAlias($table)
    {
        $alias = parent::getTableAlias($table);
        $alias = trim($alias, '(');

        return $alias;
    }

    public function setHash($hash)
    {
        parent::setHash($hash);
        if (is_string($hash) && strpos($hash, 'contact_info.phone') !== false) {
            $phone_prefix = wa('crm')->getConfig()->getPhoneTransformPrefix();
            if (!empty($phone_prefix['input_code']) && !empty($phone_prefix['output_code'])) {
                $explode_hash = explode('&', $this->hash[1]);
                foreach ($explode_hash as &$_hash) {
                    if (strpos($_hash, 'contact_info.phone') === 0) {
                        $h = explode('=', $_hash);
                        $h[1] = ltrim($h[1], $phone_prefix['input_code']);
                        $h[1] = ltrim($h[1], '+'.$phone_prefix['output_code']);
                        $_hash = $h[0].'='.$h[1];
                        $this->hash[1] = implode('&', $explode_hash);
                        break;
                    }
                }
            }
        }
    }

    public function getCrmRights()
    {
        return $this->crm_rights ? $this->crm_rights : ($this->crm_rights = new crmRights());
    }
}
