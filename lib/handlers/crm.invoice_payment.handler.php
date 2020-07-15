<?php

class crmCrmInvoice_paymentHandler extends waEventHandler
{
    /**
     * @param $params [string][array] invoice
     * @return bool|void
     */
    public function execute(&$params)
    {
        return crmNotification::sendByEventType('invoice.payment', $params);
    }
}
