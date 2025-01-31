<?php

class crmPushDataMethod extends waAPIMethod
{
    public function execute()
    {
        $push_adapter = wa('crm')->getConfig()->getPushAdapter('onesignal');
        if (empty($push_adapter) || !$push_adapter->isEnabled()) {
            $this->response['provider'] = 'none';
            return;
        }
        $this->response['provider'] = $push_adapter->getId();
    }
}
