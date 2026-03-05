<?php

class crmInvoiceRecurrentSaveController extends crmJsonController
{
    protected static $allowed_units = [
        crmInvoiceRecurrentModel::UNIT_DAY,
        crmInvoiceRecurrentModel::UNIT_WEEK,
        crmInvoiceRecurrentModel::UNIT_MONTH,
        crmInvoiceRecurrentModel::UNIT_YEAR
    ];

    protected $invoice_model;
    protected $recurrent_model;

    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        $invoice_id = waRequest::post('invoice_id', null, waRequest::TYPE_INT);
        $recurrent_id = waRequest::post('recurrent_id', null, waRequest::TYPE_INT);
        $unit = waRequest::post('interval_unit', null, waRequest::TYPE_STRING);
        $value = waRequest::post('interval_value', 1, waRequest::TYPE_INT);
        $next_date = waRequest::post('next_date', null, waRequest::TYPE_STRING);
        $number_template = waRequest::post('number_template', null, waRequest::TYPE_STRING);
        $stop_on_non_payment = waRequest::post('stop_on_non_payment', null, waRequest::TYPE_INT);

        if (empty($invoice_id) && empty($recurrent_id) || empty($unit) || !in_array($unit, self::$allowed_units) || $value <= 0) {
            throw new waException(_w('Invalid data.'), 400);
        }

        if (!empty($next_date) && !checkdate(substr($next_date, 5, 2), substr($next_date, 8, 2), substr($next_date, 0, 4))) {
            throw new waException(_w('Invalid next date format.'), 400);
        }

        if (!empty($stop_on_non_payment) && $stop_on_non_payment < 0) {
            throw new waException(_w('The number of non-paid invoices must be positive.'), 400);
        }

        if (!empty($invoice_id)) {
            $invoice = $this->getInvoiceModel()->getById($invoice_id);
            if (empty($invoice)) {
                throw new waException(_w('Invoice not found'), 404);
            }
        }

        if (empty($recurrent_id)) {
            $recurrent_id = $invoice['recurrent_id'];
        }

        if (!empty($recurrent_id)) {
            $recurrent = $this->getInvoiceRecurrentModel()->getById($recurrent_id);
            if (empty($recurrent)) {
                //throw new waException(_w('Recurring record not found.'), 404);
                $recurrent_id = null;
            }
        }

        if (empty($next_date)) {
            $next_date = date('Y-m-d', strtotime('+'.$value.' '. $unit));
        }

        if (empty($recurrent)) {
            $recurrent_id = $this->getInvoiceRecurrentModel()->insert([
                'create_datetime' => date('Y-m-d H:i:s'),
                'origin_invoice_id' => $invoice_id,
                'interval_unit' => $unit,
                'interval_value' => $value,
                'number_template' => $number_template,
                'stop_on_non_payment' => empty($stop_on_non_payment) ? 0 : $stop_on_non_payment,
                'counter' => 0,
                'last_datetime' => null,
                'next_date' => $next_date,
                'end_datetime' => null,
            ]);
            $this->getInvoiceModel()->updateById($invoice_id, ['recurrent_id' => $recurrent_id]);
        } else {
            $update = [
                'interval_unit' => $unit,
                'interval_value' => $value,
                'next_date' => $next_date,
                'end_datetime' => null,
            ];
            if ($number_template !== null) {
                $update['number_template'] = $number_template;
            }
            if ($stop_on_non_payment !== null) {
                $update['stop_on_non_payment'] = $stop_on_non_payment;
            }
            $this->getInvoiceRecurrentModel()->updateById($recurrent_id, $update);
        }

        $this->response = [
            'recurrent_id' => $recurrent_id,
            'interval_unit' => $unit,
            'interval_value' => $value,
            'recurrent_description' => crmInvoiceRecurrentModel::getDescription([
                'interval_unit' => $unit,
                'interval_value' => $value,
            ])
        ];
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
