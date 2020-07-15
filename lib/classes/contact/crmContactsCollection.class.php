<?php

class crmContactsCollection extends waContactsCollection
{
    /**
     * @var waModel[]
     */
    protected $models;

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
            $rights = new crmRights();
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

    public function getContacts($fields = null, $offset = 0, $limit = 50)
    {
        if ($fields === null) {
            $fields = wa('crm')->getConfig()->getContactFields();
        }

        $contacts = parent::getContacts($fields, $offset, $limit);
        $fields_ar = array();
        foreach (explode(',', $fields) as $field) {
            $field = trim($field);
            if ($field) {
                $fields_ar[] = $field;
            }
        }
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

        if (!empty($this->options['full_email_info'])) {
            $cem = new waContactEmailsModel();
            $emails = $cem->select('*')->where('contact_id IN (:ids)', array('ids' => $contact_ids))->fetchAll();
            foreach ($emails as $email) {
                $contact_id = $email['contact_id'];
                $sort = $email['sort'];
                unset($email['contact_id'], $email['sort'], $email['id']);
                $contacts[$contact_id]['email'] = (array)ifset($contacts[$contact_id]['email']);
                $contacts[$contact_id]['email'][$sort] = $email;
            }
            $contacts[$contact_id]['email'] = array_values($contacts[$contact_id]['email']);
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
    }
}
