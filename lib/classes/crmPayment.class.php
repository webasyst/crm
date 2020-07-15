<?php

class crmPayment extends waAppPayment
{
    private static $instance;
    private static $instance_id;
    private static $invoice;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function init()
    {
        $this->app_id = 'crm';
        parent::init();
    }

    /**
     *
     * @return crmPaymentSettingsModel
     */
    private function model()
    {
        static $model;
        if (!$model) {
            $model = new crmPaymentSettingsModel();
        }
        return $model;
    }

    /**
     * @return crmPaymentModel
     */
    private static function pluginModel()
    {
        static $model;
        if (!$model) {
            $model = new crmPaymentModel();
        }
        return $model;
    }

    /**
     *
     * @param string $plugin plugin identity string (e.g. PayPal/WebMoney)
     * @param int $merchant_id plugin instance merchant id
     * @throws waException
     * @return waPayment
     */
    public static function getPlugin($plugin, $merchant_id)
    {
        return waPayment::factory($plugin, $merchant_id, self::getInstance());
        /*
                if (!$plugin && $instance_id) {
                    $info = self::getPluginData($instance_id);
                    if (!$info) {
                        throw new waException("Payment plugin {$plugin} not found", 404);
                    }
                    $merchant_id = $info['company_id'];
                    $plugin = $info['plugin'];
                }
        */
    }

    public static function getPluginById($instance_id)
    {
        $info = self::pluginModel()->getById($instance_id);
        if (!$info) {
            throw new waException("Payment plugin {$instance_id} not found", 404);
        }
        return self::getPlugin($info['plugin'], $info['company_id']);
    }

    public static function getPluginInfo($id)
    {
        $plugin_id = max(0, intval($id));
        if ($plugin_id) {
            $info = self::getPluginData($plugin_id);
            if (!$info) {
                throw new waException("Payment plugin {$plugin_id} not found", 404);
            }
        } else {
            $info = array(
                'plugin' => $id,
                'status' => 1,
            );
        }
        $default_info = waPayment::info($info['plugin']);
        return is_array($default_info) ? array_merge($default_info, $info) : $default_info;
    }

    public static function getList()
    {
        if (!class_exists('waPayment')) {
            throw new waException(_w('Payment plugins not installed yet'));
        }
        $list = waPayment::enumerate();
        return $list;
    }

    private static function getPluginData($instance_id)
    {
        return self::pluginModel()->getById($instance_id);
    }

    public static function savePlugin($plugin)
    {
        if (waConfig::get('is_template')) {
            return;
        }
        $default = array(
            'status' => 0,
        );
        $plugin = array_merge($default, $plugin);
        $model = self::pluginModel();

        if (!empty($plugin['instance_id']) && ($id = max(0, intval($plugin['instance_id']))) && ($row = $model->getByField(array('id' => $id)))) {
            $plugin['plugin'] = $row['plugin'];
            $plugin['id'] = $id;
            $model->updateById($plugin['id'], $plugin);
        } elseif (!empty($plugin['plugin'])) {
            $plugin['sort'] = $model->select('MAX(sort) ms')->fetchField('ms') + 1;
            unset($plugin['id']);
            $plugin['id'] = $model->insert($plugin);
        }
        if (!empty($plugin['id']) && isset($plugin['settings'])) {
            waPayment::factory($plugin['plugin'], $plugin['company_id'], self::getInstance())->saveSettings($plugin['settings']);
        }
        if (!empty($plugin['id'])) {
            $plugins = $model->listPlugins();
            $app_settings = new waAppSettingsModel();
            $settings = json_decode($app_settings->get('crm', 'payment_'.$plugin['id'], '{}'), true);
            if (empty($settings) || !is_array($settings)) {
                $settings = array();
            }
            if (!isset($settings[$plugin['id']])) {
                $settings[$plugin['id']] = array();
            }
            $s =& $settings[$plugin['id']];
            foreach ($plugins as $item) {
                $key = array_search($item['id'], $s);
                if ($key !== false) {
                    unset($s[$key]);
                }
            }
            $s = array_unique($s);

            if (empty($s)) {
                unset($settings[$plugin['id']]);
            }
            $app_settings->set('crm', 'payment_'.$plugin['id'], json_encode($settings));
        }
        return $plugin;
    }

