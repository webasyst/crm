<?php

class crmCrmDeal_createHandler extends waEventHandler
{
    /**
     * @param $params [array] deal
     * @return bool|void
     */
    public function execute(&$params)
    {
        return crmNotification::sendByEventType('deal.create', $params);
    }
}
