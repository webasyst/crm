<?php

class crmPushEnableMethod extends webasystPushEnableMethod
{
    protected function getPushAdapter()
    {
        return wa('crm')->getConfig()->getPushAdapter('onesignal');
    }

    protected function readBodyAsJson()
    {
        $body = $this->readBody();
        if ($body) {
            $body = json_decode($body, true);
            $body['scope'] = 'crm';
            return $body;
        }
        return null;
    }
}
