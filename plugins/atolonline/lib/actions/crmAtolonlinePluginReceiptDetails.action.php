<?php

class crmAtolonlinePluginReceiptDetailsAction extends waViewAction
{
    public function execute()
    {
        $receipt_id = waRequest::request('id', null, waRequest::TYPE_INT);

        // Check access rights
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        $receipt = null;
        if ($receipt_id) {
            $rm = new crmAtolonlineReceiptModel();
            $receipt = $rm->getById($receipt_id);
            if (!$receipt) {
                throw new waException('Receipt not found', 404);
            }
            $receipt['receipt_data'] = $receipt['receipt_data'] ? json_decode($receipt['receipt_data'], true) : null;
        }

        $sno = $payment_object = $payment_method = array();
        foreach (crmAtolonlinePlugin::getSno() as $s) {
            $sno[$s['value']] = $s['title'];
        }
        foreach (crmAtolonlinePlugin::getPaymentObject() as $o) {
            $payment_object[$o['value']] = $o['title'];
        }
        foreach (crmAtolonlinePlugin::getPaymentMethod() as $m) {
            $payment_method[$m['value']] = $m['title'];
        }

        $this->view->assign(array(
            'receipt' => $receipt,
            'sno'     => $sno,
            'payment_object' => $payment_object,
            'payment_method' => $payment_method,
        ));
        $this->setTemplate('plugins/atolonline/templates/ReceiptDetails.html');
    }
}
