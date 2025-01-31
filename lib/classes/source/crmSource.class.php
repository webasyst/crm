<?php

/**
 * Class crmSource
 */
abstract class crmSource
{
    protected static $static_cache;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $info;

    /**
     * @var array
     */
    protected $cache;

    /**
     * @var string
     */
    protected $provider;

    /**
     * Constructor must be public, so plugin can factory own source instance by new operator
     * crmSource constructor.
     * @param int $id
     * @param $options
     * @throws waException
     */
    public function __construct($id = null, $options = array())
    {
        $this->id = (int)$id;

        if (isset($options['type'])) {
            if (!$this->type) {
                $this->type = $options['type'];
            }
            unset($options['type']);
        }

        if (isset($options['provider'])) {
            if (!$this->provider) {
                $this->provider = $options['provider'];
            }
            unset($options['provider']);
        }

        if (!$this->type && !($this instanceof crmNullSource) && $this->id > 0) {
            $type = self::getSourceModel()->select('type')->where('id = ?', $id)->fetchField();
            $this->type = $type;
            if (!$type) {
                throw new crmSourceException(
                    sprintf("Couldn't factor source instance: unknown type %s", $this->type ? $this->type : 'NULL')
                );
            }
        }

        $this->options = $options;
    }

    /**
     * @param string|int|array $id
     * @param array $options
     * @return crmSource
     */
    public static function factory($id, array $options = array())
    {
        if (waConfig::get('is_template')) {
            return null;
        }

        $type = null;
        $provider = null;
        if (is_array($id)) {
            $type = $id['type'];
            $provider = $id['provider'];
            $id = $id['id'];
        } else {
            $type = strtolower((string)$id);
        }

        if (wa_is_int($id) && $id > 0 && (empty($type) || empty($provider))) {
            $res = self::getSourceModel()->select('type, provider')->where('id = ?', $id)->fetchAssoc();
            if ($res) {
                $type = strtolower($res['type']);
                $provider = $res['provider'] ? strtolower($res['provider']) : null;
            }
        }

        if (!$provider && ($type === 'email' || $type === 'pop' || $type === 'pop3')) {
            $type = 'email';
            $provider = 'pop3';
        }

        $app_types = crmSourceModel::getTypes();
        $app_types = array_keys($app_types);
        $app_types = array_map('strtolower', array_map('trim', $app_types));
        $app_types = array_fill_keys($app_types, true);

        if ($type && isset($app_types[$type])) {
            $class_name = 'crm{provider}{type}Source';
            $class_name = str_replace('{provider}', $provider ? ucfirst($provider) : '', $class_name);
            $class_name = str_replace('{type}', ucfirst($type), $class_name);
            $instance = self::factoryInstance($class_name, $id, $options);
            if ($instance) {
                return $instance;
            }
        }

        $plugin_id = $provider ? $provider : $type;
        $plugin = crmSourcePlugin::factory($plugin_id);
        if ($plugin) {
            $instance = $plugin->factorySource($id, $options);
            return $instance ? $instance : new crmNullSource($id, $options);
        }

        return new crmNullSource($id, $options);
    }

