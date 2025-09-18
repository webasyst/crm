<?php

class crmInstaller
{
    /**
     * @var waLocalePHPAdapter
     */
    protected $locale_php_adapter;

    /**
     * @var waLocaleAdapter
     */
    protected $locale_default_adapter;

    public function installAll()
    {
        $is_eng = wa()->getLocale() === 'en_US';

        // temporary set php adapter for correct translating
        if (!$is_eng) {
            $this->setLocalePHPAdapter();
        }

        $this->installAlterTables();
        $this->installCurrency();
        $this->installSegments();
        $this->installTags();
        $this->installNotes();
        $this->installForms();
        $this->installFields();
        $this->installFunnels();
        $this->installCompany();
        $this->installDealLost();
        $this->installNotification();
        $this->installSearchConfig();
        $this->installVault();
        $this->installEditRight();
        $this->installCountry();
        $this->installTemplates();
        $this->pullLogsFromShop();

        // restore default locale adapter
        if (!$is_eng) {
            $this->restoreLocaleDefaultAdapter();
        }
    }

    protected function setLocalePHPAdapter()
    {
        // one time call only
        if ($this->locale_default_adapter !== null) {
            return;
        }

        $this->locale_default_adapter = waLocale::getAdapter();
        waLocale::$adapter = $this->getLocalePHPAdapter();
    }

    protected function restoreLocaleDefaultAdapter()
    {
        // one time call only
        if ($this->locale_default_adapter === null) {
            return;
        }
        waLocale::$adapter = $this->locale_default_adapter;
        $this->locale_default_adapter = null;
    }

    protected function getLocalePHPAdapter()
    {
        if ($this->locale_php_adapter !== null) {
            return $this->locale_php_adapter;
        }
        $this->locale_php_adapter = new waLocalePHPAdapter();
        $locale_path = wa()->getAppPath('locale', 'crm');
        $this->locale_php_adapter->load('ru_RU', $locale_path, 'crm');
        return $this->locale_php_adapter;
    }

