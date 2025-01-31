<?php

class crmContact extends waContact
{
    const EMPTY_EMAIL_SIGNATURE = ':EMPTY_EMAIL_SIGNATURE:';

    protected $app_id = 'crm';
    protected $region_model = null;

    /**
     * For store arbitrary properties
     * @var array
     */
    protected $properties;

    /**
     * @var waContactEmailsModel
     */
    protected $wcem;

    public function set($field_id, $value, $add = false)
    {
        $value = $this->prepareValueBeforeSet($field_id, $value);

        if (strpos($field_id, '.') !== false) {
            $field_parts = explode('.', $field_id, 2);
            $field_id = $field_parts[0];
            $ext = $field_parts[1];
        } else {
            $ext = null;
        }
        if (strpos($field_id, ':') !== false) {
            $field_parts = explode(':', $field_id, 2);
            $field_id = $field_parts[0];
            $subfield = $field_parts[1];
        } else {
            $subfield = null;
        }

        $f = waContactFields::get($field_id, $this['is_company'] ? 'company' : 'person');
        if (!$f) {
            if ($field_id == 'password') {
                $value = self::getPasswordHash($value);
            }
            $this->data[$field_id] = $value;
        } else {
            $set_value = true;
            if ($f instanceof waContactSelectField) {
                $set_value = false;
                $value = $this->findValueForSelect($f, $value);
                if ($value !== null) {
                    $set_value = true;
                }
            } elseif ($f instanceof waContactCompositeField && !empty($subfield)) {
                $sf = $f->getFields($subfield);
                if ($sf instanceof waContactSelectField) {
                    $set_value = false;
                    $value = $this->findValueForSelect($sf, $value);
                    if ($value !== null) {
                        $set_value = true;
                    }
                } elseif ($sf instanceof waContactRegionField) {
                    $value = $this->findRegion($value);
                }
            }
            if ($set_value) {
                $this->data[$field_id] = $f->set($this, $value, array('ext' => $ext, 'subfield' => $subfield), $add);
            }
        }
    }

    protected function prepareValueBeforeSet($field_id, $value)
    {
        // support im source (twitter, fb, telegram...) that could create (save) contact
        if ($field_id == 'firstname' || $field_id == 'middlename' || $field_id == 'lastname' || $field_id == 'name') {
            if (!crmModel::isTableColumnMb4('wa_contact', $field_id)) {
                return crmHelper::removeEmoji($value);
            }
        }
        return $value;
    }

    protected function findValueForSelect($field, $value)
    {
        $orig_loc = wa()->getLocale();

        wa()->setLocale('en_US');

        // get always english options
        $options = $field->getOptions();

        // trivial case
        if (isset($options[$value])) {
            wa()->setLocale($orig_loc);
            return $value;
        }

        $locales = array($orig_loc => 1);
        foreach (waLocale::getAll() as $locale) {
            $locales[$locale] = 1;
        }
        $locales = array_keys($locales);

        $is_found = false;
        $found_opt_id = null;

        foreach ($locales as $locale) {
            wa()->setLocale($locale);
            $low_val = mb_strtolower($value);
            foreach ($options as $opt_id => $opt) {
                $opt_loc = _w($opt);
                $opt_loc_s = _ws($opt);
                $variants = array(
                    $opt_id, $opt_loc, $opt_loc_s
                );
                foreach ($variants as $variant) {
                    $variant_low = mb_strtolower($variant);
                    if ($variant_low === $low_val) {
                        $found_opt_id = $opt_id;
                        $is_found = true;
                        break 3;
                    }
                }
            }
        }

        wa()->setLocale($orig_loc);

        return $is_found ? $found_opt_id : null;
    }

    protected function findRegion($value)
    {
        if (empty($this->region_model)) {
            $this->region_model = new waRegionModel();
        }

        $region = $this->region_model->getByField(['name' => $value]);
        return empty($region) ? $value : $region['code'];
    }

    /**
     * @return array
     * Format
     *   array(
     *       '<column_id>[:<sub_column_id>]' => array('sort' => int, 'width' => 's'|'m'|'l'),
     *       ...
     *   )
     */
    public function getContactColumns()
    {
        $columns_map = $this->getSettings($this->app_id, 'contact_list_columns');
        if ($columns_map === null) {
            $columns_map = array('create_datetime' => array('sort' => 1), 'email' => array('sort' => 2));
        } else {
            $columns_map = (array)json_decode($columns_map, true);
        }

        return $this->typecastContactColumns($columns_map);
    }

    public function setContactColumns($columns_map)
    {
        if (empty($columns_map)) {
            $this->delContactColumns();
            return;
        }

        $columns_map = $this->typecastContactColumns($columns_map);
        $this->setSettings($this->app_id, 'contact_list_columns', json_encode($columns_map));

    }

    public function delContactColumns()
    {
        $this->getSettings($this->app_id, 'contact_list_columns');
    }

