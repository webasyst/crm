<?php

class crmConversationModel extends crmModel
{
    protected $table = 'crm_conversation';

    const TYPE_EMAIL = 'EMAIL';
    const TYPE_IM = 'IM';

    public static function isColumnMb4($column)
    {
        return crmModel::isTableColumnMb4('crm_conversation', $column);
    }

    public function add($data, $type)
    {
        $data['type'] = $type === self::TYPE_EMAIL ? self::TYPE_EMAIL : self::TYPE_IM;

        $data['create_datetime'] = date('Y-m-d H:i:s');
        $data['update_datetime'] = $data['create_datetime'];

        if (!array_key_exists('user_contact_id', $data)) {
            $data['user_contact_id'] = wa()->getUser()->getId();
        } else {
            $data['user_contact_id'] = (int)$data['user_contact_id'];
        }

        if (!array_key_exists('contact_id', $data)) {
            $data['contact_id'] = wa()->getUser()->getId();;
        } else {
            $data['contact_id'] = (int)$data['contact_id'];
        }
        $data['contact_id'] = (int)$data['contact_id'];

        $data['source_id'] = (int)ifset($data['source_id']);

        $summary = (string)ifset($data['summary']);
        $data['summary'] = strlen($summary) > 0 ? $summary : null;

        return $this->insert($data);
    }

    public function update($id, $data)
    {
        $update = array();
        $allowed = array('summary', 'last_message_id', 'is_closed', 'count');
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (array_key_exists('last_message_id', $update) && !array_key_exists('update_datetime', $update)) {
            $update['update_datetime'] = date('Y-m-d H:i:s');
        }
        if (!$update) {
            return;
        }
        $this->updateById($id, $update);
    }

    public function delete($id)
    {
        $ids = crmHelper::toIntArray($id);
        $ids = crmHelper::dropNotPositive($ids);
        if (!$ids) {
            return;
        }

        $mm = new crmMessageModel();
        $message_ids = $mm->select('id')->where('conversation_id IN(:ids)', array(
            'ids' => $ids
        ))->fetchAll(null, true);

        if ($message_ids) {
            $mm->delete($message_ids);
        }

        $this->deleteById($ids);
    }

    /**
     * Magic for increment and decrement count is here
     * @param $field
     * @param $value
     * @return int|mixed|string
     * @throws waException
     */
    protected function getFieldValue($field, $value)
    {
        if ($field == 'count' && is_string($value) && ($value[0] == '+' || $value[0] == '-')) {
            $op = $value[0];
            $value = substr($value, 1);
            $value = (int)$value;
            if ($value > 0) {
                return $this->escapeField($field).' '.$op.' '.$value;
            } else {
                return parent::getFieldValue($field, $value);
            }
        }
        return parent::getFieldValue($field, $value);
    }

    public function incCount($id)
    {
        $this->update($id, array('count' => '+1'));
    }

    public function decCount($id)
    {
        $this->update($id, array('count' => '-1'));
    }

    public function getConversation($id)
    {
        return $this->getById($id);
    }