    public function installAlterTables()
    {
        $m = new waModel();

        try {
            $m->exec("SELECT crm_vault_id FROM wa_contact WHERE 0 LIMIT 0");
        } catch (Exception $e) {
            $m->exec("ALTER TABLE `wa_contact`
                      ADD `crm_vault_id` BIGINT NOT NULL DEFAULT 0,
                      ADD INDEX `crm_vault` (`crm_vault_id`)");
            $clear_cache = true;
        }

        try {
            $m->exec("SELECT crm_user_id FROM wa_contact WHERE 0 LIMIT 0");
        } catch (Exception $e) {
            $m->exec("ALTER TABLE `wa_contact`
                      ADD `crm_user_id` BIGINT NULL DEFAULT NULL,
                      ADD INDEX `crm_user` (`crm_user_id`)");
            $clear_cache = true;
        }

        try {
            $m->exec("SELECT crm_last_log_id FROM wa_contact WHERE 0 LIMIT 0");
        } catch (Exception $e) {
            $m->exec("ALTER TABLE `wa_contact`
                      ADD `crm_last_log_id` BIGINT NULL DEFAULT NULL");
            $clear_cache = true;
        }

        try {
            $m->exec("SELECT crm_last_log_datetime FROM wa_contact WHERE 0 LIMIT 0");
        } catch (Exception $e) {
            $m->exec("ALTER TABLE `wa_contact`
                      ADD `crm_last_log_datetime` DATETIME NULL DEFAULT NULL");
            $clear_cache = true;
        }

        try {
            $m->exec("SELECT last_log_id FROM crm_deal WHERE 0 LIMIT 0");
        } catch (Exception $e) {
            $m->exec("ALTER TABLE `crm_deal` ADD `last_log_id` BIGINT NULL DEFAULT NULL");
            $clear_cache = true;
        }

        try {
            $m->exec("SELECT last_log_datetime FROM crm_deal WHERE 0 LIMIT 0");
        } catch (Exception $e) {
            $m->exec("ALTER TABLE `crm_deal` ADD `last_log_datetime` DATETIME NULL DEFAULT NULL");
            $clear_cache = true;
        }

        if (!empty($clear_cache)) {
            $cache = new waRuntimeCache('db/default/wa_contact', -1, 'webasyst');
            $cache->clearAll();
            $cache = new waSystemCache('db/default/wa_contact', -1, 'webasyst');
            $cache->delete();
        }
    }

    public function installCurrency()
    {
        $cm = new crmCurrencyModel();
        if ($cm->countAll() <= 0) {
            $asm = new waAppSettingsModel();
            if (wa()->appExists('shop')) {
                wa('shop');
            }
            if (crmConfig::isShopSupported()) {
                $sql = "INSERT INTO {$cm->getTableName()} (code, rate, sort)
                  SELECT code, rate, sort FROM shop_currency";
                $cm->exec($sql);
                $asm->set('crm', 'currency', $asm->get('shop', 'currency'));
            } else {
                $currency = wa()->getLocale() == 'ru_RU' ? 'RUB' : 'USD';
                $cm->insert(array(
                    'code' => $currency,
                    'rate' => 1.000,
                ), 2);
                $asm->set('crm', 'currency', $currency);
            }
        }
    }

    public function installTags()
    {
        $m = new crmTagModel();
        if ($m->countAll() > 0) {
            return;
        }
        if ($this->isContactsProInstalled()) {
            $this->installTagsByContactsPro();
            return;
        }
    }

    public function installSegments()
    {
        $m = new crmSegmentModel();
        if ($m->countAll() > 0) {
            return;
        }
        if ($this->isContactsProInstalled() && $this->installSegmentsByContactsPro()) {
            return;
        }
        $this->installSegmentsByWaCategories();
    }

    public function installNotes()
    {
        $m = new crmNoteModel();
        if ($m->countAll() > 0) {
            return;
        }
        if ($this->isContactsProInstalled()) {
            $this->installNotesByContactsPro();
            return;
        }
    }

    public function installForms()
    {
        $m = new crmFormModel();
        if ($m->countAll() > 0) {
            return;
        }
        if (!$this->isContactsProInstalled()) {
            return;
        }
        $this->installFormsByContactsPro();
    }

    /**
     * @param string|array[] string $table
     */
    public function createTable($table)
    {
        $tables = array_map('strval', (array)$table);
        if (empty($tables)) {
            return;
        }

        $db_path = wa()->getAppPath('lib/config/db.php', 'crm');
        $db = include($db_path);

        $db_partial = array();
        foreach ($tables as $table) {
            if (isset($db[$table])) {
                $db_partial[$table] = $db[$table];
            }
        }

        if (empty($db_partial)) {
            return;
        }

        $m = new waModel();
        $m->createSchema($db_partial);
    }

    public function installFields()
    {
        // inside constructor ensure invariant method have called, so what we need
        new crmFieldConstructor();
    }

    public function installFunnels()
    {
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        if ($fm->countAll() <= 0 && $fsm->countAll() <= 0) {

            $funnel_name = _w('Sales');
            $id = $fm->insert(array(
                'name'        => $funnel_name,
                'color'       => '#6bb532',
                'open_color'  => '#dcedcf',
                'close_color' => '#6bb532',
            ));
            $stages = crmConfig::getFunnelBaseStages($id);

            $fsm->multipleInsert($stages);
        }
    }

    protected function installSegmentsByContactsPro()
    {
        if (!$this->checkModel('contactsViewModel')) {
            return false;
        }
        $crm_segment_model = new crmSegmentModel();
        if ($crm_segment_model->countAll() > 0) {
            return false;
        }
        if (!$this->reflectTables($crm_segment_model, new contactsViewModel())) {
            return false;
        }
        $this->renameSearchSegmentsHashes();
        return true;
    }

    protected function checkModel($class_name)
    {
        if (!class_exists($class_name)) {
            return false;
        }
        try {
            new $class_name();
        } catch (waDbException $e) {
            return false;
        }
        return true;
    }

    public function renameSearchSegmentsHashes()
    {
        $segment_model = new crmSegmentModel();

        $where = '`type` = "search" AND (SUBSTR(`hash`, 1, 19) = "/contacts/prosearch" OR SUBSTR(`hash`, 1, 18) = "contacts/prosearch")';
        $segments = $segment_model
            ->select('id, hash')
            ->where($where)->fetchAll();

        foreach ($segments as $segment) {
            $hash = $segment['hash'];
            $hash = str_replace('contacts/prosearch/', 'crmSearch/', $hash);
            $hash = trim($hash, '/');
            if ($hash !== $segment['hash']) {
                $segment_model->updateById($segment['id'], array('hash' => $hash));
            }
        }
    }

    public function installEditRight()
    {
        $m = new waModel();
        $sql = 'INSERT IGNORE INTO `wa_contact_rights` (`group_id`, `app_id`, `name`, `value`)
                SELECT `group_id`, "crm", `name`, `value`
                FROM `wa_contact_rights`
                WHERE `app_id` = "contacts" AND `name` = "edit"';
        $m->exec($sql);
    }

    protected function installSegmentsByWaCategories()
    {
        $cm = new waContactCategoryModel();
        $sm = new crmSegmentModel();
        $sort = 0;
        if ($sm->countAll() <= 0) {
            foreach ($cm->getAll() as $category) {
                $sm->insert(array(
                    'type'            => 'category',
                    'sort'            => $sort++,
                    'create_datetime' => date('Y-m-d H:i:s'),
                    'shared'          => 1,
                    'icon'            => null,
                    'category_id'     => $category['id']
                ));
            }
        }
    }

    protected function installTagsByContactsPro()
    {
        if (!$this->checkModel('contactsTagModel') ||
            !$this->checkModel('contactsContactTagsModel')) {
            return false;
        }
        $crm_tag_model = new crmTagModel();
        if ($crm_tag_model->countAll() > 0) {
            return false;
        }
        $this->reflectTables($crm_tag_model, new contactsTagModel(), array('id' => '`id`'));
        $this->reflectTables(new crmContactTagsModel(), new contactsContactTagsModel());
    }

    protected function installNotesByContactsPro()
    {
        if (!$this->checkModel('contactsNotesModel')) {
            return false;
        }

        $crm_note_model = new crmNoteModel();
        if ($crm_note_model->countAll() > 0) {
            return false;
        }

        $this->reflectTables(
            $crm_note_model,
            new contactsNotesModel(),
            array(
                'creator_contact_id' => '`create_contact_id`',
                'content'            => '`text`'
            )
        );

        $log_model = new crmLogModel();
        $insert_map = array_fill_keys(array_keys($log_model->getMetadata()), null);
        $insert_map = array_merge($insert_map, array(
            'id'               => "`id`",
            'create_datetime'  => "`create_datetime`",
            'contact_id'       => "`contact_id`",
            'actor_contact_id' => "`creator_contact_id`",
            'action'           => "'note_add'",
            'object_id'        => "`id`"
        ));
        $this->reflectTables(
            new crmLogModel(),
            new crmNoteModel(),
            $insert_map
        );
    }

    protected function installFormsByContactsPro()
    {
        if (!$this->checkModel('contactsFormModel') ||
            !$this->checkModel('contactsFormParamsModel')) {
            return false;
        }

        $crm_form_model = new crmFormModel();
        if ($crm_form_model->countAll() > 0) {
            return false;
        }

        $this->reflectTables(
            $crm_form_model,
            new contactsFormModel()
        );
        $this->reflectTables(
            new crmFormParamsModel(),
            new contactsFormParamsModel()
        );
    }

    /**
     * @param waModel $to_model
     * @param waModel $from_model
     * @param null|array $insert_map
     * @return bool
     */
    protected function reflectTables($to_model, $from_model, $insert_map = null)
    {
        $fields = array();
        $values = array();

        if ($insert_map === null) {
            $insert_map = array('id' => null);
        }

        foreach (array_keys($to_model->getMetadata()) as $field) {

            $inserted_value = null;
            if (array_key_exists($field, $insert_map)) {
                $inserted_value = $insert_map[$field];
                if ($inserted_value !== null && strpos($inserted_value, '`') !== false) {
                    $inserted_value = str_replace('`', '', $inserted_value);
                    if ($from_model->fieldExists($inserted_value)) {
                        $inserted_value = "`{$inserted_value}`";
                    } else {
                        $inserted_value = null;
                    }
                }
            } elseif ($from_model->fieldExists($field)) {
                $inserted_value = "`{$field}`";
            }

            if ($inserted_value === null) {
                continue;
            }

            $fields[] = "`{$field}`";
            $values[] = $inserted_value;
        }

        $fields = join(',', $fields);
        $values = join(',', $values);

        $from_table = $from_model->getTableName();
        $to_table = $to_model->getTableName();

        $sql = "INSERT INTO `{$to_table}`({$fields})
                  SELECT {$values} FROM `{$from_table}`";

        try {
            $to_model->exec($sql);
            return true;
        } catch (waDbException $e) {
        }
        return false;
    }

    protected function isContactsProInstalled()
    {
        if (wa()->appExists('contacts')) {
            $plugins = wa('contacts')->getConfig()->getPlugins();
            return !empty($plugins['pro']);
        }
        return false;
    }

    public function installCompany()
    {
        $cm = new crmCompanyModel();
        $sql = "INSERT INTO `{$cm->getTableName()}` SET name='".$cm->escape(wa()->accountName())."'";
        if ($cm->countAll() <= 0) {
            try {
                $cm->exec($sql);
                return true;
            } catch (waDbException $e) {
            }
        }
        return false;
    }

    public function installDealLost()
    {
        $reasons = array(
            _w('Rival was preferred'),
            _w('Junk lead'),
            _w('Not interested'),
            _w('Maybe in the future'),
            _w('Product is not required'),
            _w('Product does not satisfy'),
            _w('Price is too high')
        );

        $ins = array();
        for ($i = 0; $i < count($reasons); $i++) {
            $ins[] = array(
                'name' => $reasons[$i],
                'sort' => $i,
            );
        }
        $dlm = new crmDealLostModel();
        if ($dlm->countAll() <= 0) {
            try {
                $dlm->multipleInsert($ins);
                return true;
            } catch (waDbException $e) {
            }
        }
        return false;
    }

    public function uninstallAll()
    {
        $this->uninstallAlterTables();
    }

    public function uninstallAlterTables()
    {
        try {
            $m = new waModel();
            $m->exec("ALTER TABLE `wa_contact` DROP `crm_vault_id`");
            $clear_cache = true;
        } catch (waDbException $e) {
        }

        try {
            $m = new waModel();
            $m->exec("ALTER TABLE `wa_contact` DROP `crm_user_id`");
            $clear_cache = true;
        } catch (waDbException $e) {
        }

        if (!empty($clear_cache)) {
            $cache = new waRuntimeCache('db/default/wa_contact', -1, 'webasyst');
            $cache->clearAll();
            $cache = new waSystemCache('db/default/wa_contact', -1, 'webasyst');
            $cache->delete();
        }
    }

    public function installNotification()
    {
        $nm = new crmNotificationModel();
        if ($nm->countAll() <= 0) {
            $notifications = array_values(crmNotification::getNotificationVariants(array('customer.birthday')));
            $nm->multipleInsert($notifications);
        }
    }

    public function installSearchConfig()
    {
        // insert custom fields to search bar (by update search.php config)
        try {
            waFiles::delete(wa('crm')->getConfig()->getConfigPath('search/search.php'));
        } catch (Exception $e) {

        }

        $constructor = new crmFieldConstructor();
        $fields = $constructor->getAllFieldsPlainList();
        foreach ($fields as $field_id => $field) {

            $pf = waContactFields::get($field_id, 'person');
            $cf = waContactFields::get($field_id, 'company');

            if (!$pf && !$cf && crmContactsSearchHelper::isContactFieldEnabledForSearch($field)) {
                crmContactsSearchHelper::disableContactFieldForSearch($field);
            } elseif (($pf || $cf) && !crmContactsSearchHelper::isContactFieldEnabledForSearch($field)) {
                crmContactsSearchHelper::enableContactFieldForSearch($field);
            }
        }

        crmFieldConstructor::sortFieldsInSearchConfig($fields);
    }

    public function installVault()
    {
        $vm = new crmVaultModel();
        if ($vm->countAll() <= 0) {
            $vm->insert(array(
                'name'            => 'VIP',
                'create_datetime' => date('Y-m-d H:i:s'),
            ));
        }
    }

    protected function installCountry()
    {
        if (wa()->getLocale() == 'ru_RU') {
            $cm = new waCountryModel();
            if (!$cm->select('*')->where('fav_sort IS NOT NULL')->limit(1)->fetch() && $cm->getByField('iso3letter', 'rus')) {
                $cm->updateByField('iso3letter', 'rus', array('fav_sort' => 1));
            }
        }
    }

    /**
     * Install the templates and adds parameters to them
     */
    public function installTemplates()
    {
        $tm = new crmTemplatesModel();
        $tpm = new crmTemplatesParamsModel();

        if ($tm->countAll() <= 0) {
            $fields = $tm->getMetadata();
            $templates = crmTemplates::getTemplatesVariants(true);

            foreach ($templates as $t) {
                $data = array_intersect_key($t, $fields);
                $template_id = $tm->insert($data);

                if ($template_id) {
                    foreach ($t['template_params'] as &$param) {
                        $param['template_id'] = $template_id;
                    }
                    $tpm->multipleInsert($t['template_params']);
                }
            }
        }
    }

    public function pullLogsFromShop()
    {
        if (!crmShop::appExists()) {
            return;
        }
        $lm = new crmLogModel();

        $res = $lm->query('SELECT * FROM crm_log LIMIT 1')->fetch();
        if (!empty($res)) {
            return;
        }

        $limit = 1000000;
        $offset = 0;

        $startTime=time();
        try {
            while(time() - $startTime < 10) {
                $res = $lm->query("
                    INSERT IGNORE INTO crm_log (id, create_datetime, actor_contact_id, action, contact_id, object_id, object_type)
                    SELECT l.id, l.`datetime` AS create_datetime, 
                        IFNULL(l.contact_id, 0) AS actor_contact_id, l.action_id AS action, o.contact_id, l.id AS object_id, 'ORDER_LOG' AS object_type
                    FROM shop_order_log l
                    INNER JOIN shop_order o ON l.order_id=o.id
                    ORDER BY l.id DESC
                    LIMIT i:limit OFFSET i:offset
                ",
                [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);
                if (!$res->affectedRows()) {
                    break;
                }
                $offset += $limit;
            }
        } catch (waException $ex) {
            $error = join(PHP_EOL, [
                $ex->getMessage(),
                $ex->getTraceAsString()
            ]);
            waLog::log($error, 'crm/install.error.log');
        }
    }
}