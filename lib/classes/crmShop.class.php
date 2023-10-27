<?php

class crmShop
{
    protected $order_id;
    protected static $app_settings_model;

    /**
     * Constants that represent special features integrated with shop script and demands min version of shop script
     * This constant need to be pass to special public static method isIntegrationSupported
     * For meanings each constant see getIntegrationConfig method
     *
     * @see isIntegrationSupported
     * @see getIntegrationConfig
     *
     */
    const INTEGRATION_EDIT_ORDER                    = 'edit_order';
    const INTEGRATION_SHOW_SHIPPING_PACKAGE_INFO    = 'shop_shipping_package_info';
    const INTEGRATION_EXPLAIN_ORDER_LOG             = 'explain_order_log';
    const INTEGRATION_SYNC_CURRENCIES               = 'sync_currencies';
    const INTEGRATION_SYNC_WORKFLOW_FUNNELS         = 'sync_workflow_funnels';
    const INTEGRATION_ANY                           = 'any';

    /**
     * crmShop constructor.
     * @param int $order_id
     */
    public function __construct($order_id = null)
    {
        if ($order_id) {
            $this->order_id = $order_id;
        }
    }

    public function workflowPrepare($deal, $after_stage, $before_stage, $force_execute = false, $ui = null)
    {
        $ui = (empty($ui) ? wa('crm')->whichUI('crm') : $ui);
        $actions_path = ($ui === '1.3' ? 'actions-legacy' : 'actions');
        if (!is_array($after_stage)) {
            $after_stage_name = _w(ucfirst(strtolower($after_stage)));
            $after_stage_id = strtolower($after_stage);
        } else {
            $after_stage_name = $after_stage['name'];
            $after_stage_id = $after_stage['id'];
        }

        $this->order_id = self::getOrderId($deal);
        if (!$this->order_id) {
            return null;
        }
        $over_jump_result = null;
        if (!$force_execute && is_array($after_stage) && ($after_stage['number'] - $before_stage['number'] > 1 || $after_stage['number'] < $before_stage['number'])) {
            $view = wa()->getView();
            $view->assign(array(
                'deal'       => $deal,
                'stage_name' => $after_stage_name,
            ));
            $over_jump_result = array(
                'html' => $view->fetch(wa()->getAppPath("templates/$actions_path/deal/DealMoveOverJump.html", 'crm')),
            );
        }

        try {
           if (!$force_execute && !self::hasRights() && crmConfig::isShopSupported()) {
                $view = wa()->getView();
                $view->assign(array(
                    'order_number' => shopHelper::encodeOrderId($this->order_id),
                    'stage_name'   => $after_stage_name,
                ));
                return [
                    'html' => $view->fetch(wa()->getAppPath("templates/$actions_path/deal/DealMoveNoRights.html", 'crm')),
                ];
            }

            if ((is_array($after_stage) || (!is_array($after_stage) && $deal['status_id'] == 'OPEN'))
                && !empty($deal['external_id'])
                && $this->order_id
                && crmConfig::isShopSupported()
                && self::hasRights()
            ) {
                $asm = new waAppSettingsModel();
                $stage = $after_stage_id;

                wa('shop', true);
                $som = new shopOrderModel();
                $shop_order = $som->getOrder($this->order_id);

                if ($shop_order) {

                    $workflow = new shopWorkflow();

                    $setting_actions = $asm->select('name')->where("value='".$asm->escape($stage)."' AND name LIKE 'shop:%'")->fetchAll('name', true);
                    $state = $workflow->getStateById($shop_order['state_id']);
                    $actions = $state->getActions($shop_order);
                    list($action_id, $action) = $this->getActionByFunnelId($setting_actions, $actions, $deal['funnel_id']);

                    if ($action) {

                        $html = gettype($action) == 'object' ? $action->getHTML($shop_order['id']) : null;
                        if ($html) {
                            $result = array(
                                'html'         => $html,
                                'order_number' => shopHelper::encodeOrderId($this->order_id),
                                'action_name'  => $action->getName(),
                                'state_name'   => $state->getName(),
                            );
                            // display dialog
                            wa('crm', true);
                            $view = wa()->getView();
                            $view->assign($result);
                            return [
                                'html' => $view->fetch(wa()->getAppPath("templates/$actions_path/deal/DealMoveShopDialog.html", 'crm')),
                                'order_id' => $this->order_id,
                                'action_id' => $action_id
                            ];
                        } else {

                            if (!$over_jump_result) {
                                // stop event handling
                                $lm = new crmLogModel();
                                $lm->log('deal_order_'.$action->getId(), -$deal['id']);

                                // perform action and reload
                                $action->run($shop_order['id']);
                                wa('crm', true);
                                return null;
                            } else {
                                return $over_jump_result;
                            }
                        }
                    } elseif (!$force_execute) {

                        $actions = $workflow->getAvailableActions();
                        list($action_id, $action) = $this->getActionByFunnelId($setting_actions, $actions, $deal['funnel_id']);
                        if ($action) {
                            // display confirm
                            wa('crm', true);

                            $view = wa()->getView();
                            $view->assign(array(
                                'order_number' => shopHelper::encodeOrderId($this->order_id),
                                'state_name'   => $state->getName(),
                                'action_name'  => $action['name'],
                                'stage_name'   => $after_stage_name,
                            ));
                            return [
                                'html' => $view->fetch(wa()->getAppPath("templates/$actions_path/deal/DealMoveActionUnavailable.html", 'crm')),
                            ];
                        }
                    }
                }
            }
        } catch (waException $e) {
        }
        wa('crm', true);
        return $over_jump_result;
    }

