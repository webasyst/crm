<?php

class crmConfig extends waAppConfig
{
    /**
     * @var waAppSettingsModel
     */
    protected $asm;

    const ROWS_PER_PAGE = 30;

    const API_CLIENT_ID = 'CRM-WEB-SPA';
    const API_TOKEN_SCOPE = 'crm';

    /**
     * @deprecated
     * @see isShopSupported php-doc for details
     */

    const SHOP_MINIMAL_VERSION = '7.2.4';
    /**
     * @deprecated
     * @see isShopSupported php-doc for details
     */
    const SHOP_MAXIMAL_MINIMAL_VERSION = '7.2.21';

    protected static $max_execution_time;

    protected $push_adapter = null;
    protected $onesignal_adapter = null;

    // see also a hack in FrontController->dispatch()
    public function getRouting($route = array())
    {
        if ($this->routes === null) {
            $this->routes = $this->getRoutingRules();
        }
        return $this->routes;
    }

    protected function getRoutingPath($type)
    {
        $is_13_ui = (wa('crm')->whichUI('crm') === '1.3');

        if ($type === null) {
            $type = $this->getEnvironment();
        }
        $filename = 'routing.php';
        if ($type === 'backend') {
            $filename = $is_13_ui ? 'routing.backend.php' : 'routing.backend-ui20.php';
        }
        $path = $this->getConfigPath($filename, true, $this->application);
        if (!file_exists($path)) {
            $path = $this->getConfigPath($filename, false, $this->application);
        }
        return $path;
    }

    protected function getRoutingRules($route = array())
    {
        $routes = array();
        if ($this->getEnvironment() === 'backend') {
            $path = $this->getRoutingPath('backend');
            if (file_exists($path)) {
                $routes = array_merge($routes, include($path));
            }
        }

        $path = $this->getRoutingPath('frontend');
        if (file_exists($path)) {
            $routes = array_merge($routes, include($path));
        }
        return array_merge($this->getPluginRoutes($route), $routes);
    }

    public function getContactFields()
    {
        return '*,photo_url_16,photo_url_32,photo_url_50,jobtitle,email,phone,_online_status,_event';
    }

    public function checkRights($module, $action)
    {
        $module_and_action = $module . $action;
        $only_for_admin = array('contactMerge', 'contactImport');
        foreach ($only_for_admin as $prefix) {
            $len = strlen($prefix);
            if (substr($module_and_action, 0, $len) == $prefix) {
                return wa()->getUser()->isAdmin('crm');
            }
        }

        // pages and controllers of settings section (module) accessible only for admin..
        $is_settings_section = substr($module, 0, 8) === 'settings';
        if ($is_settings_section) {
            // ..except general settings page (and proper save controller)
            // it accessible for everybody (cause there are personal settings block in this page)
            
            // $is_general_settings_page = substr($action, 0, 7) === 'general' || substr($module, 0, 15) === 'settingsGeneral';
            $is_general_settings_page = substr($action, 0, 8) === 'personal' || substr($module, 0, 16) === 'settingsPersonal';
            if (!$is_general_settings_page) {
                return wa()->getUser()->isAdmin('crm');
            }
        }

        return true;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return (string)$this->getSettingsModel()->get(
            $this->getApplication(),
            'currency',
            wa()->getLocale() == 'ru_RU' ? 'RUB' : 'USD'
        );
    }

    public function getMaxExecutionTime($default = null)
    {
        if (self::$max_execution_time === null) {
            self::$max_execution_time = (int)ini_get('max_execution_time');
        }
        if ($default === null) {
            $default = wa()->getEnv() === 'cli' ? 600 : 120;
        }
        return self::$max_execution_time > 0 ? self::$max_execution_time : $default;
    }

    protected function getPluginRoutes($route)
    {
        /**
         * Extend routing via plugin routes
         * @event routing
         * @param array $routes
         * @return array $routes routes collected for every plugin
         */
        $result = wa()->event(array($this->application, 'routing'), $route);
        $all_plugins_routes = array();
        foreach ($result as $plugin_id => $routing_rules) {
            if (!$routing_rules) {
                continue;
            }
            $plugin = str_replace('-plugin', '', $plugin_id);
            foreach ($routing_rules as $url => & $route) {
                if (!is_array($route)) {
                    list($route_ar['module'], $route_ar['action']) = explode('/', $route) + array(1 => '');
                    $route = $route_ar;
                }
                if (!array_key_exists('plugin', $route)) {
                    $route['plugin'] = $plugin;
                }
                $route['url'] = $url = ifempty($route, 'url', $url);
                $all_plugins_routes[$url] = $route;
            }
            unset($route);
        }
        return $all_plugins_routes;
    }

    /**
     * @return waAppSettingsModel
     */
    protected function getSettingsModel()
    {
        return $this->asm !== null ? $this->asm : ($this->asm = new waAppSettingsModel());
    }

