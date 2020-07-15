<?php

class crmCrmDeal_moveHandler extends waEventHandler
{
    /**
     * @param $params [array] deal
     * @return bool|void
     */
    public function execute(&$params)
    {
        return crmNotification::sendByEventType('deal.move', $params);
    }
}
