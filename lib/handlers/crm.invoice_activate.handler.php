<?php

class crmCrmInvoice_activateHandler extends waEventHandler
{
    /**
     * @param $params [string][array] invoice
     * @return bool|void
     */
    public function execute(&$params)
    {
        return crmNotification::sendByEventType('invoice.issue', $params);
    }
}
