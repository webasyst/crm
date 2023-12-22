<?php

class crmShopOrder_actionHandler extends waEventHandler
{
    /**
     * @var array
     */
    protected $order;

    /**
     * @var array
     */
    protected $deal;

    /**
     * @var array
     */
    protected $stage;

    /**
     * @var waAppSettingsModel
     */
    protected static $app_settings_model;

    /**
     * @var shopOrderModel
     */
    protected static $shop_order_model;

    /**
     * @var crmDealModel
     */
    protected static $deal_model;

    /**
     * @var crmFunnelStageModel
     */
    protected static $funnel_stage_model;

    /**
     * @var crmCurrencyModel
     */
    protected static $currency_model;

    /**
     * @var crmLogModel
     */
    protected static $log_model;

    /**
     * @var shopOrderLogModel
     */
    protected static $shop_order_log_model;

    /**
     * @param array $params
     * @param string $event_name
     * @return void
     */
    public function execute(&$params, $event_name = null)
    {
        if (empty($params['order_id']) || !crmShop::isIntegrationSupported(crmShop::INTEGRATION_ANY)) {
            return;
        }

        if (preg_match('/^[^\.]+\.(.+)$/', $event_name, $m)) {
            $shop_action = $m[1];
        }

        if (empty($shop_action)) {
            return;
        }

        try {
            $this->setModels();
            if (!$this->getOrder($params['order_id'])) {
                return;
            }

            if ($shop_action === 'create' || $shop_action === 'edit') {
                // deal to which attach created order (by design posted from embedded order form in deal page)
                $crm_deal = wa()->getRequest()->post('crm_deal');

                // input param type is ok
                $input_post_param_is_ok = $crm_deal && is_array($crm_deal) && isset($crm_deal['id']) && $crm_deal['id'] > 0;

                // find deal with current order attached to
                $deal = $this->getDeal();

                // could perform 'create case'
                $create_case = $shop_action === 'create' && $input_post_param_is_ok && !$deal;

                // could perform 'edit case'
                $edit_case = $shop_action === 'edit' && $deal;

                if ($create_case) {
                    // update deal and attach order
                    $this->updateDeal($crm_deal['id']);
                } elseif ($edit_case) {
                    // update deal (order already attached)
                    $this->updateDeal($deal['id']);
                }
            }

            // find deal with current order attached to
            $deal = $this->getDeal(true);

            // no deal with attached order
            if (!$deal) {
                $this->createDeal($shop_action);    // create deal and attach order to it
            } elseif ($shop_action == 'restore') {
                $this->orderRestore();              // reopen deal
            }

            // log deal stage transaction
            $this->logDealStage($shop_action);

            // log shop order action
            if (isset($params['id'])) {
                $this->logOrderAction($params['id'], $shop_action);
            }
        } catch (waException $e) {
        }
    }