    protected function getActionByFunnelId($setting_actions, $actions, $funnel_id)
    {
        if ($setting_actions && $actions) {
            foreach ($actions as $action_id => $a) {
                if (isset($setting_actions['shop:'.$action_id.'_'.$funnel_id])) {
                    $action = $actions[$action_id];
                    return [$action_id, $action];
                }
            }
        }
        return null;
    }

    /**
     * @param int|array|shopOrder $order
     * @return bool
     * @throws waException
     */
    public static function canEditOrder($order)
    {
        if (!self::isIntegrationSupported(self::INTEGRATION_EDIT_ORDER) || !self::hasAccess()) {
            return false;
        }

        // typecasting input parameter
        if (is_scalar($order)) {
            $order_id = (int)$order;
            if ($order_id <= 0) {
                return false;
            }
            $som = new shopOrderModel();
            $order = $som->getOrder($order_id);
        }

        $correct_type = $order instanceof shopOrder || is_array($order);

        // we must have certain data type: associative array with certain array
        if (!$correct_type || !isset($order['state_id'])) {
            return false;
        }

        $workflow = new shopWorkflow();
        $state = $workflow->getStateById($order['state_id']);
        $actions = $state->getActions(null, true);
        return isset($actions['edit']);
    }

    public static function canCreateOrder()
    {
        return self::isIntegrationSupported(self::INTEGRATION_EDIT_ORDER) && self::hasAccess();
    }

    public static function canExplainOrderLog()
    {
        return self::isIntegrationSupported(self::INTEGRATION_EXPLAIN_ORDER_LOG) && self::hasAccess();
    }

    /**
     * Can show order shipping package info in deal page
     * @return bool
     */
    public static function canShowShippingPackageInfo()
    {
        return self::isIntegrationSupported(self::INTEGRATION_SHOW_SHIPPING_PACKAGE_INFO) && self::hasAccess();
    }

    /**
     * Is shop in welcome stage
     * @return true
     * @throws waDbException
     */
    public static function inWelcomeStage()
    {
        $app_settings_model = new waAppSettingsModel();
        return (bool)$app_settings_model->get('shop', 'welcome');
    }

    public static function getOrderByDeal($deal)
    {
        $order = null;
        if ($order_id = self::getOrderId($deal)) {

            $old_app = wa()->getApp();

            try {

                // need set_current=true inside of shopHelper::workupOrders we load shopWorkflow::$config with applied _w by shop app

                wa('shop', true);
                $som = new shopOrderModel();
                $order = $som->getOrder($order_id);

                $customer_delivery = shopHelper::getOrderCustomerDeliveryTime(ifset($order['params']));
                $order['customer_delivery_date'] = $customer_delivery[0];

                shopHelper::workupOrders($order, true);

                $workflow = new shopWorkflow();
                $order['state'] = $workflow->getStateById($order['state_id']);

                // $shop_order['shipping_address'] = shopHelper::getOrderAddress($shop_order['params'], 'shipping');
                // $shop_order['billing_address'] = shopHelper::getOrderAddress($shop_order['params'], 'billing');

            } catch (waException $e) {
            }

            wa($old_app, true);
        }
        return $order;
    }

    /**
     * @param $deal
     * @return shopOrder|null
     */
    public static function getShopOrderByDeal($deal)
    {
        $order_id = self::getOrderId($deal);
        if (!$order_id) {
            return null;
        }

        $shop_order = null;
        try {
            wa('shop');
            $shop_order = new shopOrder($order_id);
        } catch (waException $e) {
        }

        wa('crm')->setActive('crm');

        return $shop_order;
    }

    public static function getOrderId($deal)
    {
        if (!empty($deal['external_id']) && preg_match('/^shop:(\d+)$/', $deal['external_id'], $m)) {
            return intval($m[1]);
        }
        return null;
    }

    public static function getEncodedOrderId($deal)
    {
        if (($order_id = self::getOrderId($deal)) && crmConfig::isShopSupported()) {
            try {
                wa('shop');
                return shopHelper::encodeOrderId($order_id);
            } catch (waException $e) {
                // in case when e.g. shop meta-update failed to load
            }
        }
        return null;
    }

    /**
     * Has rights to manager orders
     * @return bool
     */
    public static function hasRights()
    {
        try {
            return wa()->appExists('shop') && intval(wa('shop')->getUser()->getRights('shop', 'orders')) != '0';
        } catch (waException $e) {
            return false; // in case when e.g. shop meta-update failed to load
        }
    }