    protected static function factoryInstance($class_name, $id, $options = array())
    {
        if (class_exists($class_name)) {
            $object = new $class_name($id, $options);
            if ($object instanceof crmSource) {
                return $object;
            }
        }
        return null;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getIcon()
    {
        $type = $this->getType();
        $icons = crmSourceModel::getIcons();
        $icon = ifset($icons[$type]);
        if (!$icon) {
            return;
        }
        return wa()->getAppStaticUrl('crm', true) . 'img/source/' . $icon;
    }
    
    /**
     * Returns Font Awesome icon name from fab scope and color
     * as array with keys icon_fab & icon_color
     * @return array
     */
    public function getFontAwesomeBrandIcon()
    {
        return [
            'icon_fab' => null,
            'icon_color' => null,
        ];
    }

    /**
     * @return string
     */
    public function getType()
    {
        if ($this->type) {
            return $this->type;
        }
        $info = $this->obtainInfo();
        return $this->type = (string)ifset($info['type']);
    }

    /**
     * @return string|null
     */
    public function getProvider()
    {
        if ($this->provider) {
            return $this->provider;
        }
        $info = $this->obtainInfo();
        return $this->provider = ifset($info['provider']);
    }

    /**
     * @return string|null
     */
    public function getProviderName()
    {
        return $this->getProvider();
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return $this->obtainInfo();
    }

    /**
     * @return int
     */
    public function getFunnelId()
    {
        $info = $this->obtainInfo();
        return (int)$info['funnel_id'];
    }

    /**
     * Set funnel id in runtime cache only
     * @param int $funnel_id
     */
    public function setFunnelId($funnel_id)
    {
        $this->obtainInfo();
        $this->info['funnel_id'] = is_scalar($funnel_id) ? (int)$funnel_id : null;
    }

    /**
     * Save funnel_id into DB right away
     * @param int $funnel_id
     */
    public function saveFunnelId($funnel_id)
    {
        $funnel_id = is_scalar($funnel_id) ? (int)$funnel_id : null;
        $this->save(array('funnel_id' => $funnel_id));
    }

    /**
     * @return int
     */
    public function getStageId()
    {
        $info = $this->obtainInfo();
        return (int)$info['stage_id'];
    }

    /**
     * Set stage id in runtime cache only
     * @param int $stage_id
     */
    public function setStageId($stage_id)
    {
        $this->obtainInfo();
        $this->info['stage_id'] = is_scalar($stage_id) ? (int)$stage_id : 0;
    }

    /**
     * Save stage_id into DB right away
     * @param int $stage_id
     */
    public function saveStageId($stage_id)
    {
        $stage_id = is_scalar($stage_id) ? (int)$stage_id : 0;
        $this->save(array('stage_id' => $stage_id));
    }

    /**
     * @return int|null
     */
    public function getResponsibleContactId()
    {
        $info = $this->obtainInfo();
        $responsible_contact_id = $info['responsible_contact_id'];
        if (empty($responsible_contact_id)) {
            return null;
        } else {
            return (int)$responsible_contact_id;
        }
    }

    /**
     * @return int
     */
    public function getNormalizedResponsibleContactId()
    {
        $dm = self::getDealModel();
        $contact_id = null;
        $responsible_contact_id = $this->getResponsibleContactId();
        if ($responsible_contact_id > 0) {
            $contact_id = $responsible_contact_id;
        } elseif ($responsible_contact_id < 0) {
            $responsible_user_id = $dm->getResponsibleUserOfGroup(-$responsible_contact_id);
            if ($responsible_user_id > 0) {
                $contact_id = $responsible_user_id;
            }
        }
        return (int)$contact_id;
    }

    /**
     * Set value in runtime cache only
     * @param int|null $responsible_contact_id
     */
    public function setResponsibleContactId($responsible_contact_id)
    {
        $this->obtainInfo();
        $this->info['responsible_contact_id'] = null;
        if (is_scalar($responsible_contact_id)) {
            $this->info['responsible_contact_id'] = (int)$responsible_contact_id;
        }
    }

    /**
     * Save value into DB right away
     * @param int|null $responsible_contact_id
     */
    public function saveResponsibleContactId($responsible_contact_id)
    {
        if (is_scalar($responsible_contact_id)) {
            $responsible_contact_id = (int)$responsible_contact_id;
        } else {
            $responsible_contact_id = null;
        }
        $this->save(array('responsible_contact_id' => $responsible_contact_id));
    }

    /**
     * @param bool $actual
     * @return array
     */
    public function getParams($actual = false)
    {
        if (!$actual) {
            $info = $this->obtainInfo();
            return $info['params'];
        }
        $params = self::getSourceParamsModel()->get($this->getId());
        $params = self::unserializeParams($params);

        $res = $this->workupParams($params);
        if (is_array($res)) {
            $params = $res;
        }

        if ($this->info !== null) {
            $this->info['params'] = $params;
        }
        return $params;
    }

    /**
     * @return string
     */
    public function getName()
    {
        $info = $this->obtainInfo();
        return $info['name'];
    }

    /**
     * Set name in runtime cache
     * @param $name
     */
    public function setName($name)
    {
        $this->obtainInfo();
        $this->info['name'] = is_scalar($name) ? (string)$name : '';
    }

    /**
     * Save name into DB right away
     * @param $name
     */
    public function saveName($name)
    {
        $name = is_scalar($name) ? (string)$name : '';
        $this->save(array('name' => $name));
    }

    /**
     * @param string $key
     * @param mixed $default Scalar or null
     * @param bool $actual
     * @return mixed Scalar or null
     */
    public function getParam($key, $default = null, $actual = false)
    {
        if (!is_scalar($key)) {
            return $default;
        }
        if (!$actual) {
            $info = $this->obtainInfo();
            return array_key_exists($key, $info['params']) ? $info['params'][$key] : $default;
        }
        $value = self::getSourceParamsModel()->getOne($this->getId(), $key);
        if ($value === null) {
            return $default;
        }

        $params = self::serializeParams(array($key => $value));

        $res = $this->workupParams($params);
        if (is_array($res)) {
            $params = $res;
        }

        $value = isset($params[$key]) ? $params[$key] : $default;

        if ($this->info !== null) {
            $this->info['params'] = (array)ifset($this->info['params']);
            $this->info['params'][$key] = $value;
        }
        return $value;
    }

    /**
     * @param array[string]mixed $params
     * @param bool $unset_old_params
     */
    public function setParams($params, $unset_old_params = true)
    {
        if (!is_array($params)) {
            return;
        }
        $this->obtainInfo();
        if ($unset_old_params) {
            $this->info['params'] = $params;
        } else {
            foreach ($params as $name => $val) {
                $this->info['params'][$name] = $val;
            }
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setParam($name, $value)
    {
        $this->obtainInfo();
        if (is_scalar($name)) {
            $this->info['params'][$name] = $value;
        }
    }

    /**
     * @param array[string]mixed $params
     * @param bool $delete_old_params
     */
    public function saveParams($params, $delete_old_params = true)
    {
        if (!is_array($params)) {
            return;
        }
        $this->save(array('params' => $params), $delete_old_params);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function saveParam($key, $value)
    {
        if (is_scalar($key)) {
            $this->save(array('params' => array($key => $value)));
        }
    }

    /**
     * Reset params in runtime, not in DB
     */
    public function resetParams()
    {
        if ($this->info) {
            $this->info['params'] = array();
        }
    }

    /**
     * Unset param in runtime, not in DB
     * @param string $name
     */
    public function unsetParam($name)
    {
        if (!is_scalar($name) || !$this->info) {
            return;
        }
        if (array_key_exists($name, $this->info['params'])) {
            unset($this->info['params'][$name]);
        }
    }

    /**
     * Delete params in DB
     */
    public function deleteParams()
    {
        if ($this->exists()) {
            $this->saveParams(array());
        } else {
            $this->resetParams();
        }
    }

    /**
     * Delete param in DB
     * @param string $key
     */
    public function deleteParam($key)
    {
        if (!is_scalar($key)) {
            return;
        }
        if ($this->exists()) {
            $this->saveParams(array($key => null), false);
        } else {
            $this->unsetParam($key);
        }
    }

    /**
     * Runtime property will not go to DB in any case
     * @param $name
     * @param mixed $default
     * @return mixed
     */
    public function getProperty($name, $default = null)
    {
        if (!is_scalar($name)) {
            return $default;
        }
        $this->cache['properties'] = (array)ifset($this->cache['properties']);
        $name = (string)$name;
        return array_key_exists($name, $this->cache['properties']) ? $this->cache['properties'][$name] : $default;
    }

    /**
     * Runtime property will not go to DB in any case
     * @param string $name
     * @param mixed $value
     */
    public function setProperty($name, $value)
    {
        if (!is_scalar($name)) {
            return;
        }
        $this->cache['properties'] = (array)ifset($this->cache['properties']);
        $name = (string)$name;
        $this->cache['properties'][$name] = $value;
    }

    /**
     * Runtime property will not go to DB in any case
     * @param string $name
     */
    public function deleteProperty($name)
    {
        if (!is_scalar($name)) {
            return;
        }
        $name = (string)$name;
        if (isset($this->cache['properties'][$name])) {
            unset($this->cache['properties'][$name]);
        }
    }

    /**
     * @return array
     */
    protected function obtainInfo()
    {
        if ($this->info) {
            return $this->info;
        }

        $source = self::getSourceModel()->getSource($this->id);
        if (!$source) {
            $source = self::getSourceModel()->getEmptySourceOfType($this->type);
        }

        if ($this->provider) {
            $source['provider'] = $this->provider;
        }

        // workup source-info
        $res = $this->workupInfo($source);

        // in case if child class unset crm_source keys we restore it (they are obligatory)
        if (is_array($res)) {
            $source = array_merge($source, $res);
        }

        $source['params'] = self::unserializeParams((array)ifset($source['params']));

        // workup params array
        $res = $this->workupParams($source['params']);
        if (is_array($res)) {
            $source['params'] = $res;
        }

        $this->info = $source;
        $this->id = $this->info['id'];

        return $this->info;
    }

    /**
     * Template method to workup info array just now obtained from DB table crm_source
     * @param $info
     * @return mixed
     */
    protected function workupInfo($info)
    {
        return $info;
    }

    /**
     * Template method to workup params just now obtained from DB table crm_source_params
     * @param $params
     * @return mixed
     */
    protected function workupParams($params)
    {
        return $params;
    }

    /**
     * Exists source record in DB
     * @return bool
     */
    public function exists()
    {
        $info = $this->obtainInfo();
        return $info['id'] > 0;
    }

    /**
     * Delete source record form DB
     */
    public function delete()
    {
        if (!$this->exists()) {
            return;
        }
        self::getSourceModel()->delete($this->id);
        $this->info = null;
    }

    /**
     * @return bool
     */
    public function isDisabled()
    {
        $info = $this->obtainInfo();
        return !!$info['disabled'];
    }

    /**
     * Disable source in runtime
     */
    public function setAsDisabled()
    {
        $this->obtainInfo();
        $this->info['disabled'] = 1;
    }

    /**
     * Disable source in DB right away
     */
    public function saveAsDisabled()
    {
        $this->save(array('disabled' => 1));
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return !$this->isDisabled();
    }

    /**
     * Enable source in runtime
     */
    public function setAsEnabled()
    {
        $this->obtainInfo();
        $this->info['disabled'] = 0;
    }

    /**
     * Enable source in DB right away
     */
    public function saveAsEnabled()
    {
        $this->save(array('disabled' => 0));
    }

    /**
     * Save (update or insert) date into DB
     * @param array $data array of fields of records + 'params' key for save params
     * @param bool $delete_old_params Delete old param values or not
     */
    public function save($data, $delete_old_params = false)
    {
        $res = $this->workupDataBeforeSave($data);
        if (is_array($res)) {
            $data = $res;
        }
        if (!$data && !is_array($data)) {
            return;
        }

        $info = $this->obtainInfo();
        $info_params = $info['params'];
        unset($info['params']);

        $data_params = (array)ifset($data['params']);
        $data['params'] = $data_params;
        unset($data['params']);

        $data = array_merge($info, $data);

        if (!$delete_old_params) {
            $data_params = array_merge($info_params, $data_params);
        }

        $data_params = self::serializeParams($data_params);
        $data['params'] = $data_params;

        if (!$this->exists()) {
            $type = isset($data['type']) ? $data['type'] : $this->getType();
            $this->id = self::getSourceModel()->add($type, $data);
        } else {
            self::getSourceModel()->update($this->id, $data, $delete_old_params);
        }

        $this->info = null;
    }

    /**
     * Save info into DB
     * @example
     * $p->setParam('key_1', 'value_1');
     * $p->setParam('key_2', 'value_2');
     * $p->setName('super');
     * ...
     * $p->commit()
     *
     */
    public function commit()
    {
        // save fresh info
        $info = $this->obtainInfo();
        // reset info, so we can have only DB variant
        $this->info = null;
        $this->save($info, true);
    }

    /**
     * Is ok for working
     * @override
     * @return bool
     */
    public function canWork()
    {
        return $this->exists(); // && $this->isEnabled();
    }

    /**
     * For that sources that need to test connection
     * It connection isn't supported by source, just ignore this method
     * @override
     * @return array errors if connection is failed
     */
    public function testConnection()
    {
        return array();
    }

    /**
     * @override
     * @return array
     */
    public function getConnectionParams()
    {
       return array();
    }

    /**
     * @override
     * @param $params
     */
    public function setConnectionParams($params)
    {

    }

    /**
     * @override
     * @param $params
     */
    public function saveConnectionParams($params)
    {

    }


    /**
     * @param array $deal
     * @return bool|int
     */
    public function createDeal($deal = array())
    {
        if ($this->isDisabled()) {
            return false;
        }

        if (!$this->exists()) {
            return false;
        }

        if ($this->getType() !== crmSourceModel::TYPE_SHOP &&
            !$this->getParam('create_deal')) {
            return false;
        }

        $deal = $this->prepareDealBeforeCreate($deal);
        if (!$deal) {
            return false;
        }

        if (empty($deal['contact_id'])) {
            return false;
        }

        $contact_id = (int)$deal['contact_id'];
        $cm = new waContactModel();
        $is_user = $cm->select('is_user')->where('id = ?', $contact_id)->fetchField();
        if ($is_user == -1) {
            return false;
        }
        $id = self::getDealModel()->add($deal);
        if ($id <= 0) {
            return false;
        }

        return $id;
    }

    /**
     * Source must be crete deal after all, so this method for prepare deal array
     * @param array $deal
     * @return null|array If method failed return null
     */
    protected function prepareDealBeforeCreate($deal = array())
    {
        if (!$this->exists()) {
            return null;
        }

        if (!isset($deal['funnel_id'])) {
            $deal['funnel_id'] = $this->getFunnelId();
        }

        if ($deal['funnel_id'] > 0) {
            if (!isset($deal['stage_id'])) {
                $deal['stage_id'] = $this->getStageId();
            }
            $deal['stage_id'] = (int)$deal['stage_id'];

            if (!self::getFunnelModel()->getById($deal['funnel_id'])) {
                return null;
            }
            $stage = self::getStageModel()->getById($deal['stage_id']);
            if (!$stage || $stage['funnel_id'] != $deal['funnel_id']) {
                return null;
            }
        }

        if ($deal['funnel_id'] <= 0) {
            $deal['funnel_id'] = null;
            $deal['stage_id'] = null;
        }

        if (!isset($deal['name'])) {
            $deal['name'] = $this->getName();
        }
        $cm = new waContactModel();
        $crm_user_id = $cm->select('crm_user_id')->where('id = ?', $deal['contact_id'])->fetchField();
        $deal['user_contact_id'] = ($crm_user_id > 0 ? $crm_user_id : $this->getNormalizedResponsibleContactId());
        $deal['user_contact_id'] = $deal['user_contact_id'] > 0 ? $deal['user_contact_id'] : null;

        $deal['source_id'] = $this->getId();

        return $deal;
    }

    public function createMessage($message = array(), $direction = crmMessageModel::DIRECTION_IN)
    {
        if ($this->isDisabled()) {
            return false;
        }

        if (!$this->exists()) {
            return false;
        }

        if ($direction != crmMessageModel::DIRECTION_OUT) {
            $direction = crmMessageModel::DIRECTION_IN;
        }

        $message = $this->prepareMessageBeforeCreate($message, $direction);
        $message['direction'] = $direction;

        if ($direction == crmMessageModel::DIRECTION_IN) {
            if (empty($message['contact_id'])) {
                return false;
            }
            $contact_id = (int)$message['contact_id'];
            $cm = new waContactModel();
            $is_user = $cm->select('is_user')->where('id = ?', $contact_id)->fetchField();
            if ($is_user == -1) {
                return false;
            }
        }

        if (!$message) {
            return false;
        }
        $message['source_id'] = $this->getId();

        if (!empty($message['subject']) && !crmMessageModel::isColumnMb4('subject')) {
            $message['subject'] = crmHelper::removeEmoji($message['subject']);
        }
        if (!empty($message['body']) && !crmMessageModel::isColumnMb4('body')) {
            $message['body'] = crmHelper::removeEmoji($message['body']);
        }

        if (isset($message['params']) && is_array($message['params'])) {
            $is_mb4 = crmMessageParamsModel::isColumnMb4('value');
            if (!$is_mb4) {
                foreach ($message['params'] as $param_name => &$param_value) {
                    if (is_scalar($param_value)) {
                        $param_value = (string)$param_value;
                        $param_value = crmHelper::removeEmoji($param_value);
                    }
                }
                unset($param_value);
            }
        }

        $mm = new crmMessageModel();

        // Fix in wa log? and if yes with what params
        $wa_log = false;
        if (isset($message['direction']) && $message['direction'] == crmMessageModel::DIRECTION_OUT) {
            $wa_log = array(
                'source_info' => array(
                    'type' => $this->getType(),
                    'provider' => $this->getProvider(),
                    'provider_name' => $this->getProviderName(),
                ),
            );
        }

        $message_id = $mm->fix($message, array(
            'wa_log' => $wa_log
        ));

        if ($message_id <= 0) {
            return false;
        }

        // attach files to message
        if (isset($message['attachments'])) {
            $file_ids = array_unique($message['attachments']);
            $mm->setAttachments($message_id, $file_ids);
        }

        if (isset($message['deal_id']) && $message['deal_id'] > 0) {
            self::getDealModel()->updateById($message['deal_id'], array('last_message_id' => $message_id));
        }

        return $message_id;
    }

    /**
     * @override
     * @param array $default
     * @param string $direction
     * @return array
     */
    protected function prepareMessageBeforeCreate($default = array(), $direction = crmMessageModel::DIRECTION_IN)
    {
        return $default;
    }

    /**
     * @param int|int[] $contact_ids
     */
    public function addContactsToSegments($contact_ids)
    {
        $segment_ids = $this->getParam('segments');
        $segment_ids = crmHelper::toIntArray($segment_ids);
        $segment_ids = crmHelper::dropNotPositive($segment_ids);
        if (!$segment_ids) {
            return;
        }
        $contact_ids = crmHelper::toIntArray($contact_ids);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$contact_ids) {
            return;
        }

        $cm = new waContactModel();
        $contact_ids = $cm->select('id')
            ->where('id IN (:ids) AND is_user != -1', array(
                'ids' => $contact_ids
            ))
            ->fetchAll(null, true);

        if (!$contact_ids) {
            return;
        }

        $wcc = new waContactCategoriesModel();
        $wcc->add($contact_ids, $segment_ids);
    }

    /**
     * @deprecated
     * Need only for compatibility with old plugins for app version < 1.4.2
     * Use crmHelper::removeEmoji instead
     * @param $string
     * @return mixed
     */
    public static function removeEmoji($string)
    {
        return crmHelper::removeEmoji($string);
    }


    /**
     * Template method to workup inputs array - data and info record - before save into DB
     * @param array $data
     * @param bool
     * @return array - first item is worked up data array, second item is worked up info array
     */
    protected function workupDataBeforeSave($data)
    {
        return $data;
    }

    /**
     * Serialize params array
     * @param $params
     * @return mixed
     */
    protected static function serializeParams($params)
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = @serialize($value);
            }
        }
        return $params;
    }

