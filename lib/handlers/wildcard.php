<?php

return array(
    array(
        'event_app_id' => 'shop',
        'event'        => 'order_action.*',
        'class'        => 'crmShopOrder_actionHandler',
        'method'       => 'execute',
    )
);
