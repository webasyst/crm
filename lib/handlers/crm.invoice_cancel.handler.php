<?php

class crmCrmInvoice_cancelHandler extends waEventHandler
{
    /**
     * @param $params [string][array] invoice
     * @return bool|void
     */
    public function execute(&$params)
    {
        return crmNotification::sendByEventType('invoice.cancel', $params);
    }
}
