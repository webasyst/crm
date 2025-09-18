<?php

class crmForm
{
    protected static $static_cache;

    /**
     * @var int
     */
    protected $id;

    protected $hash;

    /**
     * @var array
     */
    protected $info;

    /**
     * @var array
     */
    protected $options;

    const MESSAGE_TO_VARIANT_CLIENT = 'client';
    const MESSAGE_TO_VARIANT_RESPONSIBLE_USER = 'responsible_user';

    const TYPE_SIGN_UP = 'sign_up';
    const TYPE_ADDING = 'adding';

    /**
     * @var array
     */
    protected static $message_to_variants;

    /**
     * @var array
     */
    protected static $message_template_vars;

    /**
     * For store arbitrary properties in runtime (mostly for cashing)
     * @var array
     */
    protected $properties;

    /**
     * crmForm constructor.
     * @param int|null $id
     */
    public function __construct($id = null)
    {
        $this->id = is_scalar($id) ? (int)$id : 0;
    }

    public function getInfo()
    {
        $info = $this->obtainInfo();

        foreach ($info as $key => $value) {
            if (substr($key, 0, 2) === '__') {
                unset($info[$key]);
            }
            if ($key === 'params') {
                foreach ((array) $value as $k => $v) {
                    if (substr($k, 0, 2) === '__') {
                        unset($info['params'][$k]);
                    }
                }
            }
        }
        return $info;
    }

    public function getHash()
    {
        if (empty($this->id)) {
            return null;
        }
        if ($this->hash === null) {
            $hash = md5($this->obtainInfo()['create_datetime'] . $this->id);
            $this->hash = substr($hash, 0, 16) . $this->id . substr($hash, -16);
        }
        return $this->hash;
    }

    /**
     * @return string
     */
    public function getType()
    {
        $info = $this->obtainInfo();
        return $info['__type'];
    }

    public function getMessages()
    {
        $info = $this->obtainInfo();
        return $info['params']['__messages'];
    }