    /**
     * @param string $shop_action
     * @return bool
     * @throws waException
     */
    protected function logDealStage($shop_action)
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }
        $deal = $this->getDeal();
        if (!$deal) {
            return false;
        }
        $lm = new crmLogModel();
        $log = $lm->getByField(array('action' => 'deal_order_'.$shop_action, 'contact_id' => -$deal['id']));
        if ($log) {
            return false; // prevent duplicates and recursion
        }
        $stage = $this->getStage($shop_action, $deal);
        if (!$stage) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $upd = array(
            'update_datetime' => $now,
        );
        if (is_array($stage)) {
            $upd['status_id'] = crmDealModel::STATUS_OPEN;
            $upd['stage_id'] = $stage['id'];
            self::$deal_model->updateById($this->deal['id'], $upd);
            self::$log_model->log(
                'deal_step',
                $this->deal['id'] * -1,
                $this->deal['id'],
                ifset($this->deal['stage']['name']),
                $this->stage['name'],
                null,
                ['stage_id_before' => $this->deal['stage']['id'], 'stage_id_after' => $stage['id']]
            );
        } else {
            // WON, LOST
            $upd['status_id'] = strtoupper($stage);
            $upd['closed_datetime'] = $now;

            if ($upd['status_id'] === crmDealModel::STATUS_LOST) {
                // Extra info in 'crm_change_workflow_data' subarray of POST
                // This data injected in JS when show SS dialog, otherwise we could loose this data
                // For LOST for example here could be lost_id, lost_text
                $crm_change_workflow_data = wa()->getRequest()->post('crm_change_workflow_data');
                if (!empty($crm_change_workflow_data) && is_array($crm_change_workflow_data)) {
                    if (isset($crm_change_workflow_data['lost_id']) && wa_is_int($crm_change_workflow_data['lost_id']) && $crm_change_workflow_data['lost_id'] > 0) {
                        $upd['lost_id'] = $crm_change_workflow_data['lost_id'];
                    }
                    if (isset($crm_change_workflow_data['lost_text']) && is_scalar($crm_change_workflow_data['lost_text']) && strlen(strval($crm_change_workflow_data['lost_text'])) > 0) {
                        $upd['lost_text'] = $crm_change_workflow_data['lost_text'];
                    }
                }
            }

            self::$deal_model->updateById($this->deal['id'], $upd);

            self::$log_model->log('deal_'.strtolower($stage), $this->deal['id'] * -1);
        }
        return true;
    }

    /**
     * @param string $shop_action
     * @return void
     */
    protected function createDeal($shop_action)
    {
        $source = $this->getSource();
        if (!$this->order || !$source || $source->getParam('create_deal_trigger') != 'order_'.$shop_action) {
            return;
        }

        $currencies = self::$currency_model->getAll('code');

        $amount = $currency_id = null;
        if (!empty($this->order['currency']) && !empty($currencies[$this->order['currency']])) {
            $currency_id = $this->order['currency'];
            $amount = $this->order['total'];
        }
        $deal = array(
            'name'               => _w('Order').' '.shopHelper::encodeOrderId($this->order['id']),
            'amount'             => $amount,
            'currency_id'        => $currency_id,
            'contact_id'         => $this->order['contact_id'],
            'creator_contact_id' => $this->order['contact_id'],
            'external_id'        => 'shop:'.$this->order['id'],
        );
        $deal['id'] = $source->createDeal($deal);
        $this->deal = self::$deal_model->getById($deal['id']);
    }

    /**
     * Update deal: amount and currency
     * And also update external_id, so if order wasn't attached it would be after update
     * @param $id
     */
    protected function updateDeal($id)
    {
        $currencies = self::$currency_model->getAll('code');
        $amount = $currency_id = null;
        if (!empty($this->order['currency']) && !empty($currencies[$this->order['currency']])) {
            $currency_id = $this->order['currency'];
            $amount = $this->order['total'];
        }
        $update = array(
            'amount'      => $amount,
            'currency_id' => $currency_id,
            'external_id' => 'shop:'.$this->order['id'],
        );
        self::$deal_model->updateById($id, $update);
    }

    /**
     * @return void
     * @throws waException
     */
    protected function orderRestore()
    {
        $order = $this->getOrder();
        $deal = $this->getDeal();
        if (!$order || !$deal || $deal['status_id'] == 'OPEN') {
            return;
        }
        self::$deal_model->updateById(
            $deal['id'],
            array(
                'status_id'       => 'OPEN',
                'update_datetime' => date('Y-m-d H:i:s'),
                'closed_datetime' => null,
            )
        );
        self::$log_model->log('deal_reopen', $deal['id'] * -1, $deal['id']);
    }

    /**
     * @param $order_id
     * @return array
     */
    protected function getOrder($order_id = null)
    {
        if (!$this->order && $order_id) {
            $this->order = self::$shop_order_model->getOrder($order_id);
        }
        return $this->order;
    }

    /**
     * Get current deal by current order
     * @param bool $force - if TRUE - get from DB not from runtime
     * @return array
     * @throws waException
     */
    protected function getDeal($force = false)
    {
        if ($this->deal && !$force) {
            return $this->deal;
        }
        $condition = array('external_id' => 'shop:'.$this->order['id']);
        $this->deal = self::$deal_model->getByField($condition);
        if ($this->deal) {
            $this->deal['stage'] = self::$funnel_stage_model->getById($this->deal['stage_id']);
        }
        return $this->deal;
    }

    /**
     * @return crmFormSource|null
     */
    protected function getSource()
    {
        $storefront = isset($this->order['params']['storefront']) ? $this->order['params']['storefront'] : null;
        $source = crmShopSource::factoryByStorefront($storefront);
        if ($source->getId() <= 0) {
            return null;
        }
        return $source;
    }

    /**
     * @param string $shop_action
     * @param array $deal
     * @return array|string|null
     */
    protected function getStage($shop_action, $deal)
    {
        $settings = self::$app_settings_model->select('name, value')->where(
            "app_id = 'crm' AND name LIKE 'shop:%'"
        )->fetchAll('name', true);
        $stage_id = ifset($settings['shop:'.$shop_action.'_'.$deal['funnel_id']]);
        if (!$stage_id) {
            return null;
        }
        if (!is_numeric($stage_id)) { // 'won' or 'lost'
            return strtoupper($stage_id);
        }
        $stages = self::$funnel_stage_model->getStagesByFunnel($deal['funnel_id'], false);
        if (empty($stages[$stage_id])) {
            return null;
        }
        if (array_search($deal['stage_id'], array_keys($stages)) >= array_search($stage_id, array_keys($stages))) {
            return null;
        }
        $this->stage = $stages[$stage_id];
        return $this->stage;
    }

    /**
     * @param int $log_id - shop_order_log.id
     * @param string $action
     * @return bool
     * @throws waException
     */
    protected function logOrderAction($log_id, $action)
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }
        $deal = $this->getDeal();
        if ($deal) {
            $contact_id = $this->deal['id'] * -1;
        } else {
            $contact_id = ifempty($order, 'contact_id', 0);
        }
        $log_item = self::$shop_order_log_model->getById($log_id);
        self::$log_model->add(array(
            'action'           => $action,
            'contact_id'       => $contact_id,
            'object_id'        => $log_id,
            'object_type'      => crmLogModel::OBJECT_TYPE_ORDER_LOG,
            'actor_contact_id' => $log_item['contact_id'] > 0 ? $log_item['contact_id'] : 0
        ));
        return true;
    }

    /**
     * @return void
     * @throws waDbException
     */
    protected function setModels()
    {
        self::$app_settings_model = self::$app_settings_model ? self::$app_settings_model : new waAppSettingsModel();
        self::$deal_model = self::$deal_model ? self::$deal_model : new crmDealModel();
        self::$funnel_stage_model = self::$funnel_stage_model ? self::$funnel_stage_model : new crmFunnelStageModel();
        self::$currency_model = self::$currency_model ? self::$currency_model : new crmCurrencyModel();
        self::$log_model = self::$log_model ? self::$log_model : new crmLogModel();

        wa('shop');
        self::$shop_order_model = self::$shop_order_model ? self::$shop_order_model : new shopOrderModel();
        self::$shop_order_log_model = self::$shop_order_log_model ? self::$shop_order_log_model : new shopOrderLogModel();

    }
}
