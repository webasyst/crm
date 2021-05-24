<?php

class crmShopBackend_customers_listHandler extends waEventHandler
{
    public function execute(&$params)
    {
        if (empty($params['hash'])) {
            return null;
        }

        $button_text = htmlspecialchars(_wd('crm', 'Open in CRM'));

        $hash = $params['hash'];
        $url = wa()->getRootUrl(true).wa()->getConfig()->getBackendUrl()."/crm/contact/search/result/";

        if (strpos($hash, 'search/') === 0) {

            // sanitize
            $hash = trim(trim(str_replace('search/', '', $hash)), '/');

            if (strstr($hash, 'app.total_spent') !== false) {
                $hash = preg_replace("/app\.total_spent(>|<|=|<=|>=)([\d\-\.]+)/", "shop.customers.total_spent$1$2&shop.customers.payed_orders=1", $hash);
            }

            // decoding
            $decode_statements = array(
                'app.orders_total_sum' => 'shop.customers.total_spent',
                'app.payment_method'   => 'shop.placed_orders.payment_method',
                'app.shipment_method'  => 'shop.placed_orders.shipment_method',
                'app.product'          => 'shop.purchased_product.product',
                'app.order_datetime'   => 'shop.purchased_product.period.period',
                'app.'                 => 'shop.customers.'
            );
            foreach ($decode_statements as $from => $to) {
                $hash = str_replace($from, $to, $hash);
            }

            // cause we need customers not just contacts
            if (strstr($hash, 'shop.') === false) {
                $prefix = 'shop.placed_orders.period.period<='.date('Y-m-d');
                $hash .= $hash ? ('&' . $prefix) : $prefix;
            }

            $url .= urlencode($hash);
        } else if (preg_match('/^([a-z_0-9]*)\//', $hash, $match)) {
            $hash = str_replace($match[1] . '/', "shop_customers\/{$match[1]}=", $hash);
            $url .= $hash;
        } else {
            $hash = 'shop_customers\/' . $hash;
            $url .= $hash;
        }

        if (wa()->getUser()->getRights('crm')) {
            return array(
                'top_li' => '<input type="button" onclick="location.href=\'' . $url . '\'" value="' . $button_text . '">',
            );
        }
    }

}
