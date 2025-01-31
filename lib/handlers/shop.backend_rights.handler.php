<?php

class crmShopBackend_rightsHandler extends waEventHandler
{
    public function execute(&$params)
    {
        if (!crmConfig::isShopSupported()) {
            return; // ignore old shop app
        }

        if (wa()->getUser()->getRights('crm', 'backend') <= 0) {
            return; // do not modify rights unless user has access to CRM
        }
        if (wa()->getUser()->getRights('shop', 'backend') > 1) {
            return; // do not modify rights if user has full access to Shop app
        }

        $rights_to_orders = wa()->getUser()->getRights('shop', 'orders');

        $module = $params['module'];
        $action = $params['action'];

        if (substr($module, 0, 5) == 'order') {
            if (substr($module, 0, 6) == 'orders' && $action != 'getProduct') {
                return; // do not affect list of orders
            }
            // different actions use different parameter for order id
            if ($action == 'settle') {
                $order_id = waRequest::post('id');
            } else if ($action == 'total') {
                $order_id = waRequest::post('order_id');
            } else if (in_array($action, array('printform', 'sendprintform', 'tracking', 'getProduct'))) {
                $order_id = waRequest::get('order_id');
            } else {
                $order_id = waRequest::get('id');
            }
        } else if ($module == 'workflow') {
            $order_id = waRequest::post('id');
        }

        if (empty($order_id)) {

            // when user already has access to Orders, no need to do anything here
            if (wa()->getUser()->getRights('shop', 'orders') > 0) {
                return;
            }

            if ($module == 'backend' && ($action == 'orders' || $action === null)) {
                // Allow to load Order tab layout via direct link
                // Remember to append styles, modifying shop layout.
                // (See webasyst.backend_header handler)
                if (!$rights_to_orders) {
                    waRequest::setParam('crm_should_fix_shop_layout', true);
                }
                return array(
                    'orders' => 1
                );
            }

            return; // ignore all modules except Orders tab in backend
        }

        $order_id = (int)$order_id;     // sometimes it is with order_id is string with spaces around

        $deal = null;
        $dm = new crmDealModel();

        if ($order_id > 0) {
            $deal = $dm->getByField('external_id', 'shop:' . $order_id);
        }

        if (!$deal) {
            return; // do not modify access rights unless order has an attached deal
        }

        // When user can edit deal in CRM, allow to see order in Shop.
        // When user can not edit deal in CRM, deny order in Shop.
        $rights = new crmRights();
        $right_deal_view = $rights->deal($deal) > crmRightConfig::RIGHT_DEAL_VIEW ? 1 : 0;
        if ($right_deal_view == 0) {
            echo '<div class="block double-padded"><h2 id="Title">' . _wd('crm', 'The order is not available because of insufficient access rights for deal editing.') . '</h2></div>';
            exit;
        } else {
            if (defined('shopRightConfig::RIGHT_ORDERS_FULL')) {
                return [
                    'orders' => shopRightConfig::RIGHT_ORDERS_FULL,
                ];
            } else {
                return [
                    'orders' => 1,
                ];
            }
        }
    }
}
