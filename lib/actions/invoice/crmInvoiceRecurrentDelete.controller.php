<?php

class crmInvoiceRecurrentDeleteController extends crmJsonController
{

    protected $invoice_model;
    protected $recurrent_model;

    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        $invoice_id = waRequest::post('invoice_id', null, waRequest::TYPE_INT);
        $recurrent_id = waRequest::post('recurrent_id', null, waRequest::TYPE_INT);

        if (empty($invoice_id) && empty($recurrent_id)) {
            throw new waException(_w('Invalid data.'), 400);
        }

        if (empty($recurrent_id)) {
            $invoice = $this->getInvoiceModel()->getById($invoice_id);
            if (empty($invoice)) {
                throw new waException(_w('Invoice not found'), 404);
            }
            $recurrent_id = $invoice['recurrent_id'];
        }

        if (empty($recurrent_id)) {
            // do nothing
            return;
        }

        if (!empty($recurrent_id)) {
            $recurrent = $this->getInvoiceRecurrentModel()->getById($recurrent_id);
        }

        if (empty($recurrent) || !empty($recurrent['end_datetime'])) {
            // do nothing
            return;
        }

        if ($recurrent['counter'] > 0) {
            $this->getInvoiceRecurrentModel()->updateById($recurrent_id, ['end_datetime' => date('Y-m-d H:i:s')]);
        } else {
            $this->getInvoiceRecurrentModel()->deleteById($recurrent_id);
            $this->getInvoiceModel()->updateByField(['recurrent_id' => $recurrent_id], ['recurrent_id' => null]);
        }
    }

    protected function getInvoiceModel()
    {
        if (empty($this->invoice_model)) {
            $this->invoice_model = new crmInvoiceModel();
        }
        return $this->invoice_model;
    }

    protected function getInvoiceRecurrentModel()
    {
        if (empty($this->recurrent_model)) {
            $this->recurrent_model = new crmInvoiceRecurrentModel();
        }
        return $this->recurrent_model;
    }

}
