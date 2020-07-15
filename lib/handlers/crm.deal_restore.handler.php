<?php

class crmCrmDeal_restoreHandler extends waEventHandler
{
    /**
     * @param $params [array] deal
     * @return bool|void
     */
    public function execute(&$params)
    {
        return crmNotification::sendByEventType('deal.restore', $params);
    }
}
