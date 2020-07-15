<?php

class crmAtolonlinePluginCheckConnectionController extends waJsonController
{
    public function execute()
    {
        $data = waRequest::post('crm_atolonline', null, waRequest::TYPE_ARRAY_TRIM);
        $error = 'Подключение не удалось';

        if (!empty($data['login']) && !empty($data['pass']) && !empty($data['crm_company_id'])) {
            $res = crmAtolonlinePluginReceipt::send('getToken', array(
                'login' => $data['login'],
                'pass' => $data['pass'],
                'company_id' => $data['crm_company_id'],
                'api_version' => $data['api_version'],
                'debug_mode' => ifempty($data['debug_mode'], 'off'),
            ));

            if ($res['status'] == 'ok' && !empty($res['data']['token'])) {
                return;
            } elseif (!empty($res['data']['error']) && !empty($res['data']['error']['text'])) {
                $error = $res['data']['error']['text'];
            } elseif (!empty($res['data']['text'])) {
                $error = $res['data']['text'];
            }
        }
        $this->errors = $error;
    }
}