    protected function typecastContactColumns($columns_map)
    {
        $columns_map = is_array($columns_map) ? $columns_map : array();

        $widths = array_fill_keys(array('s', 'm', 'l'), true);
        $default_width = 'm';

        foreach ($columns_map as $column_id => $item) {
            $item = (array)$item;
            $item['sort'] = (int)ifset($item['sort']);
            $item['width'] = (string)ifset($item['width']);
            if (!isset($widths[$item['width']])) {
                $item['width'] = $default_width;
            }
            $columns_map[$column_id] = $item;
        }

        return $columns_map;
    }

    public function getDefaultEmailValue()
    {
        return $this->get('email', 'default');
    }

    public function setEmailSignature($value)
    {
        $value = is_scalar($value) ? trim((string)$value) : '';
        $value = str_replace("</p><p>", "<br>", $value);

        if ($value === $this->defaultEmailSignature()) {
            // If the signature coincides with the default, then delete it and always return the default signature.
            $this->delSettings('crm', 'email_signature');
        } elseif (strlen($value) > 0) {
            $this->setSettings('crm', 'email_signature', $value);
        } else {
            $this->setSettings('crm', 'email_signature', self::EMPTY_EMAIL_SIGNATURE);
        }
    }

    public function getEmailSignature()
    {
        $user_email_signature = trim( (string)$this->getSettings('crm', 'email_signature') );

        if ($user_email_signature && $user_email_signature != self::EMPTY_EMAIL_SIGNATURE) {
            return $user_email_signature;
        } elseif ($user_email_signature == self::EMPTY_EMAIL_SIGNATURE){
            return null;
        } else {
            return $this->defaultEmailSignature();
        }
    }

    protected function defaultEmailSignature()
    {
        $contact = new crmContact(wa()->getUser()->getId());

        $signature_body = $this->getSenderName();
        if($contact['company']) {
            $signature_body .= "<br>{$contact['company']}";
        }

        return "<p>--<br>{$signature_body}</p>";
    }

    /**
     * Return name of current contact when he send email, aka sender name
     * @return string
     */
    public function getSenderName()
    {
        $name = trim((string)$this->getSettings('crm', 'sender_name'));
        if (strlen($name) > 0) {
            return $name;
        }
        return $this->getName();
    }

    /**
     * Set name for sending email, aka sender name
     * @param $value
     */
    public function setSenderName($value)
    {
        $value = is_scalar($value) ? trim((string)$value) : '';
        if (strlen($value) > 0 && $value !== $this->getName()) {
            $this->setSettings('crm', 'sender_name', $value);
        } else {
            $this->delSettings('crm', 'sender_name');
        }
    }

    /**
     * Return email address of current contact when he send email, aka sender email
     * @return string
     */
    public function getSenderEmail()
    {
        $email = trim((string)$this->getSettings('crm', 'sender_email'));
        $emails = $this->get('email', 'value');
        $relevance = in_array($email, $emails);
        if (strlen($email) > 0 && $relevance) {
            return $email;
        }
        if (strlen($email) > 0 && !$relevance) {
            $this->delSettings('crm', 'sender_email');
        }
        return $this->getDefaultEmailValue();
    }

