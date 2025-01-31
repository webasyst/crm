<?php

/**
 * Single deal details page.
 */
class crmDealIdAction extends crmBackendViewAction
{
    public function execute()
    {
        $deal = $this->getDeal();

        // List of contacts to fetch via collection
        $contact_ids = array($deal['contact_id'] => 1, $deal['user_contact_id'] => 1);

        // split into 2 arrays by role
        $participant_users = array();
        $participant_contacts = array();
        foreach ($deal['participants'] as $participant) {
            if ($participant['role_id'] === crmDealParticipantsModel::ROLE_USER) {
                $participant_users[$participant['contact_id']] = $participant;
            } elseif ($participant['role_id'] === crmDealParticipantsModel::ROLE_CLIENT) {
                $participant_contacts[$participant['contact_id']] = $participant;
            }
            $contact_ids[$participant['contact_id']] = 1;
        }

        $deal_access_level = $this->getCrmRights()->deal($deal);
        
        if ($deal_access_level <= crmRightConfig::RIGHT_DEAL_NONE) {
            $this->accessDenied();
        }

        $can_edit_deal = $deal_access_level > crmRightConfig::RIGHT_DEAL_VIEW;

        $deal_contact = null;
        try {
            if ($deal['contact_id']) {
                $deal_contact = $this->newContact($deal['contact_id']);
                $deal_contact->getName();
            }
        } catch (waException $e) {
            $deal_contact = null;
        }
        if ($deal_contact && $deal_contact['company_contact_id']) {
            $contact_ids[$deal_contact['company_contact_id']] = 1;
        }

        // Invoices
        $im = new crmInvoiceModel();
        $invoices = $im->select('*')->where('deal_id='.intval($deal['id']))->fetchAll('id');
        foreach ($invoices as $i) {
            $contact_ids[$i['company_id']] = 1;
            $contact_ids[$i['creator_contact_id']] = 1;
        }

        // Attachments
        $dfm = new crmFileModel();
        $attachments = $dfm->select('*')->where('contact_id=-'.intval($deal['id']))->fetchAll('id');
        foreach ($attachments as $a) {
            $contact_ids[$a['creator_contact_id']] = 1;
        }

        // Fetch all contacts via collection, assigning labels to them
        $contacts = array();
        $deal_users = array();
        $deal_contacts = array();
        if ($contact_ids) {
            $collection = new crmContactsCollection(array_keys($contact_ids), array(
                'check_rights' => true,
            ));
            foreach ($collection->getContacts('*,email.*', 0, count($contact_ids)) as $id => $c) {
                $c = $this->newContact($c);
                $contacts[$id] = $c;
                if (!empty($participant_users[$id])) {
                    $deal_users[$id] = $c;
                    $deal_users[$id]['label'] = $participant_users[$id]['label'];
                }
                if (!empty($participant_contacts[$id])) {
                    $deal_contacts[$id] = $c;
                    $deal_contacts[$id]['label'] = $participant_contacts[$id]['label'];
                }
            }

            unset($deal_contacts[$deal['contact_id']]);
            unset($deal_users[$deal['user_contact_id']]);
        }

        // Contact tags
        $ctm = new crmContactTagsModel();
        $contact_tags = $ctm->select('*')->where("contact_id IN('".join("','", $ctm->escape(array_keys($contacts)))."')")->fetchAll();
        $tm = new crmTagModel();
        $all_tags = $tm->getAll('id');
        $tags = array();
        foreach ($contacts as $id => &$c) {
            foreach ($contact_tags as $ct) {
                if ($ct['contact_id'] == $id) { //  && !empty($tags[$ct['tag_id']])
                    $tags[$id][$ct['tag_id']] = $all_tags[$ct['tag_id']]['name'];
                }
            }
        }
        unset($c);

        $deal['contacts'] = array(
            'contact' => isset($contacts[$deal['contact_id']]) ? $contacts[$deal['contact_id']] : null,
            'user'    => isset($contacts[$deal['user_contact_id']]) ? $contacts[$deal['user_contact_id']] : null,
        );

        // Funnel and stages
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $funnel = $fsm->withStages(array($deal['funnel_id'] => $fm->getById($deal['funnel_id'])));
        $funnel = reset($funnel);

        // Update list of recently viewed deals
        $rm = new crmRecentModel();
        $rm->update($deal['id'] * -1);

        if ($deal['lost_id'] && $deal['status_id'] != 'OPEN' && !$deal['lost_text']) {
            $dlm = new crmDealLostModel();
            $deal['lost_text'] = $dlm->select('name')->where('id='.$deal['lost_id'])->fetchField('name');
        }

        $cm = new crmCurrencyModel();
        $currencies = $cm->getAll('code');
        $currency_info = waCurrency::getInfo($deal['currency_id']);
        if (isset($currency_info['precision'])) {
            $deal['amount'] = round((float) $deal['amount'], $currency_info['precision']);
        }

        $can_delete = $deal_access_level === crmRightConfig::RIGHT_DEAL_ALL;

        /**
         * @var shopOrder $_order
         */
        $_order = crmShop::getShopOrderByDeal($deal);

        //
        $has_access_to_shop = crmShop::hasAccess();

        // create contact allowed when
        // 1) can edit deal
        // 2) there is NOT attached shop order yet
        // 3) has deal contact
        // 4) has proper rights in shop
        $can_create_order = $can_edit_deal && $deal_contact && !$_order && crmShop::canCreateOrder();

        // edit contact allowed when
        // 1) can edit deal
        // 2) there IS attached shop order already
        // 3) has proper rights in shop
        $can_edit_order = $can_edit_deal && $_order && crmShop::canEditOrder($_order);

        $rights = new crmRights();
        $funnel_rights_value = $rights->funnel($funnel);

        // shipping info vars

        $order_shipping_info = $this->getOrderShippingInfo($_order);

        // formatted counters by deals and orders for client participants

        $counters = crmDeal::getDealPageContactCounters(
            $deal['contacts']['contact'],
            $deal_contacts,
            !empty($_order)
        );

        $order_data_array = array();
        if ($_order) {
            $order_data_array = $_order->dataArray();
            $order_items = $this->extendOrderItems($order_data_array);
            $order_data_array['contact'] = $_order->contact_essentials;
            $order_data_array['coupon'] = $_order['coupon'];
            $order_data_array['state'] = $_order['state'];
            $order_data_array['items'] = $order_items;
        }

        $can_manage_responsible = $deal['user_contact_id'] == wa()->getUser()->getId() || $funnel_rights_value > 2 ||
            (!$deal['contacts']['user'] && $funnel_rights_value > 0);

        $this->view->assign(array(
            'deal'                   => $deal,
            'can_edit_deal'          => $can_edit_deal,

            'contacts'               => $contacts,
            'stages'                 => $funnel['stages'],
            'invoices'               => $invoices,
            'attachments'            => $attachments,
            'deal_users'             => $deal_users,
            'deal_contacts'          => $deal_contacts,

            'funnel'                 => $funnel,
            'has_access_to_funnel'   => $funnel_rights_value > crmRightConfig::RIGHT_FUNNEL_NONE,

            'log_html'               => $this->getLogHtml(),
            'currencies'             => $currencies,
            'can_manage_invoices'    => wa()->getUser()->getRights('crm', 'manage_invoices'),
            'can_delete'             => $can_delete,

            'order'                  => $order_data_array,
            'can_create_order'       => $can_create_order,
            'can_edit_order'         => $can_edit_order,
            'has_access_to_shop'     => $has_access_to_shop,
            'order_shipping_info'    => $order_shipping_info,
            'shop_in_welcome_stage'  => crmShop::inWelcomeStage(),

            'magic_source_email'     => crmHelper::getMagicSourceEmail($deal),
            'tags'                   => $this->getTags($deal),
            'contact_tags'           => $tags,
            'is_init_call'           => $rights->isInitCall(),
            'can_manage_responsible' => $can_manage_responsible,
            'is_sms_configured'      => $this->isSMSConfigured(),

            'counters'               => $counters
        ));

    }