    /**
     * Unserialize params array
     * @param $params
     * @return mixed
     */
    protected static function unserializeParams($params)
    {
        foreach ($params as $key => $value) {
            if (preg_match('/^a:\d+?:\{/', $value)) {
                $res = @unserialize($value);
                if (is_array($res)) {
                    $params[$key] = $res;
                }
            }
        }
        return $params;
    }

    /**
     * @return crmSourceModel
     */
    protected static function getSourceModel()
    {
        if (empty(self::$static_cache['models']['source'])) {
            self::$static_cache['models']['source'] = new crmSourceModel();
        }
        return self::$static_cache['models']['source'];
    }

    /**
     * @return crmMessageModel
     */
    protected static function getMessageModel()
    {
        if (empty(self::$static_cache['models']['message'])) {
            self::$static_cache['models']['message'] = new crmMessageModel();
        }
        return self::$static_cache['models']['message'];
    }

    /**
     * @return crmMessageParamsModel
     */
    protected static function getMessageParamsModel()
    {
        if (empty(self::$static_cache['models']['message_params'])) {
            self::$static_cache['models']['message_params'] = new crmMessageParamsModel();
        }
        return self::$static_cache['models']['message_params'];
    }

    /**
     * @return crmConversationModel
     */
    protected static function getConversationModel()
    {
        if (empty(self::$static_cache['models']['conversation'])) {
            self::$static_cache['models']['conversation'] = new crmConversationModel();
        }
        return self::$static_cache['models']['conversation'];
    }

    /**
     * @return crmSourceParamsModel
     */
    protected static function getSourceParamsModel()
    {
        if (empty(self::$static_cache['models']['source_params'])) {
            self::$static_cache['models']['source_params'] = new crmSourceParamsModel();
        }
        return self::$static_cache['models']['source_params'];
    }

    /**
     * @return crmDealModel
     */
    protected static function getDealModel()
    {
        if (empty(self::$static_cache['models']['deal'])) {
            self::$static_cache['models']['deal'] = new crmDealModel();
        }
        return self::$static_cache['models']['deal'];
    }

    /**
     * @return crmFunnelModel
     */
    protected static function getFunnelModel()
    {
        if (empty(self::$static_cache['models']['funnel'])) {
            self::$static_cache['models']['funnel'] = new crmFunnelModel();
        }
        return self::$static_cache['models']['funnel'];
    }

    /**
     * @return crmFunnelStageModel
     */
    protected static function getStageModel()
    {
        if (empty(self::$static_cache['models']['stage'])) {
            self::$static_cache['models']['stage'] = new crmFunnelStageModel();
        }
        return self::$static_cache['models']['stage'];
    }

}