    /**
     * Set email address for sending email, aka sender email
     * @param $value
     */
    public function setSenderEmail($value)
    {
        $value = is_scalar($value) ? trim((string)$value) : '';
        if (strlen($value) > 0) {
            $this->setSettings('crm', 'sender_email', $value);
        } else {
            $this->delSettings('crm', 'sender_email');
        }
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
     * @param $email
     */
    public function setEmailConfirmed($email)
    {
        $this->getEmailsModel()->updateByField(array(
            'contact_id' => $this->getId(),
            'email' => $email
        ), array('status' => 'confirmed'));
    }

    public static function getAllColumns($contact_type = '')
    {
        $fields = array();

        $ignore = array(
            'type' => array(
                'Hidden' => 1,
            ) + ($contact_type === 'all_api' ? [] : ['Text' => 1])
        );
        if (empty($contact_type)) {
            $ignore += array(
                'field_id' => array(
                    'name' => 1, 'firstname' => 1, 'middlename' => 1, 'lastname' => 1
                )
            );
        }

        // several special columns
        $special_fields = array(
            'create_datetime' => new waContactDateField('create_datetime', _w('Created')),
            'last_datetime' => new waContactDateField('last_datetime', _w('Last activity'))
        );

        $all_fields = $special_fields + waContactFields::getAll('all');

        foreach ($all_fields as $field_id => $field) {

            /**
             * @var waContactField $field
             */
            $type = $field->getType();
            $is_ignored = !empty($ignore['type'][$type]) || !empty($ignore['field_id'][$field_id]);
            $is_special = !empty($special_fields[$field_id]);
            if ($is_ignored && !$is_special) {
                continue;
            }

            $fields[$field_id] = array(
                'id' => $field_id,
                'is_composite' => $field instanceof waContactCompositeField,
                'name' => $field->getName(),
                'is_multi' => $field->isMulti(),
                'field' => $field,
                'is_sortable' => !($field->getStorage() instanceof waContactDataTextStorage) && !($field instanceof waContactBirthdayField)
            );

            if ($fields[$field_id]['is_composite']) {
                $fields[$field_id]['sub_columns'] = array();
                foreach ($field->getFields() as $sub_field_id => $sub_field) {
                    /**
                     * @var waContactField $sub_field
                     */
                    $type = $sub_field->getType();
                    if (!empty($ignore['type'][$type])) {
                        continue;
                    }
                    $fields[$field_id]['sub_columns'][$sub_field_id] = array(
                        'id' => $sub_field_id,
                        'is_composite' => false,
                        'name' => $sub_field->getName(),
                        'is_multi' => $sub_field->isMulti(),
                        'field' => $sub_field,
                        'is_sortable' => !($field->getStorage() instanceof waContactDataTextStorage) && !($field instanceof waContactBirthdayField)
                    );
                }
                continue;
            }
        }

        $fields['create_datetime']['is_sortable'] = true;
        $fields['last_datetime']['is_sortable'] = true;

        return $fields;
    }

    public static function getCurrentContactColumns($contact_id = null)
    {
        $contact_id = (int) $contact_id;
        $contact = new crmContact($contact_id > 0 ? $contact_id : wa()->getUser()->getId());
        return $contact->getContactColumns();
    }

    public static function setCurrentContactColumns($columns, $contact_id = null)
    {
        $contact_id = (int) $contact_id;
        $contact = new crmContact($contact_id > 0 ? $contact_id : wa()->getUser()->getId());
        $contact->setContactColumns($columns);
    }

    protected function getEmailsModel()
    {
        return $this->wcem !== null ? $this->wcem : ($this->wcem = new waContactEmailsModel());
    }

    /**
     * Если можно назначить юзера $new_responsible_id ответственным, возвращает false.
     * Если нельзя, возвращзает причину, почему нельзя.
     */
    public function isResponsibleUserIncceptable($new_responsible_id)
    {
        $contact = $this;
        if (!$contact['crm_vault_id'] || !$new_responsible_id) {
            return false;
        }

        $responsible = new waContact($new_responsible_id);
        if ($contact['crm_vault_id'] > 0) {
            if ($responsible->getRights('crm', 'vault.'.$contact['crm_vault_id'])) {
                return false;
            } else {
                return 'no_vault_access';
            }
        }

        $adhoc_group_model = new crmAdhocGroupModel();
        $owner_ids = $adhoc_group_model->getByGroup(-$contact['crm_vault_id']);
        if(in_array($new_responsible_id, $owner_ids)) {
            return false;
        } else {
            return 'no_adhoc_access';
        }
    }

    /**
     * Добавляет юзера $new_responsible_id в adhoc список теукщего контакта.
     */
    public function addResponsibleToAdhock($new_responsible_id)
    {
        $contact = $this;
        $adhoc_group_model = new crmAdhocGroupModel();
        $owner_ids = $adhoc_group_model->getByGroup(-$contact['crm_vault_id']);
        $new_owners = array_merge($owner_ids,array($new_responsible_id));
        $adhoc_group_model->setContactsOwners($contact['id'], -$contact['crm_vault_id'], $new_owners);

        return true;
    }

    /**
     * Create new waContactField of appropriate type from given array of options.
     *
     * @param array $opts
     * @param array $occupied_keys
     * @return null|waContactField
     */
    public static function createFromOpts($opts, $occupied_keys = array())
    {
        if (!is_array($opts) || empty($opts['_type']) || waConfig::get('is_template')) {
            return null;
        }

        // Generate field_id from name
        $fld_id = self::transliterate((string)ifset($opts['localized_names'], ''));
        if (!$fld_id) {
            $fld_id = 'f';
        }
        if (strlen($fld_id) > 15) {
            $fld_id = substr($fld_id, 0, 15);
        }
        while (isset($occupied_keys[$fld_id])) {
            if (strlen($fld_id) >= 15) {
                $fld_id = substr($fld_id, 0, 10);
            }
            $fld_id .= mt_rand(0, 9);
        }

        // Create field object of appropriate type
        $options = array(
            'app_id' => 'crm',
        );
        $_type = strtolower($opts['_type']);
        switch ($_type) {
            case 'textarea':
                $class = 'waContactStringField';
                $options['storage'] = 'waContactDataStorage';
                $options['input_height'] = 5;
                break;
            case 'radio':
                $class = 'waContactRadioSelectField';
                break;
            default:
                $class = 'waContact'.ucfirst($_type).'Field';
        }
        if (!$_type || !class_exists($class)) {
            return null;
        }
        return new $class($fld_id, '', $options);
    }


    public static function transliterate($str, $strict = true)
    {
        $str = preg_replace('/\s+/u', '-', $str);
        if ($str) {
            foreach (waLocale::getAll() as $lang) {
                $str = waLocale::transliterate($str, $lang);
            }
        }
        $str = preg_replace('/[^a-zA-Z0-9_-]+/', '', $str);
        if ($strict && !strlen($str)) {
            $str = date('Ymd');
        }
        return strtolower($str);
    }

}