    protected function getLogHtml()
    {
        $action = new crmLogAction();
        return $action->display(false);
    }

    protected function getDeal()
    {
        $id = (int)$this->getParameter('id');
        if ($id <= 0) {
            $this->notFound();
        }
        $deal = $this->getDealModel()->getDeal($id, true, true);
        if (!$deal) {
            $this->notFound();
        }
        return $deal;
    }

    protected function getTags($deal)
    {
        $tm = new crmTagModel();
        return $tm->getByContact(-$deal['id'], false);
    }


    /**
     *
     * IMPORTANT: isolated code that duplicate piece of code from shopOrderAction
     *
     * @param shopOrder|null $_order
     * @return array()
     * @throws waException
     */
    protected function getOrderShippingInfo($_order)
    {
        if (!$_order) {
            return array();
        }

        $params = $_order->params;

        list($customer_delivery_date, $customer_delivery_time) = shopHelper::getOrderCustomerDeliveryTime($params);
        list($shipping_date, $shipping_time_start, $shipping_time_end) = shopHelper::getOrderShippingInterval($params);

        $order_data_array = $_order->dataArray();
        $order_items = $this->extendOrderItems($order_data_array);
        $order_data_array['contact'] = $_order->contact_essentials;
        $order_data_array['coupon'] = $_order['coupon'];
        $order_data_array['state'] = $_order['state'];
        $order_data_array['items'] = $order_items;

        $map = $this->getShippingAddressMap($_order);

        // tracking block of html has ajax url without app_url in elder versions of shop
        // trying work around this
        $tacking_block_html = $_order->getTracking('backend');
        $app_url = wa()->getAppUrl('shop');
        $tracking_url_prefix_with_app    = "{$app_url}?module=order&action=tracking&order_id=";
        $tracking_url_prefix_without_app = "?module=order&action=tracking&order_id=";
        if (strpos($tacking_block_html, $tracking_url_prefix_with_app) === false) {
            $tacking_block_html = str_replace($tracking_url_prefix_without_app, $tracking_url_prefix_with_app, $tacking_block_html);
        }

        return array(
            'tracking'                   => $tacking_block_html,
            'shipping_address_html'      => $_order->shipping_address_html,
            'params'                     => $params,
            'customer_delivery_date'     => $customer_delivery_date,
            'customer_delivery_time'     => $customer_delivery_time,
            'customer_delivery_date_str' => ifset($params['shipping_params_desired_delivery.date_str']),
            'shipping_date'              => $shipping_date,
            'shipping_time_start'        => $shipping_time_start,
            'shipping_time_end'          => $shipping_time_end,
            'order'                      => $order_data_array,
            'courier'                    => $_order->courier,
            'shop_app_url'               => wa()->getAppUrl('shop'),
            'shipping_custom_fields'     => $_order->shipping_custom_fields,
            'map'                        => $map,
            'map_settings'               => $this->getMapSettings(),
            'shipping_address_text'      => $_order->shipping_address_text,
            'can_show_package_info'      => crmShop::canShowShippingPackageInfo(),
        );
    }