    public function getSettings($plugin, $merchant_id)
    {
        $this->merchant_id = (int)$merchant_id;
        $info = self::pluginModel()->getByField(array('plugin' => $plugin, 'company_id' => $this->merchant_id));
        if (!$info) {
            throw new waException('Plugin not found', 404);
        }
        self::$instance_id = $info['id'];
        return $this->model()->get(self::$instance_id);
    }

    public function setSettings($plugin_id, $key, $name, $value)
    {
        $this->model()->set(self::$instance_id, $name, $value);
    }

    public function cancel()
    {

    }

    public function refund($params)
    {
        $result = null;
        if (!empty($params['transaction']['plugin']) && !empty($params['transaction']['merchant_id'])) {
            $this->merchant_id = $params['transaction']['merchant_id'];
            $module = waPayment::factory($params['transaction']['plugin'], $this->merchant_id, self::getInstance());
            if ($module instanceof waIPaymentRefund) {
                $result = $module->refund(array(
                    'transaction'   => $params['transaction'],
                    'refund_amount' => $params['refund_amount']
                ));
                $params = array('invoice' => self::$invoice, 'transaction' => $params['transaction']);
                wa('crm')->event('invoice_refund', $params);
            }
        }
        return $result;
    }

    public function auth()
    {

    }

    public function payment()
    {

    }

    public function capture($params)
    {
        $result = null;

        if (empty($params['transaction']['plugin']) || empty($params['transaction']['merchant_id'])) {
            throw new waException('Payment system instance not found');
        }
        if (empty($params['transaction']['state']) || $params['transaction']['state'] != waPayment::STATE_AUTH) {
            throw new waException('Error: invalid transaction state');
        }
        $module = waPayment::factory($params['transaction']['plugin'], $params['transaction']['merchant_id'], self::getInstance());

        if ($module instanceof waIPaymentCapture) {
            $result = $module->capture(array(
                'transaction' => $params['transaction']
            ));
        }
        return $result;
    }

    public function void($params)
    {
        $result = null;
        if (empty($params['transaction']['plugin']) || empty($params['transaction']['merchant_id'])) {
            throw new waException('Payment system instance not found');
        }
        if (empty($params['transaction']['state']) || $params['transaction']['state'] != waPayment::STATE_AUTH) {
            throw new waException('Error: invalid transaction state');
        }
        $module = waPayment::factory($params['transaction']['plugin'], $params['transaction']['merchant_id'], self::getInstance());
        if ($module instanceof waIPaymentCancel) {
            $result = $module->cancel(array(
                'transaction' => $params['transaction']
            ));
        }
        return $result;
    }

    public function paymentForm()
    {

    }

    public function getBackUrl($type = self::URL_SUCCESS, $transaction_data = array())
    {
        if (!empty($transaction_data['order_id'])) {
            $im = new crmInvoiceModel();
            if ($invoice = $im->getById($transaction_data['order_id'])) {
                return wa()->getRouteUrl(
                    'crm/frontend/invoice',
                    array('hash' => crmHelper::getInvoiceHash($invoice)),
                    true
                ).'?result='.$type;
            }
        } elseif ($invioce_id = wa()->getStorage()->get('crm/invoice_id')) {
            $im = new crmInvoiceModel();
            if ($invoice = $im->getById($invioce_id)) {
                switch ($type) {
                    case self::URL_SUCCESS:
                        return wa()->getRouteUrl(
                            'crm/frontend/invoice',
                            array('hash' => crmHelper::getInvoiceHash($invoice)),
                            true
                        ).'?result='.$type;
                        break;
                }
            }
        }
        return null;
    }

