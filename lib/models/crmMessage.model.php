<?php

class crmMessageModel extends crmModel
{
    protected $table = 'crm_message';
    protected $deal_table = 'crm_deal';

    const TRANSPORT_EMAIL = 'EMAIL';
    const TRANSPORT_SMS = 'SMS';
    const TRANSPORT_IM = 'IM';
    const DIRECTION_IN = 'IN';
    const DIRECTION_OUT = 'OUT';
    const LOG_ACTION_SENT = 'message_sent';

    public static function isColumnMb4($column)
    {
        return crmModel::isTableColumnMb4('crm_message', $column);
    }

    /**
     * @param array $data Fields of table
     *
     *   array(
     *       'id' => int,
     *       ...
     *   )
     * @return bool|int|resource
     * @throws waDbException
     * @throws waException
     */
    public function add($data)
    {
        $data = $this->prepareDataBeforeInsert($data);
        return $this->insertData($data);
    }

    /**
     * Insert data and trigger event
     * Data is as it, not prepared
     * You must prepare it before by calling $this->prepareDataBeforeInsert()
     *
     * @param array $data
     *  Could has 'params' key
     *
     * @return bool|int|resource
     * @throws waDbException
     * @throws waException
     */
    protected function insertData($data)
    {
        $id = $this->insert($data);
        if ($id <= 0) {
            return false;
        }

        if (isset($data['params'])) {
            $this->getMessageParamsModel()->set($id, $data['params']);
        }

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'message_ts', time());

        /**
         * Event after message has created
         * @event message_create
         * @param array $params
         * @param array[]int $params['id'] ID of just created message
         */
        $event_params = array('id' => $id);
        wa('crm')->event('message_create', $event_params);