    /**
     * @param shopOrder|null $_order
     * @return string
     */
    protected function getShippingAddressMap($_order) {
        if (!$_order || !$_order->shipping_address_html) {
            return '';
        }
        return $_order->map;
    }

    /**
     * @return array
     * @throws waException
     */
    protected function getMapSettings()
    {
        try {
            $map = wa()->getMap();
            if ($map->getId() === 'google') {
                return array(
                    'type' => $map->getId(),
                    'key' => $map->getSettings('key'),
                    'locale' => wa()->getLocale()
                );
            } elseif ($map->getId() === 'yandex') {
                return array(
                    'type' => $map->getId(),
                    'key' => $map->getSettings('apikey'),
                    'locale' => wa()->getLocale()
                );
            }
        } catch (Exception $e) {

        }

        return array(
            'type' => '',
            'key' => '',
            'locale' => ''
        );
    }

    /**
     * IMPORTANT: duplicated from shopOrderAction
     * @param $order
     * @return mixed
     * @throws waException
     */
    private function extendOrderItems($order)
    {
        $sku_ids = array();
        $stock_ids = array();
        $product_ids = array();
        $service_ids = array();
        $order_items = $order['items'];

        foreach ($order_items as $item) {
            //get product_id and service_id to clear from deleted items
            if ($item['type'] == 'product') {
                $product_ids[] = $item['product_id'];
            } else {
                $service_ids[] = $item['service_id'];
            }

            if ($item['stock_id']) {
                $stock_ids[] = $item['stock_id'];
            }
            if ($item['sku_id']) {
                $sku_ids[] = $item['sku_id'];
            }
        }
        $sku_ids = array_unique($sku_ids);
        $stock_ids = array_unique($stock_ids);

        // extend items by stocks
        $stocks = $this->getStocks($stock_ids);
        foreach ($order_items as &$item) {
            if (!empty($stocks[$item['stock_id']])) {
                $item['stock'] = $stocks[$item['stock_id']];
            }
        }
        unset($item);

        $skus = $this->getSkus($sku_ids);

        $sku_stocks = $this->getSkuStocks($sku_ids);

        //get existing services/products
        $product_ids = $this->getProducts($product_ids);
        $service_ids = $this->getServices($service_ids);

        foreach ($order_items as &$item) {

            //check whether the item was deleted
            if ($item['type'] == 'product' && empty($product_ids[$item['product_id']])) {
                $item['deleted'] = 1;
            } elseif ($item['type'] == 'service' &&
                (empty($service_ids[$item['service_id']]) || empty($service_ids[$item['service_id']]['variants'][$item['service_variant_id']]))) {
                //check service and service variants
                $item['deleted'] = 1;
            }

            // product and existing sku
            if ($item['type'] === 'product' && isset($skus[$item['sku_id']])) {
                $s = $skus[$item['sku_id']];

                // for that counts that lower than low_count-thresholds show icon

                if (isset($item['stock'])) {
                    if (isset($sku_stocks[$s['id']][$item['stock']['id']])) {
                        $count = $sku_stocks[$s['id']][$item['stock']['id']]['count'];
                        if ($count <= $item['stock']['low_count']) {
                            $item['stock_icon'] = shopHelper::getStockCountIcon($count, $item['stock']['id'], true);
                        }
                    }
                } elseif ($s['count'] !== null && $s['count'] <= shopStockModel::LOW_DEFAULT) {
                    $item['stock_icon'] = shopHelper::getStockCountIcon($s['count'], null, true);
                }

            }


            $current_product_name = ifset($product_ids, $item['product_id'], 'name', null);

            if (!empty($skus[$item['sku_id']]['name'])) {
                $current_product_name .= ' ('.$skus[$item['sku_id']]['name'].')';
            }

            $item['current_product_name'] = $current_product_name;
        }
        unset($item);

        return $order_items;
    }

