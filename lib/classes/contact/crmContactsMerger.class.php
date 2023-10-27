<?php

class crmContactsMerger
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
     * @var waCache
     */
    protected static $cache;

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
        if (isset($options['hash'])) {
            $this->setHash((string)$options['hash']);
        }
        if (isset($options['master_id'])) {
            $this->setMasterId((int)$options['master_id']);
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
     * @return bool
     * @throws waException
     */
    public function mergeChunk($chunk_size = 100)
    {
        if ($this->isMergeDone()) {
            return true;
        }

        $master_id = $this->getMasterId();

        $collection = $this->getCollection();

        $contacts_data = $collection->getContacts('*', 0, $chunk_size);

        $res_merged_count = $this->merge($master_id, $contacts_data);
        $prev_merged_count = $this->getMergedCount();
        $merged_count = $res_merged_count + $prev_merged_count;
        $this->setMergedCount($merged_count);

        $total_count_to_merge = $this->getTotalCountToMerge();

        if ($merged_count >= $total_count_to_merge) {
            $this->markMergeAsDone();
            return true;
        }

        return false;
    }

    /**
     * Get merged result after all mergeChunk calls
     * @return array $result
     *     int $result['total_count']
     *     int $result['merged_count']
     */
    public function getMergeResult()
    {
        return array(
            'total_count' => $this->getTotalCount(),
            'merged_count' => $this->getMergedCount()
        );
    }

    /**
     * Merge contacts with master contact. Return merged count
     * @param int $master_id
     * @param array $contacts_data
     * @return int
     * @throws waException
     */
    protected function merge($master_id, $contacts_data)
    {
        // will be work with object for master
        $master = new crmContact($master_id);

        if (!$master->exists() || $master->getId() <= 0) {
            throw new waException('No contact to merge into.');
        }

        // check photo correctness
        if ($master['photo']) {
            $dir = waContact::getPhotoDir($master['id']);
            $filename = wa()->getDataPath("{$dir}{$master['photo']}.original.jpg", true, 'contacts');
            if (!file_exists($filename)) {
                $master['photo'] = null;
            }
        }

        $contacts_data_count = count($contacts_data);

        // not contacts for merging left, so quit
        if ($contacts_data_count <= 0) {
            return 0;
        }

        // define which credentials must be after merging
        $credentials = $this->extractCredentials($master, $contacts_data);

        // creating data of oldest contact
        $creating_data = $this->extractCreatingDataOldOldestContact($master, $contacts_data);

        // run main loop
        $loop_res = $this->mainMergeLoop($master, $contacts_data);

        /**
         * @var crmContact $master
         */
        $master = $loop_res['contact'];
        $update_photo = $loop_res['update_photo'];
        // Save master contact
        // 42 == do not validate anything at all
        $master->save(array(), 42);

        // set creating data into master
        $this->setCreatingData($master, $creating_data);

        // merge categories
        $this->mergeCategories($master, $contacts_data);

        // we need update photo, so do it
        if ($update_photo) {
            $this->updatePhoto($master, $update_photo);
        }

        // merge logs
        $this->mergeLogs($master, $contacts_data);

        // trigger event for extend merge process
        $this->triggerEvent($master, $contacts_data);

        // delete merged into master contacts
        $this->deleteContacts($contacts_data);

        // Set credentials for master
        // IMPORTANT: set up credentials must be after deleting slave contacts, so prevent possible db error "Duplicate entry for key 'login'"
        $this->setCredentials($master, $credentials);

        return $contacts_data_count;
    }

    /**
     * Extract (define) credentials (password, login, email, phone) that must be in master after merging
     *
     * If master has password - get all credentials of master
     * Otherwise get all credentials from first slave that has password
     *
     * @param crmContact $master
     * @param array $contacts_data not empty array
     * @return array
     * @throws waDbException
     * @throws waException
     */
    protected function extractCredentials($master, $contacts_data)
    {
        if ($master['password']) {

            $emails = $master->get('email', 'value');
            reset($emails);

            $email = $emails ? reset($emails) : null;

            $phones = $master->get('phone', 'value');
            reset($phones);

            $phone = $phones ? reset($phones) : null;

            return array(
                'password' => $master['password'],
                'email' => $email,
                'phone' => $phone,
                'login' => $master['login']
            );
        }

        $cm = new waContactModel();
        $passwords = $cm->select('id, password')->where('id IN(:ids)', array(
            'ids' => array_keys($contacts_data)
        ))->fetchAll('id', true);

        foreach ($contacts_data as $id => $info) {
            $password = !empty($passwords[$id]) ? $passwords[$id] : '';
            if ($password) {
                $email = '';
                if (!empty($info['email'])) {

                    $info['email'] = array_values($info['email']);
                    reset($info['email']);

                    $email = reset($info['email']);
                    if (is_array($email)) {
                        if (isset($email['value'])) {
                            $email = $email['value'];
                        } elseif (isset($email['email'])) {
                            $email = $email['email'];
                        }
                    }
                }
                $phone = '';
                if (!empty($info['phone'])) {

                    $info['phone'] = array_values($info['phone']);
                    reset($info['phone']);

                    $phone = reset($info['phone']);
                    if (is_array($phone)) {
                        if (isset($phone['value'])) {
                            $phone = $phone['value'];
                        }
                    }
                }
                return array(
                    'password' => $password,
                    'email' => $email,
                    'phone' => $phone,
                    'login' => !empty($info['login']) ? $info['login'] : null
                );
            }
        }
    }

    /**
     * Extract create_datetime, create_app_id, create_method, create_contact_id of oldest contact
     * Oldest contact is contact that has been created earlier than other contacts
     * @param waContact $master
     * @param array $contacts_data not empty array
     * @return array with fields create_datetime, create_app_id, create_method, create_contact_id
     */
    protected function extractCreatingDataOldOldestContact($master, $contacts_data)
    {
        $creating_data_fields = array('create_datetime', 'create_app_id', 'create_method', 'create_contact_id');

        $creating_data = array();
        foreach ($creating_data_fields as $field_id) {
            $creating_data[$field_id] = $master[$field_id];
        }

        foreach ($contacts_data as $contact_id => $contact_data) {
            if ($contact_data['create_datetime'] < $creating_data['create_datetime']) {
                foreach ($creating_data as $field_id => $_) {
                    $creating_data[$field_id] = $contact_data[$field_id];
                }
            }
        }

        return $creating_data;
    }

    /**
     * Set credentials that was defined (extracted) in extractCredentials
     * If master already had password, previous credentials will not be lost
     * @param crmContact $master
     * @param $credentials
     * @throws waException
     * @see extractCredentials
     */
    protected function setCredentials($master, $credentials)
    {
        if ($master['password'] || empty($credentials)) {
            return;
        }

        $cm = new waContactModel();
        $cm->updateById($master->getId(), array(
            'password' => $credentials['password'],
            'login' => $credentials['login']
        ));

        //
        $this->moveEmailOnFirstPlace($master, $credentials['email']);

        //
        $this->movePhoneOnFirstPlace($master, $credentials['phone']);

        $master->removeCache();
    }

    /**
     * Set creating data
     * @param waContact $master
     * @param $creating_data
     * @throws waDbException
     * @throws waException
     */
    protected function setCreatingData($master, $creating_data)
    {
        $cm = new waContactModel();
        $cm->updateById($master->getId(), $creating_data);
        $master->removeCache();
    }


    /**
     * Move email on first place
     * If email was not in list of contact emails place it new on first place
     * @param waContact $contact
     * @param string $email
     * @throws waException
     */
    protected function moveEmailOnFirstPlace($contact, $email)
    {
        // set email on first place
        $emails = $contact->get('email');

        // was our target email exist in list
        $was_in_list = false;

        $new_list = array();
        foreach ($emails as $existed_email) {
            if ($existed_email['value'] == $email) {
                array_unshift($new_list, $existed_email);
                $was_in_list = true;
            } else {
                array_push($new_list, $existed_email);
            }
        }

        if (!$was_in_list) {
            array_unshift($new_list, array(
                'value' => $email,
                'ext' => '',
                'status' => waContactEmailsModel::STATUS_UNKNOWN
            ));
        }

        if ($emails != $new_list) {
            $contact->save(array(
                'email' => $new_list
            ));
        }

    }

    /**
     * Move phone on first place
     * If phone was not in list of contact phones place it new on first place
     * @param waContact $contact
     * @param string $phone
     * @throws waException
     */
    protected function movePhoneOnFirstPlace($contact, $phone)
    {
        $phones = $contact->get('phone');

        // was our target phone exist in list
        $was_in_list = false;

        $new_list = array();

        foreach ($phones as $existed_phone) {
            if ($existed_phone['value'] == $phone) {
                array_unshift($new_list, $existed_phone);
                $was_in_list = true;
            } else {
                array_push($new_list, $existed_phone);
            }
        }

        if (!$was_in_list) {
            array_unshift($new_list, array(
                'value' => $phone,
                'ext' => '',
                'status' => waContactDataModel::STATUS_UNKNOWN
            ));
        }

        if ($phones != $new_list) {
            $contact->save(array(
                'phone' => $new_list
            ));
        }
    }


    /**
     * Eval main merge loop, where we merge values in db
     * @param crmContact $master
     * @param array $contacts_data
     * @return array $return
     *     crmContact $return['contact']
     *     array|null $return['update_photo']
     */
    protected function mainMergeLoop($master, $contacts_data)
    {
        // which filed to check for duplicate value
        // field_id => true
        $check_duplicates = array();

        // if need to update photo here it is file paths
        $update_photo = null;

        /**
         * @var waContactField[] $data_fields
         */
        $data_fields = waContactFields::getAll('enabled');
        $contact_model = new waContactModel();

        // just in case through away password
        if (isset($data_fields['password'])) {
            unset($data_fields['password']);
        }

        // loop
        foreach ($contacts_data as $id => $info) {

            foreach ($info as $f => $val) {
                if (empty($val)) {
                    continue;
                }
                $field = ifempty($data_fields, $f, null);

                if ($field && $field->isMulti()) {
                    $this->addMultiFieldValues($master, $f, $val);
                    $check_duplicates[$f] = true;
                } else if ($field) {
                    // Known contact field does not allow multiple values
                    if (!$master[$f]) {
                        $master[$f] = $val;
                    }
                } else if ($f != '_online_status' && !$contact_model->fieldExists($f)) {
                    // unknown contact field from wa_contact_data
                    if (is_array($val)) {
                        if (isset($val['value'])) {
                            $val = $val['value'];
                        } else {
                            $val = reset($val);
                            if (is_array($val) && isset($val['value'])) {
                                $val = $val['value'];
                            }
                        }
                    }
                    if (is_scalar($val) && !$master[$f]) {
                        $master[$f] = $val;
                    }
                }
            }

            // photo
            if (!$master['photo'] && $info['photo'] && !$update_photo) {
                $filename_original = wa()->getDataPath(waContact::getPhotoDir($info['id'])."{$info['photo']}.original.jpg", true, 'contacts');
                if (file_exists($filename_original)) {
                    $update_photo = array(
                        'original' => $filename_original
                    );
                    $filename_crop = wa()->getDataPath(waContact::getPhotoDir($info['id'])."{$info['photo']}.jpg", true, 'contacts');
                    if (file_exists($filename_crop)) {
                        $update_photo['crop'] = $filename_crop;
                    }
                }
            }

            // birthday parts
            if (!empty($data_fields['birthday'])) {
                foreach(array('birth_day', 'birth_month', 'birth_year') as $f) {
                    if (!$master[$f] && !empty($info[$f])) {
                        $master[$f] = $info[$f];
                    }
                }
            }
        }

        // remove duplicate values
        $master = $this->removeDuplicateValues($master, array_keys($check_duplicates));

        return array(
            'contact' => $master,
            'update_photo' => $update_photo
        );
    }

    /**
     * @param crmContact $master
     * @param $field_id
     * @param $values
     */
    protected function addMultiFieldValues($master, $field_id, $values)
    {
        foreach ($values as $index => $value) {
            $_field_id = $field_id;
            $val = $value;
            if (is_array($value)) {
                if (isset($value['value'])) {
                    $val = $value['value'];
                } else if (isset($value['email'])) {
                    $val = $value['email'];
                } else if (isset($value['data'])) {
                    $val = $value['data'];
                } else {
                    $val = null;
                }
                $ext = (string)ifset($value['ext']);
                $_field_id = $field_id . ($ext ? '.' . $ext : '');
            }
            $master->add($_field_id, $val);
        }
    }

    /**
     * Remove duplicate values for contact for certain fields
     * @param crmContact $master
     * @param string[] $check_duplicates list of fields
     * @return crmContact
     */
    protected function removeDuplicateValues($master, $check_duplicates)
    {
        // Remove duplicates
        foreach($check_duplicates as $f) {
            $values = $master[$f];
            if (!is_array($values) || count($values) <= 1) {
                continue;
            }

            $unique_values = array(); // md5 => true
            foreach($values as $k => $v) {
                if (is_array($v)) {
                    if (isset($v['value']) && is_string($v['value'])) {
                        $v = $v['value'];
                    } else {
                        unset($v['ext'], $v['status']);
                        ksort($v);
                        $v = serialize($v);
                    }
                }
                $hash = md5(mb_strtolower($v));
                if (!empty($unique_values[$hash])) {
                    unset($values[$k]);
                    continue;
                }
                $unique_values[$hash] = true;
            }
            $master[$f] = array_values($values);
        }

        return $master;
    }

    /**
     * Merge categories
     * @param crmContact $master
     * @param array $contacts_data
     */
    protected function mergeCategories($master, $contacts_data)
    {
        // Merge categories
        $contact_ids = array_keys($contacts_data);
        $category_ids = array();
        $categories = $this->getContactCategoriesModel()->getContactsCategories($contact_ids);
        foreach($categories as $cid => $cats) {
            $category_ids += array_flip($cats);
        }
        $category_ids = array_keys($category_ids);
        $this->getContactCategoriesModel()->add($master->getId(), $category_ids);
    }

    /**
     * @param crmContact $master
     * @param string $update_photo
     */
    protected function updatePhoto($master, $update_photo)
    {
        $rand = mt_rand();
        $path = wa()->getDataPath(waContact::getPhotoDir($master['id']), true, 'contacts', false);

        // delete old image
        if (file_exists($path)) {
            waFiles::delete($path);
        }
        waFiles::create($path);

        $filename = $path."/".$rand.".original.jpg";
        waFiles::create($filename);
        waImage::factory($update_photo['original'])->save($filename, 90);

        if (!empty($update_photo['crop'])) {
            $filename = $path."/".$rand.".jpg";
            waFiles::create($filename);
            waImage::factory($update_photo['crop'])->save($filename, 90);
        } else {
            waFiles::copy($filename, $path."/".$rand.".jpg");
        }

        $master->save(array(
            'photo' => $rand
        ));
    }

    /**
     * @param crmContact $master
     * @param array $contacts_data
     */
    protected function mergeLogs($master, $contacts_data)
    {
        $contact_ids = array_keys($contacts_data);

        $update = array(
            array('contact_id' => $contact_ids),
            array('contact_id' => $master->getId())
        );

        $this->getLogModel()->updateByField($update[0], $update[1]);
        $this->getLoginLogModel()->updateByField($update[0], $update[1]);
    }

    /**
     * @param crmContact $master
     * @param array $contacts_data
     */
    protected function triggerEvent($master, $contacts_data)
    {
        $params = array(
            'contacts' => array_keys($contacts_data),
            'id' => $master->getId()
        );
        /**
         * Event for extend merge process
         * @event merge
         * @param array $params
         * @param array[]int $params['contacts'] contact ids of slave contacts
         * @param array[]int $params['id'] contact id of master contact
         */
        wa()->event(array('contacts', 'merge'), $params);
    }

    /**
     * @param array $contacts_data
     */
    protected function deleteContacts($contacts_data)
    {
        $contact_ids = array_keys($contacts_data);
        $this->getContactModel()->delete($contact_ids, false);
    }

    /**
     * @return crmContactsCollection
     */
    protected function getCollection()
    {
        if ($this->collection !== null) {
            return $this->collection;
        }
        $collection = new crmContactsCollection($this->getHash());
        $total_count = $collection->count();
        $master_id = $this->getMasterId();
        $collection->addWhere("c.is_user <= 0 && c.id != {$master_id}");
        $total_count_to_merge = $collection->count();

        $this->setTotalCount($total_count);
        $this->setTotalCountToMerge($total_count_to_merge);
        return $collection;
    }

    /**
     * @return bool
     */
    public function isMergeDone()
    {
        return (bool)$this->getCacheVar('is_merge_done');
    }

    protected function markMergeAsDone()
    {
        $this->setCacheVar('is_merge_done', 1);
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
     * @return int
     */
    protected function getMasterId()
    {
        return (int)$this->getCacheVar('master_id');
    }

    /**
     * @param int $master_id
     */
    protected function setMasterId($master_id)
    {
        $master_id = (int)$master_id;
        if ($this->getMasterId() !== $master_id) {
            $this->setCacheVar('master_id', $master_id);
        }
    }

    /**
     * @return int
     */
    protected function getTotalCount()
    {
        return (int)$this->getCacheVar('total_count');
    }

    /**
     * @param int $total_count
     */
    protected function setTotalCount($total_count)
    {
        if ($this->getCacheVar('total_count') === null) {
            $this->setCacheVar('total_count', (int)$total_count);
        }
    }

    /**
     * @return int
     */
    protected function getTotalCountToMerge()
    {
        return (int)$this->getCacheVar('total_count_to_merge');
    }

    /**
     * @param int $total_count
     */
    protected function setTotalCountToMerge($total_count)
    {
        if ($this->getCacheVar('total_count_to_merge') === null) {
            $this->setCacheVar('total_count_to_merge', (int)$total_count);
        }
    }

    /**
     * @return int
     */
    protected function getMergedCount()
    {
        return (int)$this->getCacheVar('merged_count');
    }

    /**
     * @param int $count
     */
    protected function setMergedCount($count)
    {
        $count = (int)$count;
        if ($count !== $this->getMergedCount()) {
            $this->setCacheVar('merged_count', $count);
        }
    }

    /**
     * @return array
     */
    protected function getSavedResult()
    {
        $result = (array)$this->getCacheVar('result', true);
        $result['total_count'] = (int)ifset($result['total_count']);
        $result['merged_count'] = (int)ifset($result['merged_count']);
        return $result;
    }

    /**
     * @return waContactModel
     */
    protected function getContactModel()
    {
        return !empty(self::$models['contact']) ?
            self::$models['contact'] :
            (self::$models['contact'] = new waContactModel());
    }

    /**
     * @return waContactCategoriesModel
     */
    protected function getContactCategoriesModel()
    {
        return !empty(self::$models['contact_categories']) ?
            self::$models['contact_categories'] :
            (self::$models['contact_categories'] = new waContactCategoriesModel());
    }

    /**
     * @return waLogModel
     */
    protected function getLogModel()
    {
        return !empty(self::$models['log']) ? self::$models['log'] : (self::$models['log'] = new waLogModel());
    }

    /**
     * @return waLoginLogModel
     */
    protected function getLoginLogModel()
    {
        return !empty(self::$models['login_log']) ? self::$models['login_log'] : (self::$models['login_log'] = new waLoginLogModel());
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
