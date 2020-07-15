<?php

class crmCrmDeal_lostHandler extends waEventHandler
{
    /**
     * @param $params [array] deal
     * @return bool|void
     */
    public function execute(&$params)
    {
        return crmNotification::sendByEventType('deal.lost', $params);
    }
}