    /**
     * Get existing services and service variants
     *
     * IMPORTANT: duplicated from shopOrderAction
     *
     * @param array $service_ids
     * @return array|null
     * @throws waException
     */
    public function getServices($service_ids = array())
    {
        if (!$service_ids) {
            return array();
        }
        $ssm = new shopServiceModel();
        $ssvm = new shopServiceVariantsModel();

        $service = $ssm->getByField('id', $service_ids, 'id');
        $service_variants = $ssvm->getByField('service_id', $service_ids, true);

        if ($service_variants) {
            foreach ($service_variants as $variants) {
                $service[$variants['service_id']]['variants'][$variants['id']] = $variants;
            }
        }

        return $service;
    }

    /**
     * Get existing products
     *
     * IMPORTANT: duplicated from shopOrderAction
     *
     * @param array $product_ids
     * @return array|null
     * @throws waException
     */
    public function getProducts($product_ids = array())
    {
        if (!$product_ids) {
            return array();
        }
        $spm = new shopProductModel();
        return $spm->getByField('id', $product_ids, 'id');
    }

    /**
     * IMPORTANT: duplicated from shopOrderAction
     *
     * @param $sku_ids
     * @return array|null
     * @throws waException
     */
    public function getSkus($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $model = new shopProductSkusModel();
        return $model->getByField('id', $sku_ids, 'id');
    }

    /**
     * IMPORTANT: duplicated from shopOrderAction
     *
     * @param $stock_ids
     * @return array|null
     */
    public function getStocks($stock_ids)
    {
        if (!$stock_ids) {
            return array();
        }
        $model = new shopStockModel();
        return $model->getById($stock_ids);
    }

    /**
     * IMPORTANT: duplicated from shopOrderAction
     *
     * @param $sku_ids
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function getSkuStocks($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $model = new shopProductStocksModel();
        return $model->getBySkuId($sku_ids);
    }

    /**
     * Get contact object (even if contact not exists)
     * BUT please don't save it
     *
     * @param int|array $contact ID or data
     * @return waContact
     * @throws waException
     */
    protected function newContact($contact)
    {
        if ($contact instanceof waContact) {
            return $contact;
        }

        $contact_id = 0;
        if (wa_is_int($contact) && $contact > 0) {
            $contact_id = $contact;
        } elseif (isset($contact['id']) && wa_is_int($contact['id']) && $contact['id'] > 0) {
            $contact_id = $contact['id'];
        }

        $wa_contact = new waContact($contact);
        if (!$wa_contact->exists()) {
            $wa_contact = new waContact();
            $wa_contact['id'] = $contact_id;
            $wa_contact['name'] = sprintf_wp("Contact with ID %s doesn't exist", $contact_id);
        }
        return $wa_contact;
    }

    protected function renderTemplate($assign, $template)
    {
        $view = wa()->getView();
        $vars = $view->getVars();
        $view->assign($assign);
        $html = $view->fetch($template);
        $view->clearAllAssign();
        $view->assign($vars);
        return $html;
    }
}
