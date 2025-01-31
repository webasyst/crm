<?php

class crmWsUrlController extends waJsonController
{
    public function execute()
    {
        if (!class_exists('waServicesApi')) {
            $this->errors = [
                'error_code' => 'not_implemented',
                'error_description' => _w('Not implemented yet.'),
            ];
            return;
        }

        $channel = waRequest::get('channel', 'default', waRequest::TYPE_STRING_TRIM);
        $ws_url = null;
        $servicesApi = new waServicesApi();
        if ($servicesApi->isConnected()) {
            try {
                $ws_url = $servicesApi->getWebsocketUrl($channel);
            } catch (waException $e) {
                $this->errors = [
                    'error_code' => 'no_ws_url',
                    'error_description' => $e->getMessage(),
                ];
                return;
            }
        } else {
            $this->errors = [
                'error_code' => 'no_waid',
                'error_description' => _w('Webasyst ID services are not connected.'),
            ];
            return;
        }

        if (empty($ws_url)) {
            $this->errors = [
                'error_code' => 'no_ws_url',
                'error_description' => _w('Webasyst websocket API error.'),
            ];
            return;
        }
        $this->response = [
            'ws_url' => $ws_url,
        ];
    }
}
