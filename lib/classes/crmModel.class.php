<?php

class crmModel extends waModel
{
    protected static $static_cache = array();

    /**
     * cached models for models getters
     * @var array
     */
    protected $models;

    /**
     * @var crmRights
     */
    protected $crm_rights;

    /**
     * @var string|array[]string
     */
    protected $link_contact_field = 'contact_id';

    /**
     * @var array
     *
     * array(
     *     '<link_contact_field>' => array('set_to', '<value>')     // set to some value
     *     '<link_contact_field>' => NULL,                          // don't touch link at all
     *     '<link_contact_field>' => 'delete'                       // delete link (delete entity linked to contact by this link_contact_field)
     *     ...
     * )
     *
     * OR
     *
     * array('set_to', '<value>')
     *
     * OR
     *
     * NULL
     *
     * OR
     *
     * 'delete'
     */
    protected $unset_contact_links_behavior = 'delete';

    public function __construct($type = null, $writable = false)
    {
        parent::__construct($type, $writable);
        $this->typecastUnsetContactLinksBehavior();
    }

    public static function isTableColumnMb4($table, $column)
    {
        $columns_full_info = self::getColumnsFullInfo($table);
        return !empty($columns_full_info[$column]['is_mb4']);
    }

    protected static function getColumnsFullInfo($table)
    {
        if (!isset(self::$static_cache['columns_full_info'][$table])) {
            $cache = new waVarExportCache('columns_full_info', 60, "crm/models/{$table}/column_info");
            $info = $cache->get();
            if (!is_array($info) || empty($info)) {
                $m = new self();
                self::$static_cache['columns_full_info'][$table] = $m->query("SHOW FULL COLUMNS FROM `{$table}`")->fetchAll('Field');
                $cache->set(self::$static_cache['columns_full_info'][$table]);
            } else {
                self::$static_cache['columns_full_info'][$table] = $info;
            }
            foreach (self::$static_cache['columns_full_info'][$table] as &$column) {
                $column['is_mb4'] = !empty($column['Collation']) && preg_match('~^(utf8mb4_)~ui', $column['Collation']) ? true : false;
            }
            unset($column);
        }
        return self::$static_cache['columns_full_info'][$table];
    }

    public function getCrmRights()
    {
        return $this->crm_rights ? $this->crm_rights : ($this->crm_rights = new crmRights());
    }

    /**
     * @param string $order
     * @param null $key
     * @param bool $normalize
     * @return array
     */
    public function getAllOrdered($order, $key = null, $normalize = false)
    {
        return $this->select('*')->order($order)->fetchAll($key, $normalize);
    }

    /**
     * @param string $order
     * @param string $limit
     * @param null $key
     * @param bool $normalize
     * @return array
     */
    public function getAllOrderedAndLimited($order, $limit, $key = null, $normalize = false)
    {
        return $this->select('*')->order($order)->limit($limit)->fetchAll($key, $normalize);
    }

    /**
     * @param int|array[] int $contact_id
     * @return int
     * @throws waException
     */
    public function getContactLinksCount($contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$contact_ids) {
            return 0;
        }

        $link_contact_fields = (array) $this->link_contact_field;
        if (!$link_contact_fields) {
            return 0;
        }

        $where = array();
        foreach ($link_contact_fields as $link_contact_field) {
            $where[] = $this->getWhereByField($link_contact_field, $contact_ids);
        }

        $where = join(' OR ', $where);

