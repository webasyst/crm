<?php

class crmDealShopactionMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $order_id = (int) $this->get('order_id', true);
        $action_id = trim($this->get('action_id', true));

        if (!crmShop::appExists()) {
            throw new waAPIException('error', 'App Shop-Script not exists', 400);
        }

        wa('shop', 1);
        if ($order_id < 1 || !$order = (new shopOrderModel())->getById($order_id)) {
            throw new waAPIException('not_found', 'Order not found', 404);
        }

        $workflow = new shopWorkflow();
        if (!$action = $workflow->getActionById($action_id)) {
            throw new waAPIException('unknown_parameter', 'Unknown action_id', 400);
        }
        try {
            $this->response = null;
            $action_result = $action->run($order_id);
            $deal = $this->getDealModel()->getByField('external_id', "shop:$order_id");
            if ($deal) {
                $api_deal_info = new crmDealInfoMethod();
                $api_deal_info->execute($deal['id']);
                $this->response = $api_deal_info->response;
            }
        } catch (Exception $e) {
            throw new waAPIException('error', $e->getMessage(), 400);
        }
        wa('crm', 1);
    }
}
