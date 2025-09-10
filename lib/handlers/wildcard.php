<?php

return [
    [
        'event_app_id' => 'shop',
        'event'        => 'order_action.*',
        'class'        => 'crmShopOrder_actionHandler',
        'method'       => 'execute',
    ],
    [
        'event_app_id' => '*',
        'event'        => 'wa.frontend_head',
        'class'        => 'crmWaFrontendHeadHandler',
        'method'       => 'execute',
    ],
];