    public function getList($params, &$total_count = null)
    {
        // LIMIT
        if (isset($params['offset']) || isset($params['limit'])) {
            $offset = (int)ifset($params['offset'], 0);
            $limit = (int)ifset($params['limit'], 50);
            if (!$limit) {
                return array();
            }
        } else {
            $offset = $limit = null;
        }

        $fields = array();
        if (isset($params['fields']) && !empty($params['fields'])) {
            $fields = explode(',', $params['fields']);
            foreach ($fields as &$field) {
                $field = "c." . trim($field);
            }
            unset($field);
        }
        if (empty($fields)) {
            $fields = array("c.*");
        }

        $condition = array();
        $bind_params = array();

        if (isset($params['id'])) {
            $ids = crmHelper::toIntArray($params['id']);
            $ids = crmHelper::dropNotPositive($ids);
            $ids = array_unique($ids);
            if ($ids) {
                $cond[] = "c.id IN(:ids)";
                $bind_params['ids'] = $ids;
            }
        }

        if (!empty($params['transport']) && $params['transport'] != 'all') {
            $condition[] = "c.type='".strtoupper($this->escape($params['transport']))."'";
        }

        if (!empty($params['source_id']) && wa_is_int($params['source_id']) && $params['source_id'] > 0) {
            $condition[] = "c.source_id=".$params['source_id'];
        }

        // responsible filter
        if (isset($params['responsible'])) {    // response might be 0, so not check by !empty
            $responsible_contact_id = (int)$params['responsible'];
            $condition[] = "c.user_contact_id={$responsible_contact_id}";
        }
        // client filter
        if (isset($params['contact_id'])) {
            $condition[] = 'c.contact_id='.(int) $params['contact_id'];
        }
        // deal filter
        if (isset($params['deal_id'])) {
            $condition[] = 'c.deal_id='.abs($params['deal_id']);
        }

        // need check rights
        $check_rights = !empty($params['check_rights']);

        $rights_filter = null;
        if ($check_rights) {
            $rights_filter = self::buildSQLFilterByAccessRights();
        }

        $fields = join(",", $fields);

        // Count rows setting
        if (!isset($params['count_results']) && func_num_args() > 1) {
            $params['count_results'] = true;
        }
        if (empty($params['count_results'])) {
            $select = "SELECT {$fields}";
        } else {
            $select = "SELECT SQL_CALC_FOUND_ROWS {$fields}";
        }

        $join = "";
        if ($rights_filter) {
            $join = ifset($rights_filter, "join", "");
            $rights_filter_where = ifset($rights_filter, "where", "");
            if ($rights_filter_where) {
                $condition[] = "(" . $rights_filter_where . ")";
            } else {
                $condition = array(0);
            }
        }

        if ($condition) {
            $condition = "WHERE " . join(' AND ', $condition);
        } else {
            $condition = '';
        }

        $sql = "{$select}
            FROM {$this->table} AS c
            {$join}
            {$condition}
            ORDER BY update_datetime DESC";
        if ($limit) {
            $sql .= " LIMIT $offset, $limit";
        }

        $db_result = $this->query($sql, $bind_params);
        $total_count = $this->query('SELECT FOUND_ROWS()')->fetchField();
        $list = $db_result->fetchAll('id');

        if ($fields == "c.*") {
            $list = $this->workupList($list);
        }

        return $list;
    }

    /**
     * Build sql statements to conversation list so result sql could be filtered by access rights
     *
     * @param array $options
     *   array $options['aliases'] - map of table aliases, possible tables in result statements: crm_conversation, crm_deal_participants, crm_deal
     *
     * @return null|array $result - NULL (if filter by rights no need) OR array of certain format:
     *   string $result['join']  - join statement
     *   string $result['where'] - where statement
     */
    public static function buildSQLFilterByAccessRights($options = array())
    {
        $crm_rights = new crmRights();
        $conversation_access = $crm_rights->getConversationsRights();

        if ($conversation_access >= crmRightConfig::RIGHT_CONVERSATION_ALL) {
            return null;
        }

        $options = is_array($options) ? $options : array();

        $aliases = (array)ifset($options["aliases"]);
        if (!isset($aliases["crm_conversation"])) {
            $aliases["crm_conversation"] = "c";
        }
        if (!isset($aliases["crm_deal_participants"])) {
            $aliases["crm_deal_participants"] = "p";
        }
        if (!isset($aliases["crm_deal"])) {
            $aliases["crm_deal"] = "d";
        }

        // shortcuts for clean and clarify sql code
        $c = $aliases["crm_conversation"];
        $p = $aliases["crm_deal_participants"];
        $d = $aliases["crm_deal"];

        $current_user_id = wa()->getUser()->getId();

        // sql condition statement
        $condition = array();

        // sql join statements
        $joins = array();

        // condition by deal (where user is USER participant in deal)
        $joins[] = "LEFT JOIN `crm_deal` {$d} ON {$d}.id = {$c}.deal_id";
        $joins[] = "LEFT JOIN `crm_deal_participants` {$p} ON {$p}.deal_id={$d}.id AND {$p}.contact_id={$current_user_id} AND {$p}.role_id='USER'";
        $condition[] = "( {$d}.id IS NOT NULL AND {$p}.contact_id IS NOT NULL )";

        // condition by user_contact_id
        if ($conversation_access >= crmRightConfig::RIGHT_CONVERSATION_OWN_OR_FREE) {
            $condition[] = "( {$c}.user_contact_id IN({$current_user_id}, 0) )";
        } else {
            $condition[] = "( {$c}.user_contact_id = {$current_user_id} )";
        }

        $condition = join(' OR ', $condition);

        return array(
            'join' => join(" ", $joins),
            'where' => $condition
        );
    }

