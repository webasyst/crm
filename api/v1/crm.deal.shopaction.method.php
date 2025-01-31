<?php

class crmDealShopactionMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $order_id = (int) $this->get('order_id', true);
        $action_id = trim($this->get('action_id', true));

        if (!crmShop::appExists()) {
            throw new waAPIException('error', _w('Shop-Script app is not available.'), 400);
        }

        $deal = $this->getDealModel()->getByField('external_id', "shop:$order_id");
        if (empty($deal)) {
            throw new waAPIException('not_found', _w('Deal not found.'), 404);
        }
        if (!$this->getCrmRights()->deal($deal)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        wa('shop', 1);
        if ($order_id < 1 || !$order = (new shopOrderModel())->getById($order_id)) {
            throw new waAPIException('not_found', _w('Order not found.'), 404);
        }

        $workflow = new shopWorkflow();
        if (!$action = $workflow->getActionById($action_id)) {
            throw new waAPIException('unknown_parameter', sprintf(_wd('crm', 'Unknown “%s” value.'), 'action_id'), 400);
        }

        $state = $workflow->getStateById($order['state_id']);
        $available_actions = $state->getActions($order);
        if (empty($available_actions[$action_id])) {
            throw new waAPIException('not_available', sprintf_wp('Action “%s” is not available for this order.', $action->getName()), 409);
        }

        try {
            $action_result = $action->run($order_id);
        } catch (Exception $e) {
            throw new waAPIException('error', $e->getMessage(), 400);
        }
        wa('crm', 1);
        $api_deal_info = new crmDealInfoMethod();
        $api_deal_info->execute($deal['id']);
        $this->response = $api_deal_info->response;
    }
}