        return $this->select('COUNT(*)')->where($where)->fetchField();
    }

    /**
     * @param int|array[] int $contact_id
     * @return void
     */
    public function unsetContactLinks($contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$contact_ids) {
            return;
        }

        $link_contact_fields = $this->link_contact_field;
        if (!$link_contact_fields) {
            return;
        }

        $delete_behavior = array();
        foreach ($this->unset_contact_links_behavior as $link_contact_field => $behavior) {
            if ($behavior === 'delete') {
                $delete_behavior['link_contact_fields'] = (array) ifset($delete_behavior['link_contact_fields']);
                $delete_behavior['link_contact_fields'][] = $link_contact_field;
            }
        }

        if ($delete_behavior) {
            $this->deleteContactLinks($contact_ids, $delete_behavior['link_contact_fields']);
        }

        foreach ($this->unset_contact_links_behavior as $link_contact_field => $behavior) {
            if (is_array($behavior) && ifset($behavior[0]) === 'set_to') {
                $this->setToContactsLinks($contact_ids, $link_contact_field, $behavior[1]);
            }
        }

        $tag_model = new crmTagModel();
        $tag_model->deleteUnattachedTags();
    }

    private function typecastUnsetContactLinksBehavior()
    {
        $link_contact_fields = $this->typecastLinkContactField();

        // 'ignore' behavior for all links
        if ($this->unset_contact_links_behavior === null) {
            $this->unset_contact_links_behavior = array();
            return;
        }

        $behavior = $this->unset_contact_links_behavior;
        $normalized_behavior = array();

        $is_single_delete = $behavior === 'delete';
        $is_single_set_to = is_array($behavior) && isset($behavior[0]) && $behavior[0] === 'set_to';
        if ($is_single_set_to) {
            $behavior[1] = ifset($behavior[1]);
        }

        if ($is_single_delete || $is_single_set_to) {
            foreach ($link_contact_fields as $link_contact_field) {
                $normalized_behavior[$link_contact_field] = $behavior;
            }
            return $this->unset_contact_links_behavior = $normalized_behavior;
        }

        foreach ($link_contact_fields as $link_contact_field) {
            $behavior = ifset($this->unset_contact_links_behavior[$link_contact_field]);

            $is_delete = $behavior === 'delete';
            $is_set_to = is_array($behavior) && isset($behavior[0]) && $behavior[0] === 'set_to';
            if ($is_single_set_to) {
                $behavior[1] = ifset($behavior[1]);
            }
            if (!$is_delete && !$is_set_to) {
                $behavior = null;
            }
            $normalized_behavior[$link_contact_field] = $behavior;
        }

        return $this->unset_contact_links_behavior = $normalized_behavior;
    }

    private function typecastLinkContactField()
    {
        $this->link_contact_field = (array) $this->link_contact_field;
        return $this->link_contact_field;
    }

    protected function deleteContactLinks($contact_ids, $link_contact_fields)
    {
        $where = array();
        foreach ($link_contact_fields as $link_contact_field) {
            $where[] = $this->getWhereByField($link_contact_field, $contact_ids);
        }

        $where = join(' OR ', $where);
        if (!$where) {
            return;
        }

        $this->exec("DELETE FROM `{$this->table}` WHERE {$where}");
    }

    protected function setToContactsLinks($contact_ids, $link_contact_fields, $set_to)
    {
        $is_array = is_array($set_to);
        $default_value = !is_array($set_to) ? $set_to : 0;

        $values = array();

        if (is_scalar($link_contact_fields)) {
            $link_contact_fields = (array)$link_contact_fields;
        }

        foreach ($link_contact_fields as $contact_field_id) {
            $values[$contact_field_id] = $default_value;
            if ($is_array && array_key_exists($contact_field_id, $set_to)) {
                $values[$contact_field_id] = $contact_field_id[$set_to];
            }
        }

        $set = array();
        foreach ($values as $field_id => $value) {
            $set[] = "`{$field_id}`" . ($value === null ? " = NULL " : " = '{$value}'");
        }

        $where = array();
        foreach ($link_contact_fields as $link_contact_field) {
            $where[] = $this->getWhereByField($link_contact_field, $contact_ids);
        }

        $where = join(' OR ', $where);
        if (!$where) {
            return;
        }

        $set = join(',', $set);

        $this->exec("UPDATE `{$this->table}` SET {$set} WHERE {$where}");

    }

    public function getConstants($prefix = '')
    {
        $constants = array();
        $reflection = new ReflectionClass($this);
        $prefix_len = strlen($prefix);
        foreach ($reflection->getConstants() as $name => $value) {
            if (substr($name, 0, $prefix_len) === $prefix) {
                $constants[] = $name;
            }
        }
        return $constants;
    }

    /**
     * @return crmAdhocGroupModel
     */
    protected function getAdhocGroupModel()
    {
        return $this->getModel('adhoc_group', 'crmAdhocGroupModel');
    }

    /**
     * @return crmPbxModel
     */
    protected function getPbxModel()
    {
        return $this->getModel('pbx', 'crmPbxModel');
    }

    /**
     * @return crmPbxParamsModel
     */
    protected function getPbxParamsModel()
    {
        return $this->getModel('pbx_params', 'crmPbxParamsModel');
    }

    /**
     * @return crmPbxUsersModel
     */
    protected function getPbxUsersModel()
    {
        return $this->getModel('pbx_users', 'crmPbxUsersModel');
    }

    /**
     * @return crmCallModel
     */
    protected function getCallModel()
    {
        return $this->getModel('call', 'crmCallModel');
    }

    /**
     * @return crmCompanyModel
     */
    protected function getCompanyModel()
    {
        return $this->getModel('company', 'crmCompanyModel');
    }

    /**
     * @return crmContactModel
     */
    protected function getContactModel()
    {
        return $this->getModel('contact', 'crmContactModel');
    }

    /**
     * @return crmContactTagsModel
     */
    protected function getContactTagsModel()
    {
        return $this->getModel('contacts_tag', 'crmContactTagsModel');
    }

    /**
     * @return crmCurrencyModel
     */
    protected function getCurrencyModel()
    {
        return $this->getModel('currency', 'crmCurrencyModel');
    }

    /**
     * @return crmDealModel
     */
    protected function getDealModel()
    {
        return $this->getModel('deal', 'crmDealModel');
    }

    /**
     * @return crmDealLostModel
     */
    protected function getDealLostModel()
    {
        return $this->getModel('deal_lost', 'crmDealLostModel');
    }

    /**
     * @return crmDealParticipantsModel
     */
    protected function getDealParticipantsModel()
    {
        return $this->getModel('deal_participants', 'crmDealParticipantsModel');
    }

    /**
     * @return crmDealParamsModel
     */
    protected function getDealParamsModel()
    {
        return $this->getModel('deal_params', 'crmDealParamsModel');
    }

    /**
     * @return crmFileModel
     */
    protected function getFileModel()
    {
        return $this->getModel('file', 'crmFileModel');
    }

    /**
     * @return crmFormModel
     */
    protected function getFormModel()
    {
        return $this->getModel('from', 'crmFormModel');
    }

    /**
     * @return crmFormParamsModel
     */
    protected function getFormParamsModel()
    {
        return $this->getModel('from_params', 'crmFormParamsModel');
    }

    /**
     * @return crmFunnelModel
     */
    protected function getFunnelModel()
    {
        return $this->getModel('funnel', 'crmFunnelModel');
    }

    /**
     * @return crmFunnelStageModel
     */
    protected function getFunnelStageModel()
    {
        return $this->getModel('funnel_stage', 'crmFunnelStageModel');
    }

    /**
     * @return crmInvoiceModel
     */
    protected function getInvoiceModel()
    {
        return $this->getModel('invoice', 'crmInvoiceModel');
    }

    /**
     * @return crmInvoiceItemsModel
     */
    protected function getInvoiceItemsModel()
    {
        return $this->getModel('invoice_items', 'crmInvoiceItemsModel');
    }

    /**
     * @return crmInvoiceParamsModel
     */
    protected function getInvoiceParamsModel()
    {
        return $this->getModel('invoice_params', 'crmInvoiceParamsModel');
    }

    /**
     * @return crmLogModel
     */
    protected function getLogModel()
    {
        return $this->getModel('log', 'crmLogModel');
    }

    /**
     * @return crmMessageModel
     */
    protected function getMessageModel()
    {
        return $this->getModel('message', 'crmMessageModel');
    }

    /**
     * @return crmMessageParamsModel
     */
    protected function getMessageParamsModel()
    {
        return $this->getModel('message_params', 'crmMessageParamsModel');
    }

    /**
     * @return crmMessageAttachmentsModel
     */
    protected function getMessageAttachmentsModel()
    {
        return $this->getModel('message_attachments', 'crmMessageAttachmentsModel');
    }

    /**
     * @return crmMessageReadModel
     */
    protected function getMessageReadModel()
    {
        return $this->getModel('message_read', 'crmMessageReadModel');
    }

    /**
     * @return crmMessageRecipientsModel
     */
    protected function getMessageRecipientsModel()
    {
        return $this->getModel('message_recipients', 'crmMessageRecipientsModel');
    }

    /**
     * @return crmConversationModel
     */
    protected function getConversationModel()
    {
        return $this->getModel('conversation', 'crmConversationModel');
    }

    /**
     * @return crmNoteModel
     */
    protected function getNoteModel()
    {
        return $this->getModel('note', 'crmNoteModel');
    }

    /**
     * @return crmNotificationModel
     */
    protected function getNotificationModel()
    {
        return $this->getModel('notification', 'crmNotificationModel');
    }

    /**
     * @return crmPaymentModel
     */
    protected function getPaymentModel()
    {
        return $this->getModel('payment', 'crmPaymentModel');
    }

    /**
     * @return crmPaymentSettingsModel
     */
    protected function getPaymentSettingsModel()
    {
        return $this->getModel('payment_settings', 'crmPaymentSettingsModel');
    }

    /**
     * @return crmRecentModel
     */
    protected function getRecentModel()
    {
        return $this->getModel('recent', 'crmRecentModel');
    }

    /**
     * @return crmReminderModel
     */
    protected function getReminderModel()
    {
        return $this->getModel('reminder', 'crmReminderModel');
    }

    /**
     * @return crmSegmentModel
     */
    protected function getSegmentModel()
    {
        return $this->getModel('segment', 'crmSegmentModel');
    }

    /**
     * @return crmSegmentCountModel
     */
    protected function getSegmentCountModel()
    {
        return $this->getModel('segment_count', 'crmSegmentCountModel');
    }

    /**
     * @return crmTempModel
     */
    protected function getSignupTempModel()
    {
        return $this->getModel('temp', 'crmTempModel');
    }

    /**
     * @return crmTagModel
     */
    protected function getTagModel()
    {
        return $this->getModel('tag', 'crmTagModel');
    }

    /**
     * @return crmVaultModel
     */
    protected function getVaultModel()
    {
        return $this->getModel('vault', 'crmVaultModel');
    }

    /**
     * @return crmSourceModel
     */
    protected function getSourceModel()
    {
        return $this->getModel('source', 'crmSourceModel');
    }

    /**
     * @return crmSourceParamsModel
     */
    protected function getSourceParamsModel()
    {
        return $this->getModel('source_params', 'crmSourceParamsModel');
    }

    /**
     * @param $prefix
     * @param $value
     * @return bool
     */
    protected function isAllowedConstValue($prefix, $value)
    {
        $constants = $this->getConstantsByPrefix($prefix);
        return in_array($value, $constants, true);
    }

    protected function getConstantsByPrefix($prefix = '')
    {
        $len = strlen($prefix);
        $refl = new ReflectionClass($this);
        $constants = array();
        foreach ($refl->getConstants() as $name => $val) {
            if (substr($name, 0, $len) === $prefix) {
                $constants[$name] = $val;
            }
        }
        return $constants;
    }

    /**
     * @param $key
     * @param $class
     * @throws waException
     * @return waModel
     */
    private function getModel($key, $class)
    {
        if (!isset($this->models[$key]) || get_class($this->models[$key]) !== $class) {
            $this->models[$key] = new $class();
        }
        if (!($this->models[$key] instanceof waModel)) {
            throw new waException('Class must be instance of waModel');
        }
        return $this->models[$key];
    }

}
