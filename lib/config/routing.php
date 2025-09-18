<?php

return array(
    'form/?'                                => 'frontend/formSubmit',
    'form/regions/?'                        => 'frontend/formRegions',
    'form/iframe/<id:\d+>/?'                => 'frontend/formIframe',
    'form/headless/<id:\d+>/?'              => 'frontend/formHeadless',
    'form/<hash>/?'                         => 'frontend/form',
    'confirm_email/<hash>/?'                => 'frontend/confirmEmail',
    'invoice/<hash>/?'                      => 'frontend/invoice',
    'data/payment/<plugin_id>/<action_id>/' => 'frontend/paymentPlugin',
    'verification' => [
        'url' => 'verification/<verification_key>/<message_id>/<hash>/?',
        'module' => 'frontend',
        'action' => 'verification',
        'secure' => true,
    ],
);