    protected function callbackAction($transaction_data, $check_amount = false)
    {
        $result = array();

        if (!$this->merchant_id) {
            $result['error'] = 'Invalid merchant ID';
        } else {
            $im = new crmInvoiceModel();
            self::$invoice = $im->getById($transaction_data['order_id']);
            if (!self::$invoice) {
                $result['error'] = 'Invoice not found';
            } else {
                $iim = new crmInvoiceItemsModel();
                self::$invoice['items'] = $iim->getItems(self::$invoice['id']);
            }
            if (!empty($result['error'])) {
                $transaction_data['callback_declined'] = $result['error'];
            }
            if (empty($transaction_data['customer_id']) && !empty(self::$invoice['contact_id'])) {
                $result['customer_id'] = self::$invoice['contact_id'];
            }
        }
        return $result;
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackPaymentHandler($transaction_data)
    {
        $result = $this->callbackAction($transaction_data, true);
        if (empty($result['error'])) {
            $im = new crmInvoiceModel();

            if (in_array($transaction_data['state'], array(waPayment::STATE_AUTH, waPayment::STATE_CAPTURED))) {
                if (in_array($transaction_data['type'], array(waPayment::OPERATION_AUTH_CAPTURE, waPayment::OPERATION_CAPTURE), true)) {
                    if ($this->isAmountValid($transaction_data, $result['error'])) {

                        $im->updateById(
                            self::$invoice['id'],
                            array(
                                'state_id'         => 'PAID',
                                'payment_datetime' => date('Y-m-d H:i:s'),
                                'update_datetime'  => date('Y-m-d H:i:s'),
                            )
                        );
                        $result['result'] = true;

                        /**
                         * @event invoice_payment
                         * @param array [string]mixed $params
                         * @param array [string]array $params['invoice']
                         * @return bool
                         */
                        $params = array('invoice' => self::$invoice);
                        wa('crm')->event('invoice_payment', $params);
                        /**
                         * @event invoice_paid
                         * @param array [string]mixed $params
                         * @return bool
                         */
                        $params = array('invoice' => self::$invoice, 'transaction' => $transaction_data);
                        wa('crm')->event('invoice_paid', $params);
                    }
                } elseif ($transaction_data['type'] == waPayment::OPERATION_AUTH_ONLY) {

                    $im->updateById(
                        self::$invoice['id'],
                        array(
                            'state_id' => 'PROCESSING',
                        )
                    );
                }
            } elseif ($transaction_data['type'] == waPayment::OPERATION_CANCEL) {
                if (self::$invoice['state_id'] == 'PROCESSING') {

                    $im->updateById(
                        self::$invoice['id'],
                        array(
                            'state_id' => 'PENDING',
                        )
                    );
                }
            }
        }
        return $result;
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackCancelHandler($transaction_data)
    {
        return $this->callbackPaymentHandler($transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackDeclineHandler($transaction_data)
    {
        return $this->callbackPaymentHandler($transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackRefundHandler($transaction_data)
    {
        return $this->callbackPaymentHandler($transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackCaptureHandler($transaction_data)
    {
        return $this->callbackPaymentHandler($transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackNotifyHandler($transaction_data)
    {
        return $this->callbackPaymentHandler($transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackChargebackHandler($transaction_data)
    {
        return null;
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackConfirmationHandler($transaction_data)
    {
        $result = $this->callbackAction($transaction_data);
        if (empty($result['error'])) {
            if (!empty($result['order_id'])) {
                $transaction_data['order_id'] = $result['order_id'];
            }
            $result['result'] = true;

            $error = null;
            if (!$this->isAmountValid($transaction_data, $error) || self::$invoice['state_id'] != 'PENDING') {
                $result['result'] = false;
                $result['error'] = $error;
            }
        }
        return $result;
    }

    private function isAmountValid($transaction_data, &$error)
    {
        if (self::$invoice && isset($transaction_data['amount'])) {
            $total = floatval(str_replace(',', '.', $transaction_data['amount']));

            if ($transaction_data['currency_id'] != self::$invoice['currency_id']) {
                $error = 'Invalid invoice amount';
                return false;
            }
            $invalid = !empty($total)
                && !empty(self::$invoice['amount'])
                && (abs($total - self::$invoice['amount']) > 0.01);
            if ($invalid) {
                $error = sprintf(
                    'Invalid order amount: %0.2f expected, %0.2f received (%s)',
                    self::$invoice['amount'],
                    $total,
                    $transaction_data['currency_id']
                );
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     *
     * @param string|array $order
     * @param waPayment
     * @return waOrder
     */
    public static function getOrderData($order, $payment_plugin = null)
    {
        if (waConfig::get('is_template')) {
            return;
        }
        $order['merchant_id'] = $order['company_id'];
        $order['tax'] = $order['tax_amount'];
        $order['discount'] = $order['discount_amount'];
        $order['description'] = sprintf_wp('Payment for invoice #%s issued on %s.', $order['number'], wa_date('date', $order['create_datetime']));
        $order['items'] = crmHelper::workupInvoiceItems($order);
        $order['params'] = (array)$order['params'];

        return waOrder::factory($order);
    }
}