    public function getExts()
    {
        $filepath = $this->getAppPath('lib/config/data/ext.php');
        if (file_exists($filepath)) {
            return include($filepath);
        }
        return null;
    }

    public function getExtImgUrl()
    {
        return wa()->getAppStaticUrl('crm') . 'img/filetypes/';
    }

    protected $log_types = array(
        'reminder' => array(
            "name" => "Reminders",
            "color" => "#c3b351"
        ),
        'note' => array(
            "name" => "Notes",
            "color" => "#a1c351"
        ),
        'file' => array(
            "name" => "Files",
            "color" => "#51c367"
        ),
        'invoice' => array(
            "name" => "Invoices",
            "color" => "#51bcc3"
        ),
        'deal' => array(
            "name" => "Deals",
            "color" => "#5159c3"
        ),
        'contact' => array(
            "name" => "Contacts",
            "color" => "#bc51c3"
        ),
        'message' => array(
            "name" => "Messages",
            "color" => "forestgreen"
        ),
        'call' => array(
            "name" => "Calls",
            "color" => "orange"
        ),
        'order_log' => array(
            "name" => "Orders",
            "color" => "#51c367"
        )
    );

    public function getLogType($object_type = null)
    {
        static $translated;
        if (!$translated) {
            array_walk($this->log_types, function(&$item) {
                $item['name'] = _w($item['name']);
            });
            $translated = true;
        }

        if (!$object_type) {
            return $this->log_types;
        } elseif (isset($this->log_types[$object_type])) {
            return $this->log_types[$object_type];
        } else {
            return array(
                "name" => _w("Unknown"),
                "color" => "#888"
            );
        }
    }

    protected $call_states = array(
        'PENDING' => array(
            "id" => "PENDING",
            "name" => "Pending",
            "color" => "#ff0000"
        ),
        'CONNECTED' => array(
            "id" => "CONNECTED",
            "name" => "Connected",
            "color" => "#00ff00"
        ),
        'DROPPED' => array(
            "id" => "DROPPED",
            "name" => "Dropped",
            "color" => "#ff2200"
        ),
        'REDIRECTED' => array(
            "id" => "REDIRECTED",
            "name" => "Redirected",
            "color" => "#51bcc3"
        ),
        'FINISHED' => array(
            "id" => "FINISHED",
            "name" => "Finished",
            "color" => "#5159c3"
        ),
        'VOICEMAIL' => array(
            "id" => "VOICEMAIL",
            "name" => "Voicemail",
            "color" => "#bc51c3"
        ),
    );

    protected $call_states_20 = array(
        'PENDING' => array(
            "id" => "PENDING",
            "name" => "Pending",
            "color" => "#ff0000"
        ),
        'CONNECTED' => array(
            "id" => "CONNECTED",
            "name" => "Connected",
            "color" => "#00ff00"
        ),
        'DROPPED' => array(
            "id" => "DROPPED",
            "name" => "Dropped",
            "color" => "#ED2509"
        ),
        'REDIRECTED' => array(
            "id" => "REDIRECTED",
            "name" => "Redirected",
            "color" => "#00C2ED"
        ),
        'FINISHED' => array(
            "id" => "FINISHED",
            "name" => "Finished",
            "color" => "#7256EE"
        ),
        'VOICEMAIL' => array(
            "id" => "VOICEMAIL",
            "name" => "Voicemail",
            "color" => "#bc51c3"
        ),
    );

    public function getCallStates($status_id = null)
    {
        static $translated;
        $is_20_ui = (wa('crm')->whichUI('crm') === '2.0');
        if (!$translated) {
            array_walk($this->log_types, function(&$item) {
                $item['name'] = _w($item['name']);
            });
            $translated = true;
        }

        if (isset($status_id)) {
            if ($is_20_ui) {
                return ifset($this->call_states_20, $status_id, [
                    'name'  => _w('Unknown'),
                    'color' => '#888'
                ]);
            }

            return ifset($this->call_states, $status_id, [
                'name'  => _w('Unknown'),
                'color' => '#888'
            ]);
        }
        if ($is_20_ui) return $this->call_states_20;

        return $this->call_states;
    }

    /**
     * @return int
     */
    public function getContactsPerPage()
    {
        $value = $this->getOption('contacts_per_page');
        if ($value === null) {
            $value = self::ROWS_PER_PAGE;
        }
        return (int)$value;
    }

    public function getContactsExportChunkSize()
    {
        $value = $this->getOption('contacts_export_chunk_size');
        if ($value === null) {
            $value = 100;
        }
        return (int)$value;
    }

    /**
     * @return int
     */
    public function getMaxKanbanDeals()
    {
        $value = $this->getOption('max_kanban_deals');
        if ($value === null) {
            $value = 1000;
        }
        return (int)$value;
    }

