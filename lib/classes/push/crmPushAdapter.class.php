<?php

class crmPushAdapter extends onesignalPush
{
    protected $net_no_auth;
    protected $is_no_auth = false;
    protected $mobile_push_only = false;
    const ONESIGNAL_APP_ID = '3781e1e5-4a46-49b8-877f-ecf5dbf4ca56';
    

    public function setMobilePushOnly($is_mobile_push_only)
    {
        $this->mobile_push_only = $is_mobile_push_only;
    }

    public function getId()
    {
        return 'onesignal';
    }

    public function isEnabled()
    {
        return true;
    }

    protected function getNet()
    {
        if ($this->is_no_auth) {
            if (empty($this->net_no_auth)) {
                $options = [
                    'timeout' => 7,
                    'format' => waNet::FORMAT_JSON,
                ];
                $this->net_no_auth = new waNet($options);
            }

            return $this->net_no_auth;
        }

        return parent::getNet();
    }

    protected function request($api_method, $request_data = array(), $request_method = waNet::METHOD_GET)
    {
        if ($api_method === 'notifications') {
            $this->is_no_auth = ifset($request_data['app_id']) === self::ONESIGNAL_APP_ID;
            if ($this->mobile_push_only && !$this->is_no_auth) {
                // on mobile_push_only do not try to send web push
                return null;
            }
        }
        return parent::request($api_method, $request_data, $request_method);
    }

}

