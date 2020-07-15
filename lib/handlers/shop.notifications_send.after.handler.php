<?php

class crmShopNotifications_sendAfterHandler extends waEventHandler
{
    public function execute(&$params, $event_name = null)
    {
        $send_results = array();
        foreach ($params['send_results'] as $send_result) {
            if (!empty($send_result['log_id']) && wa_is_int($send_result['log_id'])) {
                $send_results[] = $send_result;
            }
        }

        $log_ids = waUtils::getFieldValues($send_results, 'log_id');

        if (!$log_ids) {
            return;
        }

        if (empty($params['data']['order']['id'])) {
            return;
        }

        $order_id = $params['data']['order']['id'];
        $deal = $this->getDealByOrderId($order_id);

        if (!$deal) {
            return;
        }

        $solm = new shopOrderLogModel();
        $log_items = $solm->getById($log_ids);

        foreach ($send_results as $send_result) {
            $log_id = $send_result['log_id'];
            $transport = !empty($send_result['transport']) ? $send_result['transport'] : null;

            if (!empty($log_items[$log_id])) {
                $log_item = $log_items[$log_id];
                $lm = new crmLogModel();
                $action = 'send_notification';
                if ($transport) {
                    $action .= '_' . $transport;
                }
                $lm->add(array(
                    'action' => $action,
                    'contact_id' => $deal['id'] * -1,
                    'object_id' => $log_item['id'],
                    'object_type' => crmLogModel::OBJECT_TYPE_ORDER_LOG,
                    'actor_contact_id' => $log_item['contact_id'] > 0 ? $log_item['contact_id'] : 0
                ));
            }

        }
    }

    protected function getDealByOrderId($order_id)
    {
        $dm = new crmDealModel();
        $deal = $dm->getByField(array('external_id' => 'shop:'.$order_id));
        return $deal;
    }
}
