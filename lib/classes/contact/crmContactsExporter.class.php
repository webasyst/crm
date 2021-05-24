<?php

class crmContactsExporter
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
     * @var crmContactsCollection
     */
    protected $collection;

    /**
     * @var array
     */
    protected $vars = array();

    /**
     * @var array
     */
    protected static $fields;

    /**
     * @var waCache
     */
    protected static $cache;

    /**
     * @var waModel[]
     */
    protected static $models;

    /**
     * @var array
     */
    protected static $countries;

    /**
     * @var array
     */
    protected static $regions;

    public function __construct($options = array())
    {
        if (isset($options['process_id'])) {
            $this->process_id = $options['process_id'];
            unset($options['process_id']);
        } else {
            $this->process_id = uniqid(get_class($this), true);
        }
        if (isset($options['hash'])) {
            $this->setHash((string)$options['hash']);
        }
        $this->options = $options;
    }

    /**
     * @return string
     */
    public function getProcessId()
    {
        return $this->process_id;
    }

    /**
     * @param int $chunk_size
     * @return bool Done or not
     * @throws waException
     */
    public function exportChunk($chunk_size = 100)
    {
        if ($this->isExportDone()) {
            return true;
        }
        $offset = $this->getOffset();
        $collection = $this->getCollection();
        $res = $collection->getContacts('id', $offset, $chunk_size);
        $contact_ids = array_keys($res);
        $result = $this->exportContacts($contact_ids);
        $this->saveResultChunk($result);
        $offset += count($contact_ids);
        $this->setOffset($offset);
        if ($offset >= $collection->count()) {
            $this->markExportAsDone();
            return true;
        }
        return false;
    }

    /**
     * @return double
     */
    public function getCurrentProgress()
    {
        $total_count = $this->getCollection()->count();
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
                $repetition = $res['repetition'];
                for ($i = 0; $i < $repetition; $i += 1) {
                    $is_empty_column = ifset($res['is_empty_column'][$i]);
                    if ($not_export_empty_columns && $is_empty_column) {
                        continue;
                    }
                    $line["{$field_id}/{$i}"] = $res['name'];
                }
            }
            $data['fields'] = $line;
            $this->markFieldsAsReceived();
            $chunk_size -= 1;
        }

        if ($chunk_size <= 0) {
            return $data;
        }

        foreach ($result as $field_id => &$res) {
            $repetition = $res['repetition'];
            $line_i = 0;
            foreach ($res['data'] as $contact_id => $values) {
                $data[$contact_id] = (array)ifset($data[$contact_id]);
                $values = (array)$values;
                for ($i = 0; $i < $repetition; $i += 1) {
                    $is_empty_column = ifset($res['is_empty_column'][$i]);
                    if ($not_export_empty_columns && $is_empty_column) {
                        continue;
                    }
                    $data[$contact_id]["{$field_id}/{$i}"] = (string)ifset($values[$i]);
                }
                unset($res['data'][$contact_id]);

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

    protected function exportContacts($contacts)
    {
        $result = array();
        foreach ($this->getFields() as $field) {
            $result[$field['full_id']] = array(
                'name' => $field['full_name'],
                'data' => array()
            );
        }
        foreach ($contacts as $contact) {
            $contact = $this->prepareContact($contact);
            $data = $this->exportContact($contact, $this->getFields());
            foreach ($data as $full_field_id => $values) {
                if (is_array($values)) {
                    $values = array_values($values);
                    if (empty($values)) {
                        $values = array('');
                    }
                    foreach ($values as &$value) {
                        $value = (string)$value;
                    }
                    unset($value);
                } else {
                    $values = (string)$values;
                }
                $result[$full_field_id]['data'][$contact['id']] = $values;
            }
        }

        return $result;
    }


    /**
     * Prepare contact for export
     * @param int|array|waContact $contact
     * @return array
     */
    protected function prepareContact($contact)
    {
        if (wa_is_int($contact)) {
            return $this->obtainContact($contact);
        }

        $result = array(
            'id' => $contact['id'],
            'contact' => array(),
            'emails' => array(),
            'data' => array()
        );

        $empty_contact = $this->getContactModel()->getEmptyRow();
        foreach ($empty_contact as $field_id => $default) {
            $result['contact'][$field_id] = isset($contact[$field_id]) ? $contact[$field_id] : $default;
        }

        if (!empty($contact['email'])) {
            $sort = 0;
            foreach ($contact['email'] as $index => $email) {
                if (!is_array($email)) {
                    $email = array(
                        'value' => (string)$email,
                        'ext' => '',
                        'status' => 'unknown'
                    );
                }
                $email['sort'] = $sort++;
                $result['emails'][] = $email;
            }
        }

        $result['data'] = $this->obtainContactData($contact['id']);

        // get name as in DB, because 'name' is kind a magic
        $result['contact']['name'] =
            $this->getContactModel()
                ->select('name')
                ->where('id = ?', $contact['id'])
                ->fetchField();

        return $result;
    }

    protected function obtainContact($contact_id)
    {
        $result = array(
            'id' => $contact_id
        );
        $result['contact'] = (array)$this->getContactModel()->getById($contact_id);
        $result['emails'] = $this->obtainContactEmails($contact_id);
        $result['data'] = $this->obtainContactData($contact_id);
        return $result;
    }

    protected function obtainContactEmails($contact_id)
    {
        $emails = (array)$this->getContactEmailsModel()->getEmails($contact_id);
        $sort = 0;
        foreach ($emails as &$email) {
            $email['email'] = $email['value'];
            $email['sort'] = $sort++;
        }
        unset($email);
        return $emails;
    }

    protected function obtainContactData($contact_id)
    {
        $sql = "SELECT `field`, `ext`, `value`, `sort`  
                FROM wa_contact_data AS cd
                WHERE contact_id = ?
                ORDER BY `field`, `sort`";

        $data = array();
        foreach ($this->getContactDataModel()->query($sql, (int) $contact_id) as $item) {
            $data[$item['field']] = (array) ifset($data[$item['field']]);
            $data[$item['field']][] = $item;
        }

        if (isset($data['address:country'])) {
            $data = $this->workupData($data);
        }

        return $data;
    }

    protected function workupData($data)
    {
        $countries = $this->getCountries();
        $regions = $this->getRegions();
        foreach ($data['address:country'] as $sort => $item) {
            $country = $item['value'];
            if (isset($countries[$country])) {
                $data['address:country'][$sort]['loc_value'] = _ws($countries[$country]['name']);
            }
            if (isset($data['address:region'][$sort])) {
                $region = $data['address:region'][$sort]['value'];
                if (isset($regions[$country][$region])) {
                    $data['address:region'][$sort]['loc_value'] = $regions[$country][$region]['name'];
                }
            }
        }
        return $data;
    }


    /**
     * @param array|null $contact
     *      Raw contact-info
     *      @see _nextContact
     * @param array $fields
     *      @see getFields
     * @return array
     */
    protected function exportContact($contact, $fields)
    {
        $data = array();
        $exported = array();

        foreach($fields as $f) {
            $f_id = $f['id'];
            if (!empty($f['subfield'])) {
                $f_id = $f['id'] . ':' . $f['subfield']['id'];
            }

            $data[$f['full_id']] = array();

            switch($f['source']) {
                case 'info':
                    if ($f_id === 'birthday') {
                        $y = $contact['contact']['birth_year'];
                        $m = $contact['contact']['birth_month'];
                        $d = $contact['contact']['birth_day'];
                        if (is_numeric($m) && $m > 0) {
                            $m = $m < 10 ? "0{$m}" : $m;
                        } else {
                            $m = '';
                        }
                        if (is_numeric($d) && $d > 0) {
                            $d = $d < 10 ? "0{$d}" : $d;
                        } else {
                            $d = '';
                        }
                        if (!is_numeric($y) && $y > 0) {
                            $y = '';
                        }
                        $v = implode('.', array($d, $m, $y));
                        $v = $v !== '..' ? $v : '';
                        $data[$f['full_id']] = $v;
                    } else {
                        $v = $contact['contact'][$f_id];
                        if (!empty($f['options']) && isset($f['options'][$v])) {
                            $v = $f['options'][$v];
                        }
                        $data[$f['full_id']] = $v;
                    }
                    break;
                case 'data':
                    if (!empty($contact['data'][$f_id])) {
                        foreach($contact['data'][$f_id] as $val) {
                            if ($val['ext'] === $f['ext'] && !isset($exported['data'][$f_id][$val['sort']])) {
                                if (!empty($f['options']) && isset($f['options'][$val['value']])) {
                                    $v = $f['options'][$val['value']];
                                } else {
                                    $v = isset($val['loc_value']) ? $val['loc_value'] : $val['value'];
                                    if ($f['fld']) {
                                        $v = $f['fld']->format($v, 'value');
                                    }
                                }
                                $data[$f['full_id']][$val['sort']] = $v;
                                $exported['data'][$f_id][$val['sort']] = true;
                            }
                        }
                        // if no ext and no found, search first proper
                        if (!$f['ext']) {
                            foreach($contact['data'][$f_id] as $val) {
                                if (!isset($exported['data'][$f_id][$val['sort']])) {
                                    if (!empty($f['options']) && isset($f['options'][$val['value']])) {
                                        $v = $f['options'][$val['value']];
                                    } else {
                                        $v = isset($val['loc_value']) ? $val['loc_value'] : $val['value'];
                                        if ($f['fld']) {
                                            $v = $f['fld']->format($v, 'value');
                                        }
                                    }
                                    $data[$f['full_id']][$val['sort']] = $v;
                                    $exported['data'][$f_id][$val['sort']] = true;
                                }
                            }
                        }
                    } else if (!empty($contact['data'][$f['full_id']])) {
                        foreach($contact['data'][$f['full_id']] as $val) {
                            if ($val['ext'] === $f['ext'] && !isset($exported['data'][$f_id][$val['sort']])) {
                                if (!empty($f['options']) && isset($f['options'][$val['value']])) {
                                    $v = $f['options'][$val['value']];
                                } else {
                                    $v = isset($val['loc_value']) ? $val['loc_value'] : $val['value'];
                                }
                                $data[$f['full_id']][$val['sort']] = $v;
                                $exported['data'][$f_id][$val['sort']] = true;
                            }
                        }
                        if (!$f['ext']) {
                            foreach($contact['data'][$f['full_id']] as $val) {
                                if (!isset($exported['data'][$f_id][$val['sort']])) {
                                    if (!empty($f['options']) && isset($f['options'][$val['value']])) {
                                        $v = $f['options'][$val['value']];
                                    } else {
                                        $v = isset($val['loc_value']) ? $val['loc_value'] : $val['value'];
                                    }
                                    $data[$f['full_id']][$val['sort']] = $v;
                                    $exported['data'][$f_id][$val['sort']] = true;
                                }
                            }
                        }
                    }
                    break;
                case 'email':
                    foreach($contact['emails'] as $email) {
                        if ($email['ext'] === $f['ext'] && !isset($exported['email'][$email['sort']])) {
                            $data[$f['full_id']][$email['sort']] = $email['email'];
                            $exported['email'][$email['sort']] = true;
                        }
                    }
                    if (!$f['ext']) {
                        foreach ($contact['emails'] as $email) {
                            if (!isset($exported['email'][$email['sort']])) {
                                $data[$f['full_id']][$email['sort']] = $email['email'];
                            }
                        }
                    }
                    break;
                default:
                    break;
            }
        }

        return $data;
    }

    protected function getFields()
    {
        if (self::$fields !== null) {
            return self::$fields;
        }
        return self::$fields = self::obtainFields();
    }

    protected static function obtainFields()
    {
        $fields = array();

        $all_fields = waContactFields::getAll('enabled');
        foreach($all_fields as $field_id => $field) {
            /**
             * @var waContactField $field
             */
            if ($field->getType() === 'Hidden') {
                continue;
            }

            foreach (self::getExts($field) as $ext) {
                $info = self::getFieldInfo($field, $ext);
                if ($field instanceof waContactCompositeField) {
                    foreach ($field->getFields() as $sub_field_id => $sub_field) {
                        $info['full_id'] = $field_id . ':' . $sub_field_id . ($ext ? ".{$ext}" : '');
                        $info['full_name'] = $field->getName() . ($ext ? " - {$ext}" : '') . ': ' . $sub_field->getName();
                        $info['subfield'] = array(
                            'id' => $sub_field_id,
                            'name' => $sub_field->getName()
                        );
                        $fields[] = $info;
                    }
                } else {
                    $fields[] = $info;
                }
            }
        }

        return $fields;
    }

    /**
     * @param waContactField $field
     * @param string
     * @return array
     */
    protected static function getFieldInfo($field, $ext)
    {
        $full_id = $field->getId() . ($ext ? ".{$ext}" : '');
        $full_name = $field->getName() . ($ext ? " - {$ext}" : '');
        $info = array_merge(array(
            'full_id' => $full_id,
            'full_name' => $full_name,
            'source' => $field->getStorage(true),
            'multi' => $field->isMulti(),
            'composite' => $field instanceof waContactCompositeField
        ), $field->getInfo());

        $info['ext'] = $ext;

        $info['parts'] = array();
        if ($field->getId() === 'birthday') {
            $info['parts'] = $field->getParts(true);
        }

        // these fields for which formatting is required
        $info['fld'] = null;
        if ($field->getId() === 'phone') {
            $info['fld'] = $field;
        }

        if (isset($info['fields'])) {
            unset($info['fields']);
        }

        return $info;
    }

    /**
     * @param waContactField $field
     * @return array
     */
    protected static function getExts($field)
    {
        foreach ((array)$field->getParameter('ext') as $ext) {
            if (strlen($ext) > 0) {
                $exts[] = $ext;
            }
        }
        $exts[] = '';
        return $exts;
    }

    protected function getCountries()
    {
        if (self::$countries !== null) {
            return self::$countries;
        }
        $cm = new waCountryModel();
        return self::$countries = $cm->getAll('iso3letter');
    }

    protected function getRegions()
    {
        if (self::$regions !== null) {
            return self::$regions;
        }
        $rm = new waRegionModel();
        foreach ($rm->getAll() as $item) {
            self::$regions[$item['country_iso3']] = ifset(self::$regions[$item['country_iso3']]);
            self::$regions[$item['country_iso3']][$item['code']] = $item;
        }
        return self::$regions;
    }

    /**
     * @return waContactModel
     */
    protected function getContactModel()
    {
        return !empty(self::$models['contact']) ? self::$models['contact'] : (self::$models['contact'] = new waContactModel());
    }

    /**
     * @return waContactEmailsModel
     */
    protected function getContactEmailsModel()
    {
        return !empty(self::$models['contact_emails']) ? self::$models['contact_emails'] : (self::$models['contact_emails'] = new waContactEmailsModel());
    }

    /**
     * @return waContactDataModel
     */
    protected function getContactDataModel()
    {
        return !empty(self::$models['contact_data']) ? self::$models['contact_data'] : (self::$models['contact_data'] = new waContactDataModel());
    }

    /**
     * @return crmContactsCollection
     */
    protected function getCollection()
    {
        return $this->collection !== null ? $this->collection :
            ($this->collection = new crmContactsCollection($this->getHash()));
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
        $this->setCacheVar('offset', $offset);
    }

    public function isExportDone()
    {
        return (bool)$this->getCacheVar('is_export_done');
    }

    protected function markExportAsDone()
    {
        $this->setCacheVar('is_export_done', 1);
    }

    /**
     * @return string
     */
    protected function getHash()
    {
        return (string)$this->getCacheVar('hash');
    }

    /**
     * @param string $hash
     */
    protected function setHash($hash)
    {
        $hash = (string)$hash;
        if ($this->getHash() !== $hash) {
            $this->setCacheVar('hash', $hash);
        }
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
                $saved_result[$field_id]['repetition'] = 1;
                // for sort '0', all values is empty
                $saved_result[$field_id]['is_empty_column'][0] = true;
                $saved_result[$field_id]['data'] = array();
            }
        }

        foreach ($result as $field_id => $res) {
            foreach ($res['data'] as $contact_id => $values) {
                if (is_array($values)) {
                    $count = count($values);
                    $saved_result[$field_id]['repetition'] = max($saved_result[$field_id]['repetition'], $count);
                }

                foreach ((array)$values as $sort => $value) {
                    $value = (string)$value;
                    if (strlen($value) > 0) {
                        $saved_result[$field_id]['is_empty_column'][$sort] = false;
                    }
                    $saved_result[$field_id]['data'][$contact_id][$sort] = $value;
                }
            }
        }

        $this->setCacheVar('result', $saved_result, true);
    }

    /**
     * @return array
     */
    protected function getSavedResult()
    {
        return (array)$this->getCacheVar('result', true);
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
}
