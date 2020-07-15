<?php

return array(
    'form/?'                                => 'frontend/formSubmit',
    'form/regions/?'                        => 'frontend/formRegions',
    'form/iframe/<id>/?'                    => 'frontend/formIframe',
    'confirm_email/<hash>/?'                => 'frontend/confirmEmail',
    'invoice/<hash>/?'                      => 'frontend/invoice',
    'data/payment/<plugin_id>/<action_id>/' => 'frontend/paymentPlugin',
);