    /**
     * @param null $plugin_id
     * @return null|crmPluginTelephony|crmPluginTelephony[]
     * @throws Exception
     */
    public function getTelephonyPlugins($plugin_id = null)
    {
        static $plugins = null;
        if ($plugins === null) {
            $plugins = array();
            foreach ($this->getPlugins() as $pid => $plugin_info) {
                $class_name = 'crm' . ucfirst($pid) . 'PluginTelephony';
                if (isset($plugin_info['telephony'])) {
                    $class_name = $plugin_info['telephony'];
                }
                if ($class_name && class_exists($class_name)) {
                    try {
                        $plugins[$pid] = new $class_name($plugin_info);
                        if (!$plugins[$pid] instanceof crmPluginTelephony) {
                            unset($plugins[$pid]);
                        }
                    } catch (Exception $e) {
                        if (waSystemConfig::isDebug()) {
                            throw $e;
                        }
                    }
                }
            }
        }

        if ($plugin_id === null) {
            return $plugins;
        } else {
            if (isset($plugins[$plugin_id])) {
                return $plugins[$plugin_id];
            } else {
                return null;
            }
        }
    }

    public static function getReminderType($key = null)
    {
        $types = array(
            'MEETING' => array(
                "id" => "MEETING",
                "name" => _w('Meeting'),
                "icon" => "cup"
            ),
            'CALL' => array(
                "id" => "CALL",
                "name" => _w('Call'),
                "icon" => "phone"
            ),
            'MESSAGE' => array(
                "id" => "MESSAGE",
                "name" => _w('Message'),
                "icon" => "email"
            ),
            'OTHER' => array(
                "id" => "OTHER",
                "name" => _w('Other'),
                "icon" => "clock"
            )
        );
        if ($key) {
            return ifempty($types[$key], $key);
        }

        return $types;
    }

    public static function getReminderTypeUI2($key = null)
    {
        $types = array(
            'MEETING' => array(
                "id" => "MEETING",
                "name" => _w('Meeting'),
                "icon" => "coffee"
            ),
            'CALL' => array(
                "id" => "CALL",
                "name" => _w('Call'),
                "icon" => "phone-alt"
            ),
            'MESSAGE' => array(
                "id" => "MESSAGE",
                "name" => _w('Message'),
                "icon" => "envelope"
            ),
            'OTHER' => array(
                "id" => "OTHER",
                "name" => _w('Other'),
                "icon" => "clock"
            )
        );
        if ($key) {
            return ifempty($types[$key], $key);
        }

        return $types;
    }

    /**
     * Old method for checking supporting feature of crm integrated with shop script
     *
     * You should not use it anymore for new integration features (especially for new integrated feature)
     * For new integration feature use crmShop::getIntegrationConfig/crmShop::isIntegrationSupported mechanism:
     *   - declare feature ID (const with prefix INTEGRATION_ in crmShop class)
     *   - describe this feature and min version of shop script that can support this feature
     *
     * @see crmShop::getIntegrationConfig
     * @see crmShop::isIntegrationSupported
     *
     * @deprecated
     *
     * @param bool $version_level - Version level, very poor semantic, impossible understand meanings of "level".
     *          Cause of poor semantic this method is @depecated. Your must not use it anymore
     *
     *   - FALSE or something that auto converted to FALSE - the most minimal version of shop script (now correspond to crmShop::INTEGRATION_ANY)
     *   - TRUE or something that auto converted to TRUE  - historically introduced for features that declared now by
     *          constants crmShop::INTEGRATION_SYNC_CURRENCIES, crmShop::INTEGRATION_SYNC_WORKFLOW_FUNNELS
     * @return bool
     */
    public static function isShopSupported($version_level = null)
    {
        $minimal_version = !$version_level ? crmConfig::SHOP_MINIMAL_VERSION : crmConfig::SHOP_MAXIMAL_MINIMAL_VERSION;
        return wa()->appExists('shop') && version_compare(wa()->getVersion('shop'), $minimal_version) >= 0;
    }

    public static function getFunnelBaseStages($id = false)
    {
        $stages = array();
        $stage_names = array(
            _w('Lead In'),
            _w('Needs Defining'),
            _w('Working with objections'),
            _w('Proposal Making'),
            _w('Conditions Approval')
        );

        for ($i = 0; $i < count($stage_names); $i++) {
            $stages[] = array(
                'funnel_id' => $id,
                'name' => $stage_names[$i],
                'number' => $i + 1,
            );
        }
        return $stages;
    }