    /**
     * Set arbitrary runtime property
     * @param string $name
     * @param mixed $value
     */
    public function setProperty($name, $value)
    {
        $this->properties[$name] = $value;
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function getProperty($name)
    {
        return isset($this->properties[$name]) ? $this->properties[$name] : null;
    }

    /**
     * @return array
     */
    protected function obtainInfo()
    {
        if ($this->info !== null) {
            return $this->info;
        }

        $this->info = self::getFormModel()->getForm($this->id);
        if (!$this->info) {
            $this->info = self::getFormModel()->getEmptyRow();
            $this->info['params'] = [
                'html_after_submit' => $this->getDefaultHtmlAfterSubmit(),
                'after_antispam_confirm_text' => $this->getDefaultAfterAntispamConfirmText(),
            ];
        } else {
            $this->info['params'] = self::unserializeParams(self::getFormParamsModel()->get($this->id));
        }

        $this->info['params']['fields'] = (array)ifset($this->info['params']['fields']);
        $this->info['params']['fields'] = array_values($this->info['params']['fields']);

        if ($this->searchField($this->info['params']['fields'], 'password') !== false) {
            $this->info['__type'] = self::TYPE_SIGN_UP;
        } else {
            $this->info['__type'] = self::TYPE_ADDING;
        }

        $this->info['params']['source_id'] = (int)ifset($this->info['params']['source_id']);
        $this->info['__source'] = new crmFormSource($this->info['params']['source_id']);

        $this->info['params']['messages'] = (array)ifset($this->info['params']['messages']);
        foreach ($this->info['params']['messages'] as &$message) {
            if (!isset($message['tmpl'])) {
                $message['tmpl'] = self::getDefaultMessageMailTemplate();
            }
        }
        unset($message);

        $this->info['params']['__messages'] = $this->formatMessagesArray($this->info['params']['messages']);

        $counters = array();
        foreach ($this->info['params']['fields'] as &$field) {
            if (!isset($field['id'])) {
                continue;
            }
            if (!isset($counters[$field['id']])) {
                $counters[$field['id']] = 0;
            } else {
                $counters[$field['id']] += 1;
            }
            $suffix = '';
            if ($counters[$field['id']] >= 1) {
                $suffix = "_{$counters[$field['id']]}";
            }
            $field['uid'] = $field['id'] . $suffix;
        }
        unset($field);

        $this->info['params']['__fields'] = array();
        foreach ($this->info['params']['fields'] as $field) {
            if (isset($field['uid'])) {
                $this->info['params']['__fields'][$field['uid']] = $field;
            }
        }


        return $this->info;
    }

    protected function getDefaultHtmlAfterSubmit()
    {
        $root_uri = wa()->getRootUrl();
        $text = _w('Thanks for subscribing!');
        return <<<HTML
<p style="text-align: center"><img src="{$root_uri}wa-apps/crm/img/success.svg"></p>
<p style="text-align: center">{$text}</p>
HTML;
    }

        protected function getDefaultAfterAntispamConfirmText()
    {
        $root_uri = wa()->getRootUrl();
        $text = _w('Confirmed');
        return <<<HTML
<p style="text-align: center"><img src="{$root_uri}wa-apps/crm/img/success.svg"></p>
<p style="text-align: center">{$text}</p>
HTML;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        $info = $this->getInfo();
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

    public function getContactId()
    {
        $info = $this->obtainInfo();
        return $info['contact_id'];
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
        $params = self::getFormParamsModel()->get($this->getId());
        $params = self::unserializeParams($params);
        if ($this->info !== null) {
            $this->info['params'] = $params;
        }
        return $params;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        $info = $this->obtainInfo();
        return $info['params']['fields'];
    }

    public function getAgreementCheckboxes()
    {
        return array_filter($this->getFields(), function ($field) {
            return strpos($field['id'], '!agreement_checkbox') === 0;
        });
    }

    /**
     * @param $uid
     * @return mixed
     */
    public function getFieldByUid($uid)
    {
        $info = $this->obtainInfo();
        return ifset($info['params']['__fields'][$uid]);
    }

    /**
     * @param $uid
     * @return bool
     */
    public function isFieldPresented($uid)
    {
        return $this->getFieldByUid($uid) !== null;
    }

    /**
     * @return bool
     */
    public function isCaptchaPresented()
    {
        return $this->isFieldPresented('!captcha');
    }

    public function isEmailPresented()
    {
        return $this->isFieldPresented('email');
    }

    /**
     * @return crmFormSource
     */
    public function getSource()
    {
        $info = $this->obtainInfo();
        return $info['__source'];
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
        $value = self::getFormParamsModel()->getOne($this->getId(), $key);
        if ($value === null) {
            return $default;
        }

        $params = self::serializeParams(array($key => $value));

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
     * Save (update or insert) date into DB
     * @param array $data array of fields of records + 'params' key for save params
     * @param bool $delete_old_params Delete old param values or not
     */
    public function save($data, $delete_old_params = false)
    {
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

        // 'source_id' is always presented, const param
        $data_params['source_id'] = (int)ifset($info_params['source_id']);

        /**
         * @event form_settings_save
         * @param array $data
         * @return void
         */
        wa('crm')->event('form_settings_save', $data_params);

        $data_params = self::serializeParams($data_params);
        $data['params'] = $data_params;

        // unset special fields with '__' prefix
        foreach ($data['params'] as $key => $value) {
            if (substr($key, 0, 2) === '__') {
                unset($data['params'][$key]);
            }
        }

        if (!$this->exists()) {
            $this->id = self::getFormModel()->add($data);
        } else {
            self::getFormModel()->update($this->id, $data, $delete_old_params);
        }

        $this->saveSource($data);

        $this->info = null;
    }

    protected function saveSource($data)
    {
        $info = $this->getInfo();

        $data['params'] = (array)ifset($data['params']);

        if (array_key_exists('source_id', $data['params'])) {
            $source = new crmFormSource($this->info['params']['source_id']);
        } else {
            $source = $this->getSource();
        }

        $source_info = array(
            'name' => '',
            'params' => array(
                'form_id' => $this->getId()
            )
        );
        if (isset($data['name'])) {
            $source_info['name'] = $data['name'];
        } else {
            $source_info['name'] = $info['name'];
        }

        if (isset($data['source']) && is_array($data['source'])) {
            foreach ($data['source'] as $key => $value) {
                if ($key === 'params') {
                    $source_info['params'] = array_merge($source_info['params'], is_array($value) ? $value : array());
                } else {
                    $source_info[$key] = $value;
                }
            }
        }

        $source->save($source_info, false);

        if (!empty($info['params']['create_deal'])) {
            $source->saveAsEnabled();
        } else {
            $source->saveAsDisabled();
        }

        $this->info['params']['source_id'] = $source->getId();
        self::getFormParamsModel()->setOne($this->getId(), 'source_id', $source->getId());
    }

    public static function getDefaultMessageMailTemplate()
    {
        if (wa()->getLocale() == 'ru_RU') {
            return '{SEPARATOR}'.
                '<p>Мы ответим вам в ближайшее время.</p>'.
                '<p>Спасибо!</p>'.
                '<p>--</p>'.
                '<p>{$company_name}</p>';
        }
        return '{SEPARATOR}'.
            '<p>We shall reply to you as soon as possible.</p>'.
            '<p>Thank you!</p>'.
            '<p>--</p>'.
            '<p>{$company_name}</p>';
    }

    /**
     * @return array
     */
    public static function getMessageTemplateVars()
    {
        $vars = array(
            '$original_text' => _w('“Text” field contents'),
            '$company_name' => _w('Company name specified in your Installer settings (also displayed in the top-left corner of your backend)'),
        );

        return array_merge($vars, crmHelper::getVarsForContact());
    }

    protected function formatMessagesArray($messages)
    {
        $contact_ids = array();
        foreach ($messages as $message) {
            if (!empty($message['to'])) {
                foreach ($message['to'] as $id => $flag) {
                    if (!self::isMessageToVariant($id)) {
                        $contact_ids[] = $id;
                    }
                }
            }
        }
        $contact_ids = array_unique($contact_ids);
        if (!$contact_ids) {
            return $messages;
        }
        $col = new waContactsCollection('id/' . join(',', $contact_ids));
        $contacts = $col->getContacts('id,name,firstname,lastname,middlename,email');
        foreach ($contacts as &$contact) {
            $contact['name'] = waContactNameField::formatName($contact);
        }
        unset($contact);

        foreach ($messages as &$message) {
            if (!empty($message['to'])) {
                foreach ($message['to'] as $id => $flag) {
                    if (!self::isMessageToVariant($id) && isset($contacts[$id])) {
                        $message['to'][$id] = $contacts[$id]['name'];
                    }
                }
            }
        }
        unset($message);

        return $messages;
    }

    public static function getMessageToVariants()
    {
        if (self::$message_to_variants) {
            return self::$message_to_variants;
        }
        self::$message_to_variants = array(
            self::MESSAGE_TO_VARIANT_CLIENT => _w('Client (request originator)'),
            self::MESSAGE_TO_VARIANT_RESPONSIBLE_USER => _w('Responsible user (owner)'),
        );
        return self::$message_to_variants;
    }

    public static function isMessageToVariant($variant)
    {
        $variants = self::getMessageToVariants();
        return isset($variants[$variant]);
    }

    public function exists()
    {
        $info = $this->getInfo();
        return $info['id'] > 0;
    }

    /**
     * Delete params in DB
     */
    public function deleteParams()
    {
        $this->saveParams(array());
    }

    /**
     * Delete param in DB
     * @param string $key
     */
    public function deleteParam($key)
    {
        if (is_scalar($key)) {
            $this->saveParams(array($key => null), false);
        }
    }

    public function delete()
    {
        if (!$this->exists()) {
            return;
        }
        $this->getSource()->delete();
        self::getFormParamsModel()->delete($this->id);
        self::getFormModel()->delete($this->id);
    }

    /**
     * @param $fields
     * @param $field_id
     * @param bool
     * @return bool|int|string|array[]int|array[]string
     */
    protected function searchField($fields, $field_id, $multi = false)
    {
        $indexes = array();
        foreach ($fields as $index => $field) {
            if (isset($field['id']) && $field['id'] === $field_id) {
                $indexes[] = $index;
            }
        }
        if ($multi) {
            return $indexes;
        } else {
            return $indexes ? reset($indexes) : false;
        }
    }

    /**
     * Delete certain fields from certain forms
     * @param array[]string|string $field_ids
     * @param null|array[]int|int $form_ids
     */
    public static function deleteFieldsFromForms($field_ids, $form_ids = null)
    {
        if (waConfig::get('is_template')) {
            return;
        }

        // type-cast form_ids param
        if ($form_ids !== null) {
            $form_ids = is_scalar($form_ids) ? crmHelper::toIntArray($form_ids) : $form_ids;
            $form_ids = is_array($form_ids) ? $form_ids : array();
        }

        // guard case
        if (!$form_ids && $form_ids !== null) {
            return;
        }

        // type-cast field_ids param
        $field_ids = is_scalar($field_ids) ? (array)$field_ids : $field_ids;
        $field_ids = is_array($field_ids) ? $field_ids : array();
        $field_ids = array_map('strval', $field_ids);
        $field_ids_map = array_fill_keys($field_ids, true);

        $pm = new crmFormParamsModel();

        $where = array('form_id' => $form_ids, 'name' => 'fields');
        if ($form_ids === null) {
            unset($where['form_id']);
        }
        $items = $pm->getByField($where, true);

        // guard case
        if (!$items) {
            return;
        }

        foreach ($items as $item) {

            $value = $item['value'];
            if (!preg_match('/^a:\d+?:\{/', $value)) {
                continue;
            }

            $fields = @unserialize($value);
            if (!is_array($fields)) {
                continue;
            }

            $changed = false;
            foreach ($fields as $index => $field) {
                $field_id = (string)ifset($field['id']);
                if (isset($field_ids_map[$field_id])) {
                    unset($fields[$index]);
                    $changed = true;
                }
            }

            if (!$changed) {
                continue;
            }

            $pm->updateByField(
                array(
                    'form_id' => $item['form_id'],
                    'name' => $item['name']
                ),
                array(
                    'value' => @serialize($fields)
                )
            );
        }
    }

    protected static function serializeParams($params)
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = @serialize($value);
            }
        }
        return $params;
    }

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
     * @return crmFormModel
     */
    protected static function getFormModel()
    {
        if (isset(self::$static_cache['models']['form'])) {
            return self::$static_cache['models']['form'];
        }
        return self::$static_cache['models']['form'] = new crmFormModel();
    }

    /**
     * @return crmFormParamsModel
     */
    protected static function getFormParamsModel()
    {
        if (isset(self::$static_cache['models']['form_params'])) {
            return self::$static_cache['models']['form_params'];
        }
        return self::$static_cache['models']['form_params'] = new crmFormParamsModel();
    }
}
