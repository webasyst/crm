<?php

return array(
    'name' => 'Shop',                                // _w('Shop')
    'joins' => array(
        ':tbl_customer' => array(
            'table' => 'shop_customer'
        ),
        ':tbl_order' => array(
            'table' => 'shop_order'
        )
    ),
    'items' => array(
        'placed_orders' => array(
            'name' => 'Placed orders',        // _w('Placed orders')
            'multi' => true,
            'items' => array(
                'period' => array(
                    'name' => 'Period',           // _w('Period')
                    'items' => array(
                        ':period' => array(
                            'name' => 'select a period',
                            'where' => array(
                                ':between' => "DATE(:tbl_order.create_datetime) >= ':0' AND DATE(:tbl_order.create_datetime) <= ':1'",
                                ':gt' => "DATE(:tbl_order.create_datetime) >= ':?'",
                                ':lt' => "DATE(:tbl_order.create_datetime) <= ':?'",
                            )
                        )
                    )
                ),
                'status' => array(
                    'name'  => 'Current state',      // _w('Current state')
                    'readonly' => true,
                    'items' => array(
                        ':values' => array(
                            'class' => 'crmContactsSearchShopOrderStatesValues'
                        )
                    )
                ),
                'payment_method' => array(
                    'name' => 'Payment method',       // _w('Payment method')
                    'readonly' => true,
                    'items' => array(
                        ':values' => array(
                            'join' => array(
                                'table' => 'shop_order_params',
                                'on' => ':table.order_id = :tbl_order.id'
                            ),
                            'class' => 'crmContactsSearchShopSPMethodsValues',
                            'options' => array(
                                'type' => 'payment'
                            )
                        )
                    )
                ),
                'shipment_method' => array(
                    'name' => 'Shipment method',        // _w('Shipment method')
                    'readonly' => true,
                    'items' => array(
                        ':values' => array(
                            'join' => array(
                                'table' => 'shop_order_params',
                                'on' => ':table.order_id = :tbl_order.id'
                            ),
                            'class' => 'crmContactsSearchShopSPMethodsValues',
                            'options' => array(
                                'type' => 'shipping'
                            )
                        )
                    )
                )
            )
        ),
        'purchased_product' => array(
            'name' => 'Purchased product',        // _w('Purchased product')
            'multi' => true,
            'items' => array(
                'period' => array(
                    'name' => 'Period',           // _w('Period')
                    'items' => array(
                        ':period' => array(
                            'name' => 'select a period',
                            'where' => array(
                                ':between' => "DATE(:tbl_order.create_datetime) >= ':0' AND DATE(:tbl_order.create_datetime) <= ':1'",
                                ':gt' => "DATE(:tbl_order.create_datetime) >= ':?'",
                                ':lt' => "DATE(:tbl_order.create_datetime) <= ':?'",
                            )
                        )
                    )
                ),
                'product' => array(
                    'name' => 'Product',          // _w('Product'),
                    'items' => array(
                        ':values' => array(
                            'autocomplete' => 1,
                            'class' => 'crmContactsSearchShopProductValues'
                        )
                    )
                ),
                'status' => array(
                    'name'  => 'Current state',      // _w('Current state')
                    'readonly' => true,
                    'items' => array(
                        ':values' => array(
                            'class' => 'crmContactsSearchShopOrderStatesValues'
                        )
                    )
                )
            )
        ),
        'customers' => array(
            'name' => 'Customers',
            'multi' => true,
            'items' => array(
                'total_spent' => array(
                    'name' => 'Total spent',                // _w('Total spent')
                    ':class' => 'crmContactsSearchShopTotalSpentItem',
                ),
                'payed_orders' => array(
                    'name' => 'Count only paid orders',     // _w('Count only paid orders')
                    'checkbox' => true,
                    'where' => array(
                        '=' => array(
                            '1' => ':tbl_order.paid_date IS NOT NULL'
                        )
                    )
                ),
                'number_of_orders' => array(
                    'name' => 'Number of orders',       // _w('Number of orders')
                    ':class' => 'crmContactsSearchShopNumberOfOrdersItem'
                ),
                'last_order_datetime' => array(
                    'name' => 'Last order',                 // _w('Last order')
                    ':class' => 'crmContactsSearchShopOrderDatetimeItem',
                    'options' => array('type' => 'last')
                ),
                'first_order_datetime' => array(
                    'name' => 'First order',                // _w('First order')
                    ':class' => 'crmContactsSearchShopOrderDatetimeItem',
                    'options' => array('type' => 'first')
                ),
                'coupon' => array(
                    'name' => 'Discount',                   // _w('Discount')
                    ':class' => 'crmContactsSearchShopCouponItem'
                ),
                'referer' => array(
                    'name' => 'Referer',                    // _w('Referer')
                    ':class' => 'crmContactsSearchShopRefererItem'
                ),
                'storefront' => array(
                    'name' => 'Storefront',             // _w('Storefront')
                    ':class' => 'crmContactsSearchShopStorefrontItem'
                ),
                'utm_campaign' => array(
                    'name' => 'UTM campaign',
                    ':class' => 'crmContactsSearchShopUtmCampaignItem'
                )
            )
        ),
    )
);