    protected function workupList($list)
    {
        if (!$list) {
            return array();
        }

        $last_message_ids = array();

        foreach ($list as &$r) {
            if ($r['last_message_id']) {
                $last_message_ids[$r['last_message_id']] = intval($r['last_message_id']);
            }
        }
        unset($r);

        $read_ids = array();
        if ($last_message_ids) {
            $mrm = new crmMessageReadModel();
            $read_ids = $mrm->select('message_id')->where(
                'message_id IN('.join(',', $last_message_ids).') AND contact_id='.intval(wa()->getUser()->getId())
            )->fetchAll('message_id', true);
        }

        foreach ($list as &$l) {
            if ($l['last_message_id']) {
                $l['read'] = array_key_exists($l['last_message_id'], $read_ids);
            } else {
                $l['read'] = true;
            }
        }
        unset($l);

        $source_ids = array();
        foreach ($list as &$item) {
            $item['icon_url'] = null;
            $item['icon'] = null;
            $item['icon_fa'] = null;
            $item['icon_color'] = '#BB64FF';
            $item['transport_name'] = _w('Unknown');

            if ($item['source_id'] > 0) {
                $source_ids[] = $item['source_id'];
            }
            if ($item['type'] == crmMessageModel::TRANSPORT_EMAIL) {
                $item['icon'] = 'email';
                $item['icon_fa'] = 'envelope';
                $item['transport_name'] = 'Email';
            } elseif ($item['type'] == crmMessageModel::TRANSPORT_SMS) {
                $item['icon'] = 'mobile';
                $item['icon_fa'] = 'mobile';
                $item['transport_name'] = 'SMS';
            }
        }
        unset($item);

        $source_ids = array_unique($source_ids);
        $sources = (new crmSourceModel)->getByField([ 'id' => $source_ids ], 'id');
        $source_helpers = [];
        foreach ($source_ids as $source_id) {
            $source_helpers[$source_id] = crmSourceHelper::factory(crmSource::factory(ifset($sources[$source_id], $source_id)));
        }

        foreach ($list as &$item) {
            if ($item['source_id'] <= 0) {
                continue;
            }
            /**
             * @var crmSourceHelper $source_helper
             */
            $source_helper = $source_helpers[$item['source_id']];
            $res = $source_helper->workupConversationInList($item);
            $item = $res ? $res : $item;
        }
        unset($item);

        foreach ($list as &$item) {
            if (empty($item['icon_url']) && empty($item['icon'])) {
                $item['icon'] = 'exclamation';
            }
            if (empty($item['icon_url']) && empty($item['icon_fa'])) {
                $item['icon_fa'] = 'exclamation-circle';
            }
        }
        unset($item);

        return $list;
    }

    public function getResponsibleUserOfGroup($group_id, $type = self::TYPE_IM)
    {
        // Let's find such a contact, which is now online
        // and has the least number of open conversations
        $sql = "SELECT wug.contact_id
                  FROM wa_user_groups wug
                  JOIN wa_contact c
                    ON c.id = wug.contact_id
                      AND c.last_datetime IS NOT NULL
                      AND UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(c.last_datetime) < '". waUser::getOption('online_timeout') ."'
                      AND c.is_user != -1
                  JOIN `wa_login_log` wll
                    ON wll.contact_id = wug.contact_id
                      AND wll.datetime_out IS NULL
                  LEFT JOIN `{$this->table}` cc ON cc.user_contact_id = wug.contact_id AND cc.type = :type AND cc.is_closed = 0
                WHERE wug.group_id = :group_id
                GROUP BY wug.contact_id
                ORDER BY COUNT(cc.id)
                LIMIT 1";
        $user_contact_id = (int)$this->query($sql, array('group_id' => $group_id, 'type' => $type))->fetchField();
        if ($user_contact_id > 0) {
            return $user_contact_id;
        }

        // Well, or just look for a contact from this group
        // with the least number of open conversations
        $sql = "SELECT wug.contact_id
                  FROM wa_user_groups wug
                  JOIN wa_contact c ON c.id = wug.contact_id AND c.is_user != -1
                  LEFT JOIN `{$this->table}` cc ON cc.user_contact_id = wug.contact_id AND cc.type = :type AND cc.is_closed = 0
                WHERE wug.group_id = :group_id
                GROUP BY wug.contact_id
                ORDER BY COUNT(cc.id)
                LIMIT 1";
        $user_contact_id = (int)$this->query($sql, array('group_id' => $group_id, 'type' => $type))->fetchField();

        return $user_contact_id > 0 ? $user_contact_id : null;
    }

    public function deleteByDeal($id)
    {
        $ids = crmHelper::toIntArray($id);
        $ids = crmHelper::dropNotPositive($ids);
        if (!$ids) {
            return;
        }
        $ids = $this->select('id')
                    ->where('deal_id IN (:ids)', array('ids' => $ids))
                    ->fetchAll(null, true);
        $this->delete($ids);
    }

