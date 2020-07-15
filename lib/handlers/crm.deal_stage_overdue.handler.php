<?php

class crmCrmDeal_stage_overdueHandler extends waEventHandler
{
    /**
     * @param $params [array] deal
     * @return bool|void
     */
    public function execute(&$params)
    {
        return crmNotification::sendByEventType('deal.stage_overdue', $params);
    }
}
