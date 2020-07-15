<?php

class crmCrmInvoice_expireHandler extends waEventHandler
{
    /**
     * @param $params [string][array] invoice
     * @return bool|void
     */
    public function execute(&$params)
    {
        return crmNotification::sendByEventType('invoice.expire', $params);
    }
}
