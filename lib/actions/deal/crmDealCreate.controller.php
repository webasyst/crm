<?php
/**
 * Create a deal from crmShopBackend_orderHandler.
 */
class crmDealCreateController extends waJsonController
{
    public function execute()
    {
        if (!crmConfig::isShopSupported()) {
            return;
        }

        $data = waRequest::post('data', null, waRequest::TYPE_ARRAY_TRIM);
        $order_id = waRequest::post('order_id', null, waRequest::TYPE_INT);

        wa('shop');
        $som = new shopOrderModel();
        $order = $som->getOrder($order_id);
        if (!$order) {
            throw new waException('Order not found');
        }
        $dm = new crmDealModel();
        $cm = new crmCurrencyModel();
        $currencies = $cm->getAll('code');
        $amount = $currency_id = $currency_rate = null;
        if (!empty($order['currency']) && !empty($currencies[$order['currency']])) {
            $currency_id = $order['currency'];
            $amount = $order['total'];
            $currency = $currencies[$order['currency']];
            $currency_rate = ifset($currency['rate']);
        }
        $now = date('Y-m-d H:i:s');

        $deal = null;
        if (!empty($data['deal_id']) && is_numeric($data['deal_id'])) {
            $deal = $dm->getById($data['deal_id']);
        }

        if ($deal && !$deal['external_id'] && $deal['status_id'] == 'OPEN') {
            $upd = array(
                'external_id' => 'shop:'.$order['id'],
                'update_datetime'    => $now,
            );
            if (!$deal['amount'] && $amount) {
                $upd['amount'] = $amount;
                $upd['currency_id'] = $currency_id;
                $upd['currency_rate'] = $currency_rate;
            }
            $dm->updateById($deal['id'], $upd);

            $lm = new crmLogModel();

            $lm->log('deal_order_link', null, $deal['id'], null, $order['id'], wa()->getUser()->getId());

        } else {

            $deal = array(
                'name'               => _w('Order').' '.shopHelper::encodeOrderId($order['id']),
                'amount'             => $amount,
                'currency_id'        => $currency_id,
                'currency_rate'      => $currency_rate,
                'contact_id'         => $order['contact_id'],
                'creator_contact_id' => wa()->getUser()->getId(),
                'create_datetime'    => $now,
                'update_datetime'    => $now,
                'funnel_id'          => ifset($data['funnel_id']),
                'stage_id'           => ifset($data['stage_id']),
                'user_contact_id'    => ifset($data['user_contact_id']),
                'external_id'        => 'shop:'.$order['id'],
            );
            $deal['id'] = $dm->add($deal);
        }

        $this->response = array(
            'id' => $deal['id'],
        );
    }
}
