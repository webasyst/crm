<?php

/**
 * HTML for a invoice recurrent dialog.
 */
class crmInvoiceRecurrentDialogAction extends crmViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        $invoice_id = waRequest::get('invoice_id', null, waRequest::TYPE_INT);
        $invoice = $this->getInvoiceModel()->getInvoice($invoice_id);
        if (empty($invoice)) {
            throw new waException(_w('Invoice not found'), 404);
        }

        $this->view->assign('invoice', $invoice);

        $interval_units = [
            crmInvoiceRecurrentModel::UNIT_DAY => _w('Day'),
            crmInvoiceRecurrentModel::UNIT_WEEK => _w('Week'),
            crmInvoiceRecurrentModel::UNIT_MONTH => _w('Month'),
            crmInvoiceRecurrentModel::UNIT_YEAR => _w('Year'),
        ];
        $this->view->assign('interval_units', $interval_units);

        $recurrent = empty($invoice['recurrent_id']) ? null : $this->getInvoiceRecurrentModel()->getById($invoice['recurrent_id']);
        if (empty($recurrent)) {
            $recurrent = [
                'interval_unit' => crmInvoiceRecurrentModel::UNIT_MONTH,
                'interval_value' => 1,
                'number_template' => '',
                'stop_on_non_payment' => 0,
                'next_date' => date('Y-m-d', strtotime('+1 month')),
            ];
        }
        $this->view->assign('recurrent', $recurrent);

        $number_template_placeholder = in_array($recurrent['interval_unit'], [ 
            crmInvoiceRecurrentModel::UNIT_MONTH, 
            crmInvoiceRecurrentModel::UNIT_YEAR,
        ]) ? $invoice['number'] . '-%YEAR-%MONTH' : $invoice['number'] . '-%COUNT';

        $this->view->assign('number_template_placeholder', $number_template_placeholder);
    }
}