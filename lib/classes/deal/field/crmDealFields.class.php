<?php


/**
 * Class for store and manage fields
 */
class crmDealFields
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var array[]crmDealField[]
     */
    protected static $fields;

    /**
     * @var crmDealFields
     */
    protected static $default_instance;

    public function __construct($path = null)
    {
        if (!$path) {
            $path = 'deal/fields.php';
        }
        $this->path = $path;
    }

    /**
     * @return crmDealFields
     * @throws waException
     */
    protected static function getInstance()
    {
        return self::$default_instance !== null ? self::$default_instance :
            (self::$default_instance = new crmDealFields());
    }

    /**
     * Static interface method for similarity with waContactFields
     * @see waContactFields
     *
     * Return object of the field or null if not found
     *
     * @param string $field_id
     * @param string $type 'enabled'|'disabled'|'all'
     *      By default type is 'enabled'. If value is not from available types, it will be 'all'
     * @return crmDealField|null
     * @throws waException
     */
    public static function get($field_id, $type = 'enabled')
    {
        self::templateHasNotAccess();
        $config = self::getInstance();
        $field = $config->getField($field_id);
        if (!$field) {
            return null;
        }
        if ($type === 'enabled') {
            return $config->isFieldEnabled($field) ? $field : null;
        }
        if ($type === 'disabled') {
            return $config->isFieldDisabled($field) ? $field : null;
        }
        return $field;
    }

    /**
     * Static interface method for similarity with waContactFields
     * @see waContactFields
     *
     * @param string $type 'enabled'|'disabled'|'all'
     *      By default type is 'enabled'. If value is not from available types, it will be 'all'
     * @return crmDealField[]
     * @throws waException
     */
    public static function getAll($type = 'enabled')
    {
        self::templateHasNotAccess();
        $config = self::getInstance();
        $fields = array();
        foreach ($config->getAllFields() as $field_id => $field) {
            if ($type === 'enabled') {
                $hit = $config->isFieldEnabled($field);
            } elseif ($type === 'disabled') {
                $hit = $config->isFieldDisabled($field);
            } else {
                $hit = true;
            }
            if ($hit) {
                $fields[$field_id] = $field;
            }
        }
        return $fields;
    }

    /**
     * Static interface method for similarity with waContactFields
     * @see waContactFields
     *
     * Get info items for all fields
     * @param string $type 'enabled'|'disabled'|'all'
     * @return array
     * @throws waException
     */
    public static function getInfo($type = 'enabled')
    {
        $info = array();
        foreach (self::getInstance()->getInfoOfAllFields() as $field_id => $field) {
            if ($type === 'enabled') {
                $hit = $field['is_enabled'];
            } elseif ($type === 'disabled') {
                $hit = !$field['is_enabled'];
            } else {
                $hit = true;
            }
            if ($hit) {
                $info[$field_id] = $field;
            }
        }
        return $info;
    }

    /**
     * Static interface method for similarity with waContactFields
     * @see waContactFields
     *
     * Delete the field completely.
     * @param $id crmDealField|string field ID or field instance.
     * @throws waException
     */
    public static function deleteField($id)
    {
        self::templateHasNotAccess();
        return self::getInstance()->removeField($id);
    }

    /**
     * Static interface method for similarity with waContactFields
     * @see waContactFields
     *
     * Enable field
     * @param crmDealField|string $field
     * @throws waException
     */
    public static function enableField($field)
    {
        self::templateHasNotAccess();
        self::getInstance()->setFieldEnabled($field);
    }

    /**
     * Static interface method for similarity with waContactFields
     * @see waContactFields
     *
     * Disable field
     * @param crmDealField|string $field
     * @throws waException
     */
    public static function disableField($field)
    {
        self::templateHasNotAccess();
        self::getInstance()->setFieldDisabled($field);
    }

    /**
     * Static interface method for similarity with waContactFields
     * @see waContactFields
     *
     * Update existing field without affecting *_fields_order.php
     * or add it to custom fields if not exists yet.
     * @param crmDealField $field
     * @throws waException
     */
    public static function updateField($field)
    {
        self::templateHasNotAccess();
        return self::getInstance()->saveField($field);
    }

    /**
     * Static interface method
     * @param array $field_ids that ids of fields that must be in top of list
     */
    public static function reorderFields($field_ids)
    {
        self::templateHasNotAccess();
        return self::getInstance()->resortFields($field_ids);
    }

    /**
     * Return object of the field or null if not found
     *
     * @param string $field_id
     * @return crmDealField|null
     */
    public function getField($field_id)
    {
        $fields = $this->getAllFields();
        return isset($fields[$field_id]) ? $fields[$field_id] : null;
    }

    /**
     * @return crmDealField[]
     */
    public function getAllFields()
    {
        if (isset(self::$fields[$this->path]) && self::$fields[$this->path] !== null) {
            return self::$fields[$this->path];
        }

        $path = $this->getFilePath();
        if (!file_exists($path)) {
            return self::$fields[$this->path] = array();
        }

        $fields = array();
        foreach (include($path) as $field) {
            if ($field instanceof crmDealField) {
                $fields[$field->getId()] = $field;
            }
        }

        return self::$fields[$this->path] = $fields;
    }

    /**
     * Get info items for all fields
     * @return array
     */
    public function getInfoOfAllFields()
    {
        $fields = $this->getAllFields();
        foreach ($fields as $field_id => $field) {
            $result[$field_id] = $field->getInfo();
            $result[$field_id]['is_enabled'] = $this->isFieldEnabled($field);
        }
        return $result;
    }

    /**
     * Delete the field completely.
     * @param $id crmDealField|string field ID or field instance.
     * @throws waException
     */
    public function removeField($id)
    {
        if ($id instanceof crmDealField) {
            $id = $id->getId();
        }

        $fields = $this->getAllFields();
        if (!isset($fields[$id])) {
            return;
        }
        unset($fields[$id]);

        $this->exportFields($fields);
    }

    /**
     * Update existing field or add it to custom fields if not exists yet.
     * @throws waException
     * @param crmDealField $field
     */
    public function saveField($field)
    {
        if (!($field instanceof crmDealField)) {
            throw new waException('Invalid deal field '.print_r($field, true));
        }

        $fields = $this->getAllFields();
        $id = $field->getId();
        $field = clone $field;
        $fields[$id] = $field;

        $this->exportFields($fields);
    }

    /**
     * @param $field_ids that ids of fields that must be in top of list
     */
    public function resortFields($field_ids)
    {
        $field_ids = is_scalar($field_ids) ? (array)$field_ids : $field_ids;
        if (!is_array($field_ids)) {
            return;
        }

        $all_fields = $this->getAllFields();
        $all_field_ids = array_keys($all_fields);
        $sorted_fields = array();

        foreach ($field_ids as $field_id) {
            if (!is_scalar($field_id) || !isset($all_fields[$field_id])) {
                continue;
            }
            $field = $all_fields[$field_id];
            $sorted_fields[$field_id] = $field;
            unset($all_fields[$field_id]);
        }

        foreach ($all_fields as $field_id => $field) {
            $sorted_fields[$field_id] = $field;
        }

        $sorted_field_ids = array_keys($sorted_fields);

        if ($sorted_field_ids != $all_field_ids) {
            $this->exportFields($sorted_fields);
        }
    }

    /**
     * Enable field
     * @param crmDealField|string $field
     * @throws waException
     */
    public function setFieldEnabled($field)
    {
        if ($field instanceof crmDealField) {
            $field = $field->getId();
        }
        if (is_scalar($field)) {
            $field = $this->getField((string)$field);
        }
        if (!($field instanceof crmDealField)) {
            throw new waException('Invalid deal field '.print_r($field, true));
        }
        if ($field->getParameter('disabled')) {
            $field->setParameter('disabled', null);
            $this->saveField($field);
        }
    }

    /**
     * Disable field
     * @param crmDealField|string $field
     * @throws waException
     */
    public function setFieldDisabled($field)
    {
        if ($field instanceof crmDealField) {
            $field = $field->getId();
        }
        if (is_scalar($field)) {
            $field = $this->getField((string)$field);
        }
        if (!($field instanceof crmDealField)) {
            throw new waException('Invalid deal field '.print_r($field, true));
        }
        if (!$field->getParameter('disabled')) {
            $field->setParameter('disabled', '1');
            $this->saveField($field);
        }
    }

    /**
     * @param crmDealField|string $field
     * @return bool
     * @throws waException
     */
    public function isFieldEnabled($field)
    {
        if (is_scalar($field)) {
            $field = $this->getField((string)$field);
        }
        if (!($field instanceof crmDealField)) {
            throw new waException('Invalid deal field '.print_r($field, true));
        }
        return !$field->getParameter('disabled');
    }

    /**
     * @param crmDealField|string $field
     * @return bool
     * @throws waException
     */
    public function isFieldDisabled($field)
    {
        return !$this->isFieldEnabled($field);
    }

    /**
     * Null if file path is not exists
     * @return null|string
     */
    protected function getFilePath()
    {
        $path = ltrim($this->path, '/');
        if (substr($path, -4) !== '.php') {
            $path .= '.php';
        }
        $path = wa('crm')->getConfigPath('crm') . '/' . $path;
        $dir = dirname($path);
        if (!file_exists($dir)) {
            waFiles::create($dir, true);
        }
        return $path;
    }

    protected function exportFields($fields)
    {
        self::$fields[$this->path] = $fields;
        foreach ($fields as $field) {
            if ($field instanceof crmDealField) {
                $field->prepareVarExport();
            }
        }
        $path = $this->getFilePath();
        waUtils::varExportToFile($fields, $path, true);
    }

    protected static function templateHasNotAccess()
    {
        if (waConfig::get('is_template')) {
            throw new waException('access from template is not allowed');
        }
    }
}