    /**
     * Get list of ID of last messages for conversations
     * @param int[] $conversation_ids
     * @return array[int]int conversation_id => message_id
     * @throws waException
     */
    public function getLastMessageIds($conversation_ids)
    {
        $conversation_ids = waUtils::toIntArray($conversation_ids);
        $conversation_ids = waUtils::dropNotPositive($conversation_ids);
        if (!$conversation_ids) {
            return array();
        }

        $ids = $this->select('last_message_id')
                        ->where('id IN(:ids) AND last_message_id IS NOT NULL', [
                            'ids' => $conversation_ids
                        ])
                        ->fetchAll(null, true);
        if (!$ids) {
            return array();
        }

        $ids = waUtils::toIntArray($ids);
        $ids = waUtils::dropNotPositive($ids);

        return $ids;
    }

    /**
     * Repair last_message_ids of conversations
     * @param null|int|int[] $conversation_ids - If NULL update repair all broken last_message_ids, otherwise only for these conversations
     */
    public function repairLastMessageIds($conversation_ids = null)
    {
        // prepare where and bind params for SQL

        $filter = $this->getFilterByIds($conversation_ids);
        $where = $filter['filter'];
        $bind_params = $filter['bind_params'];
        if ($where) {
            $where = 'WHERE ' . $where;
        }

        // INNER SELECT: for each conversation get computed last message id (MAX by m.id) and current last_message_id
        $inner_select_sql = "
                SELECT c.id, MAX(m.id) AS computed_last_message_id, c.last_message_id
                FROM `crm_conversation` c
                JOIN `crm_message` m ON m.conversation_id = c.id
                {$where}
                GROUP BY c.id";

        // OUTER UPDATE: if computed last message id != current last message id we need to update last_message_id
        $update_sql = "
                    UPDATE `crm_conversation` c
                    JOIN ( {$inner_select_sql} ) r ON c.id = r.id 
                        SET c.last_message_id = r.computed_last_message_id
                    WHERE r.computed_last_message_id != r.last_message_id OR r.last_message_id IS NULL";

        $this->exec($update_sql, $bind_params);
    }

    /**
     * Repair counters ('count' field) in conversation records in DB
     * @param null|int|int[] $conversation_ids - If NULL repair all broken counters, otherwise only for these conversations
     */
    public function repairCounters($conversation_ids = null)
    {
        // prepare where and bind params for SQL

        $filter = $this->getFilterByIds($conversation_ids);
        $where = $filter['filter'];
        $bind_params = $filter['bind_params'];
        if ($where) {
            $where = 'WHERE ' . $where;
        }

        // INNER SELECT: for each conversation calculate count
        $inner_select_sql = "
                SELECT c.id, IF(m.id IS NULL, 0, COUNT(*)) AS computed_count, c.count
                FROM `crm_conversation` c
                LEFT JOIN `crm_message` m ON m.conversation_id = c.id
                {$where}
                GROUP BY c.id";

        // OUTER UPDATE: if computed count != current count we need to update count
        $update_sql = "
                    UPDATE `crm_conversation` c
                    JOIN ( {$inner_select_sql} ) r ON c.id = r.id 
                        SET c.count = r.computed_count
                    WHERE r.computed_count != r.count";

        $this->exec($update_sql, $bind_params);
    }

    /**
     * Find empty conversations and delete it
     * @param null $conversation_ids
     */
    public function deleteEmptyConversations($conversation_ids = null)
    {
        $this->repairCounters($conversation_ids);

        if ($conversation_ids !== null) {
            $this->deleteByField(['id' => $conversation_ids, 'count' => 0]);
        } else {
            $this->deleteByField(['count' => 0]);
        }
    }

    /**
     * Repair last_message_ids of conversations
     * @param null|int|int[] $ids - If NULL will be not filter (empty $result['filter'])
     * @return array $result
     *      string $result['filter'] - sql condition statement
     *      array  $result['bind_params'] - sql bind params to that condition
     */
    protected function getFilterByIds($ids = null)
    {
        $filter = '';
        $bind_params = [];

        if ($ids !== null) {
            $ids = waUtils::toIntArray($ids);
            $ids = waUtils::dropNotPositive($ids);
            if (!$ids) {
                return;
            }
            $filter = 'c.id IN (:ids)';
            $bind_params['ids'] = $ids;
        }

        return [
            'filter' => $filter,
            'bind_params' => $bind_params
        ];
    }

}
