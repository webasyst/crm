<?php

class crmDealsExporter
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $process_id;

    /**
     * @var array
     */
    protected $vars = array();

    /**
     * @var waCache
     */
    protected static $cache;

    /**
     * @var array
     */
    protected static $class_cache;

    /**
     * @var waModel[]
     */
    protected static $models;

    public function __construct($options = array())
    {
        if (isset($options['process_id'])) {
            $this->process_id = $options['process_id'];
            unset($options['process_id']);
        } else {
            $this->process_id = uniqid(get_class($this), true);
        }
        if (isset($options['ids'])) {
            $ids = $options['ids'];
            $this->setIds($ids);
        }
        $this->options = $options;
    }

    /**
     * @param int $chunk_size
     * @return bool Done or not
     */
    public function exportChunk($chunk_size = 100)
    {
        if ($this->isExportDone()) {
            return true;
        }

        $offset = $this->getOffset();
        $ids = $this->getIds();

        $ids = array_slice($ids, $offset, $chunk_size);
        $result = $this->exportDeals($ids);
        $this->saveResultChunk($result);
        $offset += count($ids);
        $this->setOffset($offset);
        if ($offset >= $this->getTotalCount()) {
            $this->markExportAsDone();
            return true;
        }
        return false;
    }

    public function getCurrentProgress()
    {
        $total_count = $this->getTotalCount();
        if ($total_count <= 0) {
            return 100;
        }
        $offset = $this->getOffset();

        return ($offset / $total_count) * 100;
    }

    /**
     * @param int $chunk_size
     * @return array
     */
    public function getExportResultChunk($chunk_size = 100)
    {
        $data = array();

        $result = $this->getSavedResult();
        if (!$result || $this->isExportResultGettingDone()) {
            return array();
        }

        $not_export_empty_columns = (bool)ifset($this->options['not_export_empty_columns']);
        $not_export_column_names  = (bool)ifset($this->options['not_export_column_names']);

        if (!$not_export_column_names && !$this->isFieldsReceived()) {
            $line = array();
            foreach ($result as $field_id => $res) {
                $is_empty_column = ifset($res['is_empty_column']);
                if ($not_export_empty_columns && $is_empty_column) {
                    continue;
                }
                $line[$field_id] = $res['name'];
            }
            $data['fields'] = $line;
            $this->markFieldsAsReceived();
            $chunk_size -= 1;
        }

        if ($chunk_size <= 0) {
            return $data;
        }

        foreach ($result as $field_id => &$res) {
            $line_i = 0;
            foreach ($res['data'] as $deal_id => $value) {

                $is_empty_column = ifset($res['is_empty_column']);
                $skip = $not_export_empty_columns && $is_empty_column;
                if (!$skip) {
                    $data[$deal_id][$field_id] = $value;
                }

                unset($res['data'][$deal_id]);

                $line_i += 1;
                if ($line_i >= $chunk_size) {
                    break;
                }
            }
        }
        unset($res);

        $data_count = count($data);
        $not_data = $data_count <= 0;
        $only_fields = $data_count === 1 && key($data) === 'fields';
        if ($chunk_size > 0 && ($not_data || $only_fields)) {
            $this->markExportResultGettingAsDone();
            return array();
        }

        $this->setCacheVar('result', $result, true);

        return $data;
    }

    /**
     * @param $ids
     * @return array
     */
    protected function exportDeals($ids)
    {
        $result = array();
        foreach ($this->getFields() as $field) {
            $result[$field['id']] = array(
                'name' => $field['name'],
                'data' => array()
            );
        }

        $deals = $this->getDealModel()->getById($ids);
        foreach ($deals as $deal) {
            $data = $this->exportDeal($deal, $this->getFields());
            foreach ($data as $field_id => $value) {
                $result[$field_id]['data'][$deal['id']] = $value;
            }
        }

        return $result;
    }

    protected function exportDeal($deal, $fields)
    {
        $data = array();
        foreach ($fields as $field) {
            $field_id = $field['id'];
            $value = $this->formatFieldValue($field, (string)ifset($deal[$field_id]));
            $data[$field['id']] = $value;
        }
        return $data;
    }

    protected function formatFieldValue($field, $value)
    {
        if ($field['type'] === 'datetime') {
            return $this->formatDatetimeValue($value);
        }
        if ($field['type'] === 'date') {
            return $this->formatDateValue($value);
        }
        if ($field['id'] === 'stage_id') {
            return $this->getStageName($value);
        }
        if ($field['id'] === 'status_id') {
            return $this->getStatusName($value);
        }
        if ($field['id'] === 'lost_text') {
            return $this->getLostText($value);
        }
        return $value;
    }

    protected function formatDatetimeValue($datetime)
    {
        $datetime = strtotime($datetime);
        if (!$datetime) {
            return '';
        }
        return date('d.m.Y H:i:s', $datetime);
    }

    protected function formatDateValue($date)
    {
        $date = strtotime($date);
        if (!$date) {
            return '';
        }
        return date('d.m.Y', $date);
    }

    protected function getStageName($stage_id)
    {
        $stages = $this->getStages();
        return isset($stages[$stage_id]) ? $stages[$stage_id]['name'] : $stage_id;
    }

    protected function getLostText($lost_id)
    {
        $lost_reasons = $this->getLostReasons();
        return isset($lost_reasons[$lost_id]) ? $lost_reasons[$lost_id] : $lost_id;
    }

    /**
     * @return array
     */
    protected function getStages()
    {
        if (!empty(self::$class_cache['stages'])) {
            return self::$class_cache['stages'];
        }
        return self::$class_cache['stages'] = $this->getStageModel()->getAll('id');
    }

    /**
     * @return array
     */
    protected function getLostReasons()
    {
        if (!empty(self::$class_cache['lost_reasons'])) {
            return self::$class_cache['lost_reasons'];
        }
        return self::$class_cache['lost_reasons'] = $this->getDealLostModel()->getAll('id');
    }

    protected function getStatusName($status_id)
    {
        $name = crmDealModel::getStatusName($status_id);
        return $name ? $name : $status_id;
    }

    /**
     * @return array
     */
    protected function getFields()
    {
        if (!empty(self::$class_cache['fields'])) {
            return self::$class_cache['fields'];
        }
        self::$class_cache['fields'] = array();
        $meta = $this->getDealModel()->getMetadata();
        foreach (array(
            'create_datetime' => _w('Create datetime'),
            'name' => _w('Name'),
            'stage_id' => _w('Stage'),
            'description' => _w('Description'),
            'expected_date' => _w('Expected date'),
            'amount' => _w('Amount'),
            'currency_id' => _w('Currency'),
            'status_id' => _w('Status'),
            'closed_datetime' => _w('Closing date'),
            'lost_text' => _w('Lost reason'),
            'contact_id' => 'Contact ID'
         ) as $field_id => $field_name) {
            if (!isset($meta[$field_id])) {
                continue;
            }

            $info = $meta[$field_id];
            $field = array(
                'id' => $field_id,
                'name' => $field_name,
                'type' => $info['type']
            );

            if ($info['type'] === 'enum') {
                $field['options'] = explode("','", substr($info['params'], 1, -1));
            }

            self::$class_cache['fields'][] = $field;
        }
        return self::$class_cache['fields'];
    }

    /**
     * @return string
     */
    public function getProcessId()
    {
        return $this->process_id;
    }

    protected function getIds()
    {
        return (array)$this->getCacheVar('ids', true);
    }

    protected function setIds($ids)
    {
        $ids = (array)$ids;
        $this->setTotalCount(count($ids));
        $this->setCacheVar('ids', $ids, true);
    }

    /**
     * @return int
     */
    protected function getTotalCount()
    {
        return (int)$this->getCacheVar('total_count');
    }

    protected function setTotalCount($total_count)
    {
        return $this->setCacheVar('total_count', (int)$total_count);
    }

    /**
     * @return bool
     */
    public function isExportDone()
    {
        return (bool)$this->getCacheVar('is_export_done');
    }

    protected function markExportAsDone()
    {
        $this->setCacheVar('is_export_done', 1);
    }

    /**
     * @return int
     */
    protected function getOffset()
    {
        return (int)$this->getCacheVar('offset');
    }

    /**
     * @param int $offset
     */
    protected function setOffset($offset)
    {
        return $this->setCacheVar('offset', $offset);
    }

    /**
     * @return bool
     */
    public function isExportResultGettingDone()
    {
        return (bool)$this->getCacheVar('is_export_result_getting_done');
    }

    protected function markExportResultGettingAsDone()
    {
        $this->setCacheVar('is_export_result_getting_done', 1);
    }

    /**
     * @return bool
     */
    protected function isFieldsReceived()
    {
        return (bool)$this->getCacheVar('is_fields_received');
    }

    protected function markFieldsAsReceived()
    {
        $this->setCacheVar('is_fields_received', 1);
    }

    /**
     * @return array
     */
    protected function getSavedResult()
    {
        return (array)$this->getCacheVar('result', true);
    }

    /**
     * @param $result
     */
    protected function saveResultChunk($result)
    {
        $saved_result = $this->getCacheVar('result', true);
        if ($saved_result === null) {
            $saved_result = array();
            foreach ($result as $field_id => $res) {
                $saved_result[$field_id]['name'] = $res['name'];
                $saved_result[$field_id]['is_empty_column'] = true;
                $saved_result[$field_id]['data'] = array();
            }
        }

        foreach ($result as $field_id => $res) {
            foreach ($res['data'] as $deal_id => $value) {
                if (strlen($value) > 0) {
                    $saved_result[$field_id]['is_empty_column'] = false;
                }
                $saved_result[$field_id]['data'][$deal_id] = $value;
            }
        }
        $this->setCacheVar('result', $saved_result, true);
    }

    protected function getCacheVar($name, $json = false)
    {
        if (array_key_exists($name, $this->vars)) {
            return $this->vars[$name];
        }

        $key = $this->getVarKey($name);
        $value = $this->getCache()->get($key);

        if ($value === null || !$json) {
            return $this->vars[$name] = $value;
        }
        return $this->vars[$name] = json_decode($value, true);
    }

    protected function setCacheVar($name, $value, $json = false)
    {
        $key = $this->getVarKey($name);
        if ($value === null) {
            $this->getCache()->delete($key);
            if (array_key_exists($name, $this->vars)) {
                unset($this->vars[$name]);
            }
            return;
        }
        $this->vars[$name] = $value;
        if ($json) {
            $value = json_encode($value);
        }
        $this->getCache()->set($key, $value);
    }

    protected function getVarKey($name)
    {
        return $name . '_var_' . __CLASS__ . $this->process_id;
    }

    /**
     * @return waCache
     */
    protected function getCache()
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $cache = wa('crm')->getConfig()->getCache();
        if (!($cache instanceof waCache)) {
            $cache_adapter = new waFileCacheAdapter(array());
            $cache = new waCache($cache_adapter, 'crm');
        }
        return self::$cache = $cache;
    }

    /**
     * @return crmDealModel
     */
    protected function getDealModel()
    {
        return !empty(self::$models['deal']) ? self::$models['deal'] : (self::$models['deal'] = new crmDealModel());
    }

    /**
     * @return crmFunnelStageModel
     */
    protected function getStageModel()
    {
        return !empty(self::$models['stage']) ? self::$models['stage'] : (self::$models['stage'] = new crmFunnelStageModel());
    }

    /**
     * @return crmDealLostModel
     */
    protected function getDealLostModel()
    {
        return !empty(self::$models['deal']) ? self::$models['deal'] : (self::$models['deal'] = new crmDealLostModel());
    }
}