    /**
     * Has access at all
     * @return bool
     */
    public static function hasAccess()
    {
        try {
            return wa()->appExists('shop') && wa('shop')->getUser()->getRights('shop', 'backend') >= 1;
        } catch (waException $e) {
            return false; // in case when e.g. shop meta-update failed to load
        }
    }

    public static function currenciesCopy()
    {
        if (self::getSettingsModel()->get('crm', 'currency') == self::getSettingsModel()->get('shop', 'currency')) {
            $currency = new crmCurrency();
            $currency->copy();
        }
    }

    /**
     * @param array $options
     * @return null|array
     */
    public static function cliCurrenciesCopy()
    {
        self::getSettingsModel()->set('crm', 'currencies_copy_cli_start', date('Y-m-d H:i:s'));
        self::executeCliCurrenciesCopy();
        self::getSettingsModel()->set('crm', 'currencies_copy_cli_end', date('Y-m-d H:i:s'));
    }

    protected static function executeCliCurrenciesCopy()
    {
        if (!crmConfig::isShopSupported() || !self::getSettingsModel()->get('crm', 'use_shop_currencies')) {
            return null;
        }

        /**
         * @event start_currencies_copy_worker
         */
        wa('crm')->event('start_currencies_copy_worker');

        $currency = new crmCurrency();
        $count = $currency->copy();

        return array(
            'total_count'     => $count,
            'processed_count' => $count,
            'count'           => $count,
            'done'            => $count,
        );
    }

    public static function getLastCliRunDateTime()
    {
        return self::getSettingsModel()->get('crm', 'currencies_copy_cli_end');
    }

    public static function isCliOk()
    {
        return !!self::getLastCliRunDateTime();
    }

    protected static function getSettingsModel()
    {
        return !empty(self::$app_settings_model) ? self::$app_settings_model : (self::$app_settings_model = new waAppSettingsModel());
    }

    /**
     * Get correspondence map: Integrated with shop functionality => min version
     * @return array: <feature_id> => array (descriptor)
     *
     *   Descriptor (array) format:
     *     - string 'min'         - min version of shop script for supporting this integration feature
     *
     *  NOTICE:
     *  Descriptor is array for flexibility extending in feature, for example:
     *    - if we will want add 'max' version limit (for old features that no more need to support after particular version of shop script)
     *    - 'description' key, if we will want print this information in some explaining page about feature of crm integrated with shop script
     *    - ...e.t.c
     */
    protected static function getIntegrationConfig()
    {
        static $config;
        if ($config === null) {
            $config = array(

                // Edit or create order from deal page
                self::INTEGRATION_EDIT_ORDER => array(
                    'min' => '8.4.7'
                ),

                // Explain order log in deal page, contact page and 'live' page (everywhere where crm log explained)
                self::INTEGRATION_EXPLAIN_ORDER_LOG => array(
                    'min' => '8.4.7'
                ),

                // Show shipping package info of order in deal page
                self::INTEGRATION_SHOW_SHIPPING_PACKAGE_INFO => array(
                    'min' => '8.4.7'
                ),

                // Synchronization currencies
                self::INTEGRATION_SYNC_CURRENCIES => array(
                    'min' => '7.2.21'
                ),

                // Synchronization shop script workflow and deal funnels
                self::INTEGRATION_SYNC_WORKFLOW_FUNNELS => array(
                    'min' => '7.2.21'
                ),

                // Any other not listed (not declared explicitly) features, MUST be the min among all version variants for listed features
                // If you add new specific feature in CRM tied with shop script you MUST explicitly declare feature ID (constant) AND min version of shop script
                self::INTEGRATION_ANY => array(
                    'min' => '7.2.4'
                ),
            );
        }
        return $config;
    }

    /**
     * Is integrated feature supported by current version of shop script?
     * @param string $feature_id - must be crmShop::INTEGRATION_ constant
     * @see getIntegrationConfig
     * @see getIntegrationMinVersion
     * @return bool
     */
    public static function isIntegrationSupported($feature_id)
    {
        if (!wa()->appExists('shop')) {
            return false;
        }

        $config = self::getIntegrationConfig();
        if (isset($config[$feature_id])) {

            $descriptor = $config[$feature_id];
            $shop_version = wa()->getVersion('shop');

            if (version_compare($shop_version, $descriptor['min']) >= 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Min version of shop script that supporting this integrated feature
     * @param string $feature_id - must be crmShop::INTEGRATION_ constant
     * @see getIntegrationConfig
     * @see isIntegrationSupported
     * @return string
     */
    public static function getIntegrationMinVersion($feature_id)
    {
        if (!wa()->appExists('shop')) {
            return '0';
        }

        $config = self::getIntegrationConfig();
        if (isset($config[$feature_id])) {
            $descriptor = $config[$feature_id];
            return $descriptor['min'];
        }

        return '0';
    }

    /**
     * Shop app exists
     * @return bool
     */
    public static function  appExists()
    {
        return wa()->appExists('shop');
    }

}