        return $id;
    }

    /**
     * @param array $data
     * @return array
     * @throws waException
     */
    protected function prepareDataBeforeInsert($data)
    {
        $data['create_datetime'] = date('Y-m-d H:i:s');

        if (!isset($data['creator_contact_id'])) {
            $data['creator_contact_id'] = wa()->getUser()->getId();
        }
        $data['creator_contact_id'] = (int) $data['creator_contact_id'];

        $transport = (string) ifset($data['transport']);
        if (!in_array('TRANSPORT_'.$transport, $this->getTransports())) {
            $transport = self::TRANSPORT_EMAIL;
        }
        $data['transport'] = $transport;

        $direction = (string) ifset($data['direction']);
        if (!in_array('DIRECTION_'.$direction, $this->getDirections())) {
            $direction = self::DIRECTION_IN;
        }
        $data['direction'] = $direction;

        if ($data['transport'] == self::TRANSPORT_EMAIL) {
            $data['to'] = (string)$this->typecastFromOrToField(ifset($data['to']));
        }

        $data['original'] = ifset($data['original']) ? 1 : 0;
        $data['deal_id'] = isset($data['deal_id']) && $data['deal_id'] > 0 ? $data['deal_id'] : null;

        $from = null;
        if (isset($data['from'])) {
            if ($data['transport'] == self::TRANSPORT_EMAIL) {
                $from = $this->typecastFromOrToField($data['from']);
            } else {
                $from = $data['from'];
            }
        }
        $data['from'] = $from;

        if (isset($data['params'])) {
            $data['params'] = (array)$data['params'];
        }

        return $data;
    }

    protected function typecastFromOrToField($field)
    {
        $email_candidates = array();
        if (is_string($field)) {
            $email_candidates[] = $field;
        }
        if (is_array($field) && !empty($field)) {
            $email_candidates[] = key($field);
            $email_candidates[] = reset($field);
            $email_candidates[] = ifset($field[0]);
            $email_candidates[] = ifset($field[1]);
        }

        $valid_email = null;
        foreach ($email_candidates as $from_email) {
            if (is_string($from_email) && strlen($from_email) > 0) {
                $email_validator = new waEmailValidator();
                if ($email_validator->isValid($from_email)) {
                    $valid_email = $from_email;
                    break;
                }
            }
        }

        return $valid_email;
    }

    /**
     * @param int | array[]int $id
     * @throws waDbException
     * @throws waException
     */
    public function delete($id)
    {
        $ids = crmHelper::toIntArray($id);
        $ids = crmHelper::dropNotPositive($ids);
        if (!$ids) {
            return;
        }

        $conversation_ids = $this->getMessageConversationIds($ids);

        /**
         * Triggered on message deletion.
         * @event message_delete
         * @param array $params
         * @param array[]int $params['ids'] IDs of messages
         */
        $event_params = array('ids' => $ids);
        wa('crm')->event('message_delete', $event_params);

        $this->getMessageAttachmentsModel()->deleteByField('message_id', $ids);
        $this->getMessageRecipientsModel()->deleteByField('message_id', $ids);
        $this->getMessageReadModel()->deleteByField('message_id', $ids);
        $this->getMessageParamsModel()->delete($ids);
        $this->getLogModel()->deleteByField(array('object_type' => crmLogModel::OBJECT_TYPE_MESSAGE, 'object_id' => $ids));

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'message_ts', time());

        $this->deleteById($ids);

        // repair possible broken last_message_id after messages deleting
        $this->getConversationModel()->repairLastMessageIds($conversation_ids);

        // delete empty conversations after message deleting
        $this->getConversationModel()->deleteEmptyConversations($conversation_ids);

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
     * @param int $message_id
     * @param array[]int | array[]array('id' => int) $files
     */
    public function setAttachments($message_id, $files)
    {
        $file_ids = array();
        foreach ($files as $file) {
            if (wa_is_int($file)) {
                $file_ids[] = (int) $file;
            } elseif (is_array($file)) {
                $file_ids[] = (int) ifset($file['id']);
            }
        }
        crmHelper::dropNotPositive($file_ids);

        $mam = $this->getMessageAttachmentsModel();

        $existed_map = $mam->getByField('message_id', $message_id, 'file_id');

        $attachments = array();
        foreach ($file_ids as $file_id) {
            if (!isset($existed_map[$file_id])) {
                $attachments[] = array('message_id' => $message_id, 'file_id' => $file_id);
            }
        }

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'message_ts', time());

        $mam->multipleInsert($attachments);
    }

    public function deleteAttachments($message_id)
    {
        $mam = $this->getMessageAttachmentsModel();
        $attachments = $mam->getByField('message_id', $message_id, 'file_id');
        if (!empty($attachments)) {
            $mam->deleteByField('message_id', $message_id);
            $this->getFileModel()->delete(array_keys($attachments));
        }
    }

    /**
     * @param int $message_id
     * @param string $file_path
     */
    public function saveEmailSource($message_id, $file_path)
    {
        if (strlen($file_path) > 0 && file_exists($file_path)) {
            try {
                waFiles::copy($file_path, $this->getEmailSourceFilePath($message_id));
            } catch (Exception $e) {

            }
        }
    }

    /**
     * @param int $message_id
     * @param array $recipient [email, name, contact_id]
     * @param $default_type $type self::TYPE_*
     */
    public function setEmailRecipient($message_id, $recipient, $default_type = crmMessageRecipientsModel::TYPE_CC)
    {
        $this->getMessageRecipientsModel()->setEmailRecipient($message_id, $recipient, $default_type);
    }

    /**
     * @param int $message_id
     * @param array $recipients [ [email, name, contact_id]* ]
     * @param $default_type $type self::TYPE_*
     */
    public function setEmailRecipients($message_id, $recipients, $default_type = crmMessageRecipientsModel::TYPE_CC)
    {
        $this->getMessageRecipientsModel()->setEmailRecipients($message_id, $recipients, $default_type);
    }

    /**
     * @param int $message_id
     * @param array $recipient [destination, name, contact_id]
     * @param $default_type $type self::TYPE_*
     */
    public function setRecipient($message_id, $recipient, $default_type = crmMessageRecipientsModel::TYPE_CC)
    {
        $this->getMessageRecipientsModel()->setRecipient($message_id, $recipient, $default_type);
    }

    /**
     * @param int $message_id
     * @param array $recipients [ [destination, name, contact_id]* ]
     * @param $default_type $type self::TYPE_*
     */
    public function setRecipients($message_id, $recipients, $default_type = crmMessageRecipientsModel::TYPE_CC)
    {
        $this->getMessageRecipientsModel()->setRecipients($message_id, $recipients, $default_type);
    }

    /**
     * @param $data
     * @param array $options
     *      array|bool $options['wa_log'] [optional]
     *          If TRUE
     *              fix also in waLog
     *          If FALSE or skipped
     *              not fix in waLog
     *          If array
     *              it will be params to fix in waLog
     * @return bool|int|resource
     * @throws waDbException
     * @throws waException
     */
    public function fix($data, $options = array())
    {
        $options = is_array($options) ? $options : array();
        $wa_log = ifset($options['wa_log']);

        $data = $this->prepareDataBeforeInsert($data);
        $id = $this->insertData($data);

        if ($id <= 0) {
            return false;
        }

        try {
            $contact_id = !empty($data['deal_id']) ? $data['deal_id'] * -1 : ifset($data['contact_id']);

            $action = self::LOG_ACTION_SENT;
            $lm = new crmLogModel();
            if (empty($data['event']) && empty($data['crm_log_id'])) {
                $log_item_id = $lm->log($action, $contact_id, $id, null, null, ifset($data['creator_contact_id']));
            } else {
                $lm->updateById(
                    $data['crm_log_id'],
                    ['params' => json_encode(['message_id' => $id])]
                );
            }

            if ($wa_log || is_array($wa_log)) {

                $log_model = new waLogModel();

                $params = array(
                    'crm_log_item_id' => $log_item_id,
                    'transport' => $data['transport'],
                    'direction' => $data['direction'],
                );

                if (!empty($data['subject'])) {
                    $params['subject'] = ifset($data['subject']);
                }

                if (is_array($wa_log)) {
                    $params = array_merge($wa_log, $params);
                }

                $log_model->add($action, $params, ifset($data['contact_id']), ifset($data['creator_contact_id']));
            }
        } catch (waException $e) {

        }
        return $id;
    }

    public function getEmailSourceFilePath($message_id)
    {
        $str = str_pad($message_id, 4, '0', STR_PAD_LEFT);
        $path = 'messages/'.substr($str, -2).'/'.substr($str, -4, 2).'/';
        return wa()->getDataPath($path, false, 'crm') . 'source.eml';
    }

    /**
     * @param int | array $id
     * @return null|array
     *   Extra fields for message in case if not NULL
     *     array $message['attachments'] Files records received from crmMessageAttachmentsModel
     *     array $message['recipients'] Array of recipients
     *     string $message['body_sanitized'] Body of message, sanitized and ready for safe rendering in browser
     *     array $message['params'] array of params (not serialized)
     */
    public function getMessage($id)
    {
        if (is_array($id)) {
            $message = $id;
        } else {
            $id = (int)$id;
            if ($id <= 0) {
                return null;
            }
            $message = $this->getById($id);
        }
        if (!$message) {
            return null;
        }

        $message['attachments'] = $this->getMessageAttachmentsModel()->getFiles($message['id']);
        $message['recipients'] = $this->getMessageRecipientsModel()->getRecipients($message['id']);

        $replace_img_src = array();
        $app_url = wa()->getAppUrl('crm');
        foreach ($message['attachments'] as $attachment) {
            $src = "{$app_url}?module=file&action=download&id={$attachment['id']}";
            $replace_img_src[$attachment['id']] = $src;
        }

        $message['params'] = $this->getMessageParamsModel()->get($message['id']);

        $message['body_sanitized'] = ifset($message['params'], 'internal', false) 
            ? $message['body'] 
            : crmHtmlSanitizer::work($message['body'], [
                'replace_img_src' => $replace_img_src, 
            ]);

        return $message;
    }

    /**
     * Получение дополнений к сообщениям и обогащение сообщений этими данными
     *
     * @param $messages
     * @return array
     * @throws waException
     */
    public function getExtMessages($messages, $source)
    {
        if (!is_array($messages) || empty($messages)) {
            return [];
        }
        $message_ids = array_column($messages, 'id');
        $message_attachments = $this->getMessageAttachmentsModel()->getFilesByMessages($message_ids);
        $message_recipients = $this->getMessageRecipientsModel()->getRecipientsByMessages($message_ids, null, 'message_id');
        $message_params = $this->getMessageParamsModel()->get($message_ids);
        $verification_key = empty($source) ? false : $source->getParam('verification_key');

        foreach ($messages as &$_message) {
            $_message['attachments'] = ifset($message_attachments, $_message['id'], []);
            $_message['recipients'] = ifset($message_recipients, $_message['id'], []);
            $replace_img_src = [];
            $app_url = wa()->getAppUrl('crm');
            foreach ($_message['attachments'] as $attachment) {
                $src = "{$app_url}?module=file&action=download&id={$attachment['id']}";
                $replace_img_src[$attachment['id']] = $src;
            }
            $_message['params'] = ifset($message_params, $_message['id'], []);
            $_message['body_sanitized'] = ifset($_message['params'], 'internal', false) 
                ? $_message['body'] 
                : crmHtmlSanitizer::work($_message['body'], [
                    'replace_img_src' => $replace_img_src, 
                    'verification_key' => $verification_key,
                ]);

        }

        return $messages;
    }

    /**
     * @param $deal
     * @return null|array
     */
    public function getMessageByDeal($deal)
    {
        if (is_array($deal) && isset($deal['id'])) {
            $deal_id = (int)$deal['id'];
        } else {
            $deal_id = (int)$deal;
        }
        if ($deal_id <= 0) {
            return null;
        }
        $message = $this->getByField('deal_id', $deal_id);
        $message['attachments'] = $this->getMessageAttachmentsModel()->getFiles($message['id']);
        $message['recipients'] = $this->getMessageRecipientsModel()->getRecipients($message['id']);
        return $message;
    }

    /**
     * @param int|array $deal_ids
     * @param bool $with_event include in the result of a message with a non-zero value of the field `event`
     * @return array
     */
    public function getMessagesByDealIds($deal_ids, $with_event = false, $order_by = 'ASC')
    {
        $deal_ids = crmHelper::toIntArray($deal_ids);
        $deal_ids = crmHelper::dropNotPositive($deal_ids);
        $deal_ids = ifempty($deal_ids, 0);

        $event = "";
        if ($with_event == false) {
            $event = 'AND event IS NULL';
        }
        $sql = "
                SELECT *
                FROM {$this->table}
                WHERE deal_id IN (:ids)
                    {$event}
                ORDER BY id {$order_by}";
        $db_result = $this->query($sql, array('ids' => $deal_ids));
        return $this->workupList($db_result->fetchAll('id'));
    }

    /**
     * @param int|array $deal_ids
     * @param bool $with_event include in the result of a message with a non-zero value of the field `event`
     * @return array
     */
    public function getMessagesByClient($contact_id, $transport, $recipient_id, $with_event = false, $order_by = 'ASC')
    {
        $contact_ids = (array)$contact_id;
        $contact_ids = crmHelper::toIntArray($contact_ids);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        $contact_ids = ifempty($contact_ids, 0);

        $condition = ($transport && $transport != 'all') ? "AND m.transport='".$this->escape(strtoupper($transport))."'" : '';

        $join = '';
        if ($recipient_id && $recipient_id != 'all') {
            $join = "INNER JOIN crm_message_recipients mr ON mr.message_id=m.id AND mr.contact_id=".intval($recipient_id);
        }

        $event = "";
        if ($with_event == false) {
            $event = 'AND m.event IS NULL';
        }
        $sql = "
                SELECT m.*
                FROM {$this->table} m
                {$join}
                WHERE contact_id IN (:ids)
                    {$condition} {$event}
                ORDER BY id {$order_by}";
        $db_result = $this->query($sql, array('ids' => $contact_ids));
        return $this->workupList($db_result->fetchAll('id'));
    }

    public function getList($params, &$total_count = null)
    {
        $params = is_array($params) ? $params : array();

        // LIMIT
        if (isset($params['offset']) || isset($params['limit'])) {
            $offset = (int) ifset($params['offset'], 0);
            $limit = (int) ifset($params['limit'], 50);
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
                $field = "m." . trim($field);
            }
            unset($field);
        }
        if (empty($fields)) {
            $fields = array("m.*");
        }

        $check_rights = isset($params['check_rights']) ? $params['check_rights'] : false;

        $cond = array();
        $bind_params = array();
        $joins = array();

        // filters by id
        if (isset($params['id'])) {
            $ids = crmHelper::toIntArray($params['id']);
            $ids = crmHelper::dropNotPositive($ids);
            $ids = array_unique($ids);
            if ($ids) {
                $cond[] = "m.id IN(:ids)";
                $bind_params['ids'] = $ids;
            }
        }

        // 
        if (isset($params['min_id']) && wa_is_int($params['min_id'])) {
            $cond[] = "m.id > :min_id";
            $bind_params['min_id'] = $params['min_id'];
        }

        // filter by rights
        $rights_filter = null;
        if ($check_rights) {
            $rights_filter = crmConversationModel::buildSQLFilterByAccessRights();

            if ($rights_filter) {
                $join = ifset($rights_filter, "join", "");
                if ($join) {
                    $joins[] = "JOIN crm_conversation c ON c.id = m.conversation_id";
                    $joins[] = $join;
                }
                $rights_filter_where = ifset($rights_filter, "where", "");
                if ($rights_filter_where) {
                    $cond[] = "(" . $rights_filter_where . ")";
                } else {
                    $cond = null;   // means not show list at all
                }
            }
        }

        // other filters
        if ($cond !== null) {
            if (empty($params['event'])) {
                $cond[] = "m.event IS NULL";
            } elseif ($params['event'] === true) {
                $cond[] = "m.event IS NOT NULL";
            }

            if (isset($params['direction'])) {
                $cond[] = "direction = '" . $this->escape($params['direction']) . "'";
            }
            if (isset($params['transport'])) {
                $cond[] = "transport = '" . $this->escape($params['transport']) . "'";
            }

            // User filter
            if (!empty($params['user'])) {
                $joins[] = "
                INNER JOIN crm_message_recipients mr
                    ON m.id = mr.message_id
                        AND mr.contact_id = ".$this->escape($params['user']);
            }

            // Responsible filter
            if (isset($params['responsible'])) {    // response might be 0, so not check by !empty
                $joins[] = "
                JOIN crm_deal d
                    ON d.id = m.deal_id
                        AND d.user_contact_id = ".$this->escape($params['responsible']);
            }

        }

        $fields = join(",", $fields);
        $order_by = "ORDER BY m.id DESC";
        $group_by = "GROUP BY m.id";

        $calc_found_rows = false;
        if (!isset($params['count_results'])) {
            $params['count_results'] = func_num_args() > 1;
        }

        if ($params['count_results'] === 'only') {
            $select = "SELECT COUNT(DISTINCT m.id)";
            $order_by = "";
            $group_by = "";
            $limit = "";
        } elseif ($params['count_results']) {
            $select = "SELECT SQL_CALC_FOUND_ROWS {$fields}";
            $calc_found_rows = true;
        } else {
            $select = "SELECT {$fields}";
        }

        // not show list at all
        if ($cond === null) {
            if ($params['count_results'] == 'only') {
                return 0;
            } else {
                $total_count = 0;
                return array();
            }
        }

        if ($cond) {
            $cond = 'WHERE ' . join(' AND ', $cond);
        } else {
            $cond = '';
        }

        $join = join(" ", $joins);

        $sql = "{$select}
                FROM {$this->table} AS m
                {$join}
                {$cond}
                {$group_by}
                {$order_by}";

        if ($limit) {
            $sql .= " LIMIT $offset, $limit";
        }

        // cache counter
        if ($params['count_results'] === 'only') {
            if (isset($params['cache']) && $params['cache'] > 0) {
                $cache_key = serialize($sql) . serialize($bind_params) . wa()->getUser()->getId();
                $cache_key = md5($cache_key);
                $cache_var = new waVarExportCache("models/messages/list/count_results/{$cache_key}", $params['cache'], 'crm');
                $result = $cache_var->get();
                if (wa_is_int($result)) {
                    return $result;
                }
                $count = $this->query($sql, $bind_params)->fetchField();
                $cache_var->set($count);
                return $count;
            } else {
                return $this->query($sql, $bind_params)->fetchField();
            }
        }

        $db_result = $this->query($sql, $bind_params);
        if ($calc_found_rows) {
            $total_count = $this->query('SELECT FOUND_ROWS()')->fetchField();
        }

        $list = $db_result->fetchAll('id');
        if ($fields == "m.*") {
            $list = $this->workupList($list);
        }

        return $list;
    }

    public function getListByDeal($params, &$total_count = null)
    {
        // LIMIT
        if (isset($params['offset']) || isset($params['limit'])) {
            $offset = (int) ifset($params['offset'], 0);
            $limit = (int) ifset($params['limit'], 50);
            if (!$limit) {
                return array();
            }
        } else {
            $offset = $limit = null;
        }

        // Responsible filter
        $posponsible_join = "";
        if (!empty($params['responsible'])) {
            $posponsible_join = "
                JOIN crm_deal d
                    ON d.id = m.deal_id
                        AND d.user_contact_id = ".$this->escape($params['responsible']);
        }

        // Check rights of a limited user: only show messages in deals he participates in
        $rights_join = "";
        if (!empty($params['check_rights'])) {
            $rights_join = "
                JOIN crm_deal_participants dp
                    ON dp.deal_id = m.deal_id
                        AND dp.contact_id = ".wa()->getUser()->getId();
        }

        // Count rows setting
        if(!isset($params['count_results']) && func_num_args() > 1) {
            $params['count_results'] = true;
        }
        if (empty($params['count_results'])) {
            $select = "SELECT MAX(m.id) AS message_id";
        } else {
            $select = "SELECT SQL_CALC_FOUND_ROWS MAX(m.id) AS message_id";
        }

        // First query: get 30 message_id
        $sql = "{$select}
                FROM {$this->table} AS m
                    {$posponsible_join}
                    {$rights_join}
                    WHERE m.deal_id > 0
                        AND m.event IS NULL
                GROUP BY m.deal_id
                ORDER BY message_id DESC";
        if ($limit) {
            $sql .= " LIMIT $offset, $limit";
        }

        $message_ids = $this->query($sql)->fetchAll('message_id');
        $ids = array();
        foreach ($message_ids as $k => $v)
        {
            $ids[] = $k;
        }
        $ids = ifempty($ids, 0);
        $total_count = $this->query('SELECT FOUND_ROWS()')->fetchField();

        // Second query: get messages data
        $sql = "SELECT *
                FROM {$this->table}
                WHERE id IN (:ids)
                ORDER BY id DESC";

        $db_result = $this->query($sql, array('ids' => $ids));
        return $this->workupList($db_result->fetchAll('id'));
    }

    public function getListByClient($params, &$total_count = null)
    {
        // LIMIT
        if (isset($params['offset']) || isset($params['limit'])) {
            $offset = (int) ifset($params['offset'], 0);
            $limit = (int) ifset($params['limit'], 50);
            if (!$limit) {
                return array();
            }
        } else {
            $offset = $limit = null;
        }

        $condition = '1=1';
        //if (!empty($params['recipient'])) {
        //    $condition .= ' AND mr.contact_id='.intval($params['recipient']);
        //}
        if (!empty($params['transport']) && $params['transport'] != 'all') {
            $condition .= " AND m.transport='".strtoupper($this->escape($params['transport']))."'";
        }

        $recipient_join = "";
        if (!empty($params['recipient']) && $params['recipient'] != 'all') {
            $recipient_join = "
                INNER JOIN crm_message_recipients mr
                    ON mr.message_id = m.id AND mr.contact_id = "
                .intval($params['recipient']);
        }

        // Count rows setting
        if(!isset($params['count_results']) && func_num_args() > 1) {
            $params['count_results'] = true;
        }
        if (empty($params['count_results'])) {
            $select = "SELECT MAX(m.id) AS message_id, COUNT(m.id) 'count'";
        } else {
            $select = "SELECT SQL_CALC_FOUND_ROWS MAX(m.id) AS message_id, COUNT(m.id) 'count'";
        }

        // First query: get 30 message_id
        $sql = "{$select}, MAX(CASE direction WHEN 'IN' THEN id ELSE 0 END) as last_incoming_message
                FROM {$this->table} AS m
                    {$recipient_join}
                WHERE $condition AND m.event IS NULL
                GROUP BY m.contact_id, m.transport
                ORDER BY message_id DESC";
        if ($limit) {
            $sql .= " LIMIT $offset, $limit";
        }

        $message_ids = $this->query($sql)->fetchAll('message_id');

        $ids = $last_incoming_message_ids = array();
        foreach ($message_ids as $k => $v) {
            $ids[] = $k;
            if ($v['last_incoming_message']) {
                $last_incoming_message_ids[$v['last_incoming_message']] = $v['last_incoming_message'];
            }
        }
        $total_count = $this->query('SELECT FOUND_ROWS()')->fetchField();

        $last_incoming_message_read = $result = array();
        if ($ids) {
            if ($last_incoming_message_ids) {
                // One more query: get last_incoming_message_read
                $sql = "SELECT message_id
                    FROM crm_message_read
                    WHERE message_id IN (:ids)";
                $last_incoming_message_read = $this->query($sql, array('ids' => array_keys($last_incoming_message_ids)))->fetchAll('message_id');
            }

            // Second query: get messages data
            $sql = "SELECT m.*, mr.message_id as 'read'
                FROM {$this->table} m
                LEFT JOIN crm_message_read mr
                    ON mr.message_id=m.id AND mr.contact_id=".wa()->getUser()->getId()."
                WHERE m.id IN (:ids)
                ORDER BY id DESC";

            $result = $this->query($sql, array('ids' => $ids))->fetchAll('id');

            foreach($result as $id => &$r) {
                $r['count'] = ifempty($message_ids[$id]['count'], 0);
                $r['last_incoming_message'] = ifempty($message_ids[$id]['last_incoming_message']);
                $r['last_incoming_message_read'] = isset($last_incoming_message_read[$r['last_incoming_message']]);
            }
            unset($r);
        }
        return $this->workupList($result);
    }

    protected function workupList($list)
    {
        if (!$list) {
            return array();
        }

        $message_ids = array_keys($list);
        $pm = new crmMessageParamsModel();
        $message_params = $pm->get($message_ids);

        $source_ids = array();
        foreach ($list as &$item) {

            $item['params'] = (array)ifset($message_params[$item['id']]);

            $item['icon_url'] = null;
            $item['icon'] = 'exclamation';
            $item['transport_name'] = _w('Unknown');

            if ($item['source_id'] > 0) {
                $source_ids[] = $item['source_id'];
            }
            if ($item['transport'] == self::TRANSPORT_EMAIL) {
                $item['icon'] = 'email';
                $item['transport_name'] = 'Email';
            } elseif ($item['transport'] == self::TRANSPORT_SMS) {
                $item['icon'] = 'mobile';
                $item['transport_name'] = 'SMS';
            }
        }
        unset($item);

        $source_ids = array_unique($source_ids);
        $source_helpers = array();
        foreach ($source_ids as $source_id) {
            $source_helpers[$source_id] = crmSourceHelper::factory(crmSource::factory($source_id));
        }

        foreach ($list as &$item) {
            if ($item['source_id'] <= 0) {
                continue;
            }
            /**
             * @var crmSourceHelper $source_helper
             */
            $source_helper = $source_helpers[$item['source_id']];
            $res = $source_helper->workupMessageInList($item);
            $item = $res ? $res : $item;
        }
        unset($item);

        return $list;
    }

    protected function getTransports()
    {
        return $this->getConstants('TRANSPORT_');
    }

    protected function getDirections()
    {
        return $this->getConstants('DIRECTION_');
    }

    /**
     * Method used in pop-up with new messages on all backend pages
     * @return array
     */
    public function getNew() {
        $begin_date = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $end_date   = date('Y-m-d H:i:s');
        $user_id = intval(wa()->getUser()->getId());

        $m = new waModel();

        $sql = "SELECT
                    m.*,
                    c.id as contact_id,
                    c.name as contact_name,
                    d.id as deal_id,
                    d.name as deal_name,
                    d.funnel_id,
                    d.stage_id
                FROM crm_message m
                    JOIN wa_contact c
                        ON c.id = m.contact_id
                    LEFT JOIN crm_deal d
                        ON d.id = m.deal_id
                    LEFT JOIN crm_message_read rd
                        ON m.id = rd.message_id AND rd.contact_id=$user_id
                    INNER JOIN crm_message_recipients mr
                      ON m.id=mr.message_id
                        AND mr.type <> 'FROM'
                        AND mr.contact_id=$user_id
                WHERE m.direction = 'IN'
                  AND m.create_datetime BETWEEN ? AND ?
                  AND mr.contact_id IS NOT NULL
                  AND m.event IS NULL
                  AND rd.message_id IS NULL";

        $result = $m->query($sql, $begin_date, $end_date)->fetchAll('id');

        return $result;
    }

    /**
     * Method used in crm sidebar on all crm backend pages
     * @param string $counter
     * @param null $messages_max_id
     * @return bool|mixed
     */
    public function countByResponsible($counter, $messages_max_id = null)
    {
        // Update sidebar counter for admin (all messages)
        if (wa()->getUser()->isAdmin('crm')) {
            if ($counter == "messages_max_id") {
                return $this->select('MAX(id) mid')->where("event IS NULL")->fetchField('mid');
            } elseif ($counter == "messages_count") {
                return $this->select('COUNT(*) cnt')->where("event IS NULL")->fetchField('cnt');
            } elseif ($counter == "messages_new_count") {
                return $this->select('COUNT(*) cnt')->where("id > $messages_max_id AND event IS NULL")->fetchField('cnt');
            }
        } else {
            // Update sidebar counter for restricted user (with join to crm_deal_participants)
            if ($counter == "messages_max_id") {
                return $this->query("
                    SELECT MAX(m.id) mid
                    FROM crm_message m
                      JOIN crm_deal_participants dp
                        ON dp.deal_id = m.deal_id
                          AND dp.contact_id = ?
                    WHERE m.event IS NULL", wa()->getUser()->getId(), wa()->getUser()->getId() )->fetchField('mid');
            } elseif ($counter == "messages_count") {
                return $this->query("
                    SELECT COUNT(*) cnt
                    FROM crm_message m
                      JOIN crm_deal_participants dp
                        ON dp.deal_id = m.deal_id
                          AND dp.contact_id = ?
                    WHERE m.event IS NULL", wa()->getUser()->getId(), wa()->getUser()->getId() )->fetchField('cnt');
            } elseif ($counter == "messages_new_count") {
                return $this->query("
                    SELECT COUNT(*) cnt
                    FROM crm_message m
                      JOIN crm_deal_participants dp
                        ON dp.deal_id = m.deal_id
                          AND dp.contact_id = ?
                    WHERE m.id > ?
                      AND m.event IS NULL", wa()->getUser()->getId(), $messages_max_id )->fetchField('cnt');
            }
        }
    }

    /**
     * Count messages by deal_id
     * @param int|array $deal_ids
     * @param bool $with_event counting messages with a non-null field `event`
     * @return array
     */
    public function countByDealId($deal_ids, $with_event = false)
    {
        $deal_ids = crmHelper::toIntArray($deal_ids);
        $deal_ids = crmHelper::dropNotPositive($deal_ids);
        $deal_ids = ifempty($deal_ids, 0);

        $event = "";
        if ($with_event == false) {
            $event = 'AND event IS NULL';
        }

        return $this->query("
                SELECT deal_id, COUNT('id') as count
                FROM {$this->table}
                WHERE deal_id IN (:ids)
                    {$event}
                GROUP BY deal_id",array('ids' => $deal_ids))->fetchAll('deal_id');
    }

    /**
     * @param int|array $message
     * @param $conversation_id
     * @param array $conversation_update If also need to update conversation while adding new message
     * @throws waException
     */
    public function addToConversation($message, $conversation_id, $conversation_update = array())
    {
        if (is_scalar($message)) {
            $message = $this->getById($message);
        }
        if (!$message) {
            return;
        }

        if (!is_array($conversation_update)) {
            $conversation_update = array();
        }

        $conversation_update = array_merge($conversation_update, array(
            'last_message_id' => $message['id'],
            'count' => '+1'
        ));

        $this->getMessageModel()->updateById($message['id'], array(
            'conversation_id' => $conversation_id
        ));

        $this->getConversationModel()->update($conversation_id, $conversation_update);
        $this->getMessageReadModel()->setRead($message['id'], $message['creator_contact_id']);

        $conversation = $this->getConversationModel()->getConversation($conversation_id);

        if (!$message['deal_id'] && $conversation['deal_id']) {
            $this->updateById($message['id'], array('deal_id' => $conversation['deal_id']));
            // update log
            $fields = array(
                'contact_id'  => $message['contact_id'],
                'object_id'   => $message['id'],
                'action'      => self::LOG_ACTION_SENT,
                'object_type' => crmLogModel::OBJECT_TYPE_MESSAGE
            );
            $log = $this->getLogModel()->getByField($fields);
            if ($log) {
                $this->getLogModel()->updateById($log['id'], array('contact_id' => $conversation['deal_id'] * -1));
            }
        }

        if ($conversation['user_contact_id']) {
            $this->setRecipient($message['id'], array(
                'destination' => $conversation['user_contact_id'],
                'contact_id' => $conversation['user_contact_id'],
            ));
        }
    }

    /**
     * Get list of ID of conversations for these last message IDs
     * @param int[] $message_ids
     * @return array[int]int message_id => conversation_id
     * @throws waException
     */
    public function getMessageConversationIds($message_ids)
    {
        $message_ids = waUtils::toIntArray($message_ids);
        $message_ids = waUtils::dropNotPositive($message_ids);
        if (!$message_ids) {
            return array();
        }
        return $this->select('conversation_id')
            ->where($this->getWhereByField(array('id' => $message_ids)))
            ->fetchAll(null, true);
    }

    public function getConversationLastIncomingMessage($conversation_id)
    {
        return $this->select('*')
            ->where("direction = :direction AND conversation_id = :conversation_id", [
                'direction' => crmMessageModel::DIRECTION_IN,
                'conversation_id' => $conversation_id
            ])
            ->order("id DESC")->limit(1)->fetchAssoc();
    }
}
