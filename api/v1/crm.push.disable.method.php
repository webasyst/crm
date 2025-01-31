<?php

class crmPushDisableMethod extends webasystPushDisableMethod
{
    protected function getPushAdapter()
    {
        return wa('crm')->getConfig()->getPushAdapter('onesignal');
    }
}