    public function onCount()
    {
        // some background works
        crmShop::currenciesCopy();

        // extract app counter settings
        $app_counter_settings = wa()->getUser()->getSettings('crm', 'app_counter');
        if ($app_counter_settings) {
            $app_counter_settings = json_decode($app_counter_settings, true);
        }
        if (!$app_counter_settings || !is_array($app_counter_settings)) {
            $app_counter_settings = array(
                'new_messages' => 1,
                'new_deals' => 1,
                'overdue_reminders' => 1
            );
        }

        $user = wa()->getUser();

        $counter = 0;

        if (!empty($app_counter_settings['new_messages'])) {
            $mm = new crmMessageModel();

            $messages_new_count = 0;
            $messages_max_id = (int)$user->getSettings("crm", "messages_max_id", "0");
            if ($messages_max_id > 0) {
                $messages_new_count = $mm->getList(array(
                    'count_results' => 'only',
                    'cache' => 60,
                    'check_rights' => true,
                    'min_id' => $messages_max_id
                ));
            }

            if ($messages_new_count > 0) {
                $counter += $messages_new_count;
            }
        }

        if (!empty($app_counter_settings['new_deals'])) {
            $deal_max_id = waRequest::cookie('deal_max_id', 0, waRequest::TYPE_INT);
            $deals_new_count  = $deal_max_id ? crmDeal::getNewCount($deal_max_id) : 0;
            if ($deals_new_count > 0) {
                $counter += $deals_new_count;
            }
        }

        if (!empty($app_counter_settings['overdue_reminders'])) {
            $rm = new crmReminderModel();
            $counts = $rm->getUsersCounts($user->getId(), array(
                'overdue' => true
            ));
            if (!empty($counts['due_count'])) {
                $counter += $counts['due_count'];
            }
        }

        return $counter > 0 ? $counter : null;
    }

    public function setLastVisitedUrl($app_url)
    {
        wa()->getUser()->setSettings('crm', 'last_url', ltrim($app_url, '/'));
    }

    /**
     * Get common for whole app prefix transformation setting
     * @return array $result
     *      string|int $result['input_code']
     *      string|int $result['output_code']
     */
    public function getPhoneTransformPrefix()
    {
        $prefix_config = $this->getSettingsModel()->get('crm', 'phone_transform_prefix');
        if ($prefix_config) {
            $prefix_config = json_decode($prefix_config, true);
        }

        $is_valid_type = is_array($prefix_config) &&
                    isset($prefix_config['input_code']) && is_scalar($prefix_config['input_code']) &&
                    isset($prefix_config['output_code']) && is_scalar($prefix_config['output_code']);

        if (!$is_valid_type) {
            $prefix_config = [
                'input_code' => '',
                'output_code' => '',
            ];
        }
        $prefix_config['input_code'] = trim(strval($prefix_config['input_code']));
        $prefix_config['output_code'] = trim(strval($prefix_config['output_code']));
        return $prefix_config;
    }

    /**
     * Set common for whole app prefix transformation setting (save right aways in DB)
     * @param array $setting
     *      string|int $setting['input_code']
     *      string|int $setting['output_code']
     *
     */
    public function setPhoneTransformPrefix(array $setting)
    {
        if (!isset($setting['input_code']) || !wa_is_int($setting['input_code'])) {
            $setting['input_code'] = '';
        }
        if (!isset($setting['output_code']) || !wa_is_int($setting['output_code'])) {
            $setting['output_code'] = '';
        }
        $this->getSettingsModel()->set('crm', 'phone_transform_prefix', json_encode($setting));
    }

    public function explainLogs($logs)
    {
        $logs = parent::explainLogs($logs);
        $explainer = new crmWaLogExplainer($logs);
        return $explainer->explain();
    }

    public function getPushAdapter($force_adapter = null)
    {
        if (!empty($this->push_adapter) && $force_adapter !== 'onesignal') {
            return $this->push_adapter;
        }
        if (!empty($this->onesignal_adapter) && $force_adapter === 'onesignal') {
            return $this->onesignal_adapter;
        }

        $push_adptr = null;
        if ($force_adapter !== 'onesignal') {
            try {
                $push_adptr = wa()->getPush();
                if (empty($push_adptr) || $push_adptr->getId() == 'pushcrew' || !$push_adptr->isEnabled()) {
                    $push_adptr = null;
                }
            } catch (waException $ex) {
            }
        }

        if (empty($push_adptr) || $push_adptr->getId() == 'onesignal') {
            $mobile_push_only = empty($push_adptr);
            $onesignalPushClassFile = $this->getPath('system'). '/push/adapters/onesignal/onesignalPush.class.php';
            if (file_exists($onesignalPushClassFile)) {
                require_once($onesignalPushClassFile);
                if (class_exists('onesignalPush')) {
                    $push_adptr = new crmPushAdapter();
                    $push_adptr->setMobilePushOnly($mobile_push_only);
                }
            }
        }

        $this->push_adapter = $push_adptr;
        return $this->push_adapter;
    }
}
