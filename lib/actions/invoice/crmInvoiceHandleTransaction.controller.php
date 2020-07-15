<?php
/**
 * Perform invoice state change after one of action buttons is activated.
 */
class crmInvoiceHandleTransactionController extends crmJsonController
{
    private $invoice;
    private $data;

    public function execute()
    {
        $this->validateInvoice();
        $action = waRequest::post('action', null, waRequest::TYPE_STRING_TRIM);
        $this->data = waRequest::request('data');

        if (!in_array($action, explode('|', 'accept|refuse|refund|paid|activate|delete|archive|cancel|draft')) || !method_exists($this, $action)) {
            throw new waException('Incorrect action');
        }

        $this->$action();

        $this->invoice['contact'] = new waContact($this->invoice['contact_id']);
        $view = wa()->getView();
        $view->assign(array(
            'invoice'    => $this->invoice,
        ));
        $this->response = array(
            'html' => $view->fetch(wa()->getAppPath('templates/actions/invoice/InvoiceSidebar.item.inc.html', 'crm')),
        );
    }

    private function validateInvoice()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }
        $invoice_id = waRequest::post('invoice_id', null, waRequest::TYPE_INT);
        $im = new crmInvoiceModel();
        $this->invoice = $im->getById($invoice_id);
        if (!$invoice_id || !$this->invoice) {
            throw new waException('Invoicenot found');
        }
        if (!$this->getCrmRights()->contact($this->invoice['contact_id'])) {
            $this->accessDenied();
        }
    }

    private function accept()
    {
        $transaction_model = new waTransactionModel();
        $transactions = $transaction_model->select('*')->where(
            "app_id='crm' AND order_id = ".(int)$this->invoice['id']
            ." AND state = '".waPayment::STATE_AUTH."' AND type = '".waPayment::OPERATION_AUTH_ONLY."'"
        )->fetchAll('id');
        if ($transactions) {
            foreach ($transactions as $t) {
                $payment = new crmPayment();
                $response = $payment->capture(array(
                    'transaction' => $t,
                ));
                if ($response['result'] !== 0) {
                    $this->errors[] = 'Transaction error'
                        .(isset($response['description']) ? ': '.$response['description'] : '');
                }
            }
        }
        if (!$this->errors) {
            $now = date('Y-m-d H:i:s');
            $im = new crmInvoiceModel();
            $this->invoice['state_id'] = 'PAID';
            $im->updateById($this->invoice['id'], array(
                'state_id'         => $this->invoice['state_id'],
                'payment_datetime' => $now,
                'update_datetime'  => $now,
            ));
            $params = array('invoice' => $this->invoice);
            /**
             * @event invoice_payment
             * @param array [string]mixed $params
             * @param array [string]array $params['invoice']
             * @return bool
             */
            wa('crm')->event('invoice_payment', $params);
        }
    }

    private function refuse()
    {
        $transaction_model = new waTransactionModel();
        $transactions = $transaction_model->select('*')->where(
            "app_id='crm' AND order_id = ".(int)$this->invoice['id']
            ." AND state = '".waPayment::STATE_AUTH."' AND type = '".waPayment::OPERATION_AUTH_ONLY."'"
        )->fetchAll('id');
        if ($transactions) {
            foreach ($transactions as $t) {
                $payment = new crmPayment();
                $response = $payment->void(array(
                    'transaction' => $t,
                ));
                if ($response['result'] !== 0) {
                    $this->errors[] = 'Transaction error'
                        .(isset($response['description']) ? ': '.$response['description'] : '');
                }
            }
        }
        if (!$this->errors) {
            $im = new crmInvoiceModel();
            $this->invoice['state_id'] = 'PENDING';
            $im->updateById($this->invoice['id'], array(
                'state_id'        => $this->invoice['state_id'],
                'update_datetime' => date('Y-m-d H:i:s'),
            ));
        }
    }

    private function refund()
    {
        $transaction_model = new waTransactionModel();
        $transactions = $transaction_model->select('*')->where(
            "app_id='crm' AND order_id = ".(int)$this->invoice['id']
            ." AND state = '".waPayment::STATE_CAPTURED
            ."' AND (type = '".waPayment::OPERATION_AUTH_ONLY."' OR type = '".waPayment::OPERATION_AUTH_CAPTURE."')"
        )->fetchAll('id');
        if ($transactions) {
            foreach ($transactions as $t) {
                $module = waPayment::factory($t['plugin']);
                if (in_array(waPayment::OPERATION_REFUND, $module->supportedOperations())) {
                    $payment = new crmPayment();
                    $response = $payment->refund(array(
                        'transaction'   => $t,
                        'refund_amount' => $t['amount']
                    ));
                    if ($response['result'] !== 0) {
                        $this->errors[] = 'Transaction error'
                            .(isset($response['description']) ? ': '.$response['description'] : '');
                    }
                }
            }
        }
        if (!$this->errors) {
            $im = new crmInvoiceModel();
            $this->invoice['state_id'] = 'REFUNDED';
            $im->updateById($this->invoice['id'], array(
                'state_id'        => $this->invoice['state_id'],
                'update_datetime' => date('Y-m-d H:i:s'),
            ));

            $params = array('invoice' => $this->invoice, 'data' => $this->data);
            wa('crm')->event('invoice_refund', $params);
        }
    }

    private function paid()
    {
        if ($this->invoice['state_id'] != 'PENDING') {
            throw new waRightsException();
        }
        $im = new crmInvoiceModel();
        $this->invoice['state_id'] = 'PAID';
        $im->updateById($this->invoice['id'], array(
            'state_id'         => $this->invoice['state_id'],
            'payment_datetime' => date('Y-m-d H:i:s'),
            'update_datetime'  => date('Y-m-d H:i:s'),
        ));
        $params = array('invoice' => $this->invoice);
        /**
         * @event invoice_payment
         * @param array [string]mixed $params
         * @param array [string]array $params['invoice']
         * @return bool
         */
        wa('crm')->event('invoice_payment', $params);
    }

    private function activate()
    {
        if ($this->invoice['state_id'] != 'DRAFT') {
            throw new waRightsException();
        }
        $im = new crmInvoiceModel();
        $this->invoice['state_id'] = 'PENDING';
        $im->updateById($this->invoice['id'], array(
            'state_id'        => $this->invoice['state_id'],
            'update_datetime' => date('Y-m-d H:i:s'),
        ));
        $params = array('invoice' => $this->invoice);
        /**
         * @event invoice_activate
         * @param array [string]mixed $params
         * @param array [string]array $params['invoice']
         * @return bool
         */
        wa('crm')->event('invoice_activate', $params);

        $action = 'invoice_issue';
        $contact_id = $this->invoice['deal_id'] ? $this->invoice['deal_id'] * -1 : $this->invoice['contact_id'];
        $this->logAction($action, array('invoice_id' => $this->invoice['id']), $this->invoice['contact_id']);
        $lm = new crmLogModel();
        $lm->log($action, $contact_id, $this->invoice['id']);
    }

    private function delete()
    {
        if ($this->invoice['state_id'] != 'DRAFT') {
            throw new waRightsException();
        }
        $im = new crmInvoiceModel();
        $iim = new crmInvoiceItemsModel();
        $ipm = new crmInvoiceParamsModel();
        $im->deleteById($this->invoice['id']);
        $iim->deleteByField('invoice_id', $this->invoice['id']);
        $ipm->deleteByField('invoice_id', $this->invoice['id']);
    }

    private function archive()
    {
        if ($this->invoice['state_id'] != 'PENDING') {
            throw new waRightsException();
        }
        $im = new crmInvoiceModel();
        $this->invoice['state_id'] = 'ARCHIVED';
        $im->updateById($this->invoice['id'], array(
            'state_id'        => $this->invoice['state_id'],
            'update_datetime' => date('Y-m-d H:i:s'),
        ));

        $params = array('invoice' => $this->invoice);
        /**
         * @event invoice_expire
         * @param array [string]mixed $params
         * @param array [string]array $params['invoice']
         * @return bool
         */
        wa('crm')->event('invoice_expire', $params);
    }

    private function cancel()
    {
        if ($this->invoice['state_id'] != 'PAID' || strtotime($this->invoice['payment_datetime']) < time() - 60 * 60) {
            throw new waRightsException();
        }
        $tm = new waTransactionModel();
        $transactions = $tm->select('*')->where(
            "app_id='crm' AND order_id=".(int)$this->invoice['id']." AND state = '".waPayment::STATE_CAPTURED."' AND (type='"
            .waPayment::OPERATION_AUTH_ONLY."' OR type='".waPayment::OPERATION_AUTH_CAPTURE."')"
        )->fetchAll();
        if ($transactions) {
            throw new waRightsException();
        }
        $im = new crmInvoiceModel();
        $this->invoice['state_id'] = 'PENDING';
        $im->updateById($this->invoice['id'], array(
            'state_id'         => $this->invoice['state_id'],
            'payment_datetime' => null,
            'update_datetime'  => date('Y-m-d H:i:s'),
        ));
    }

    private function draft()
    {
        if ($this->invoice['state_id'] != 'PENDING') {
            throw new waRightsException();
        }
        $im = new crmInvoiceModel();
        $this->invoice['state_id'] = 'DRAFT';
        $im->updateById($this->invoice['id'], array(
            'state_id'        => $this->invoice['state_id'],
            'update_datetime' => date('Y-m-d H:i:s'),
        ));

        $params = array('invoice' => $this->invoice);
        /**
         * @event invoice_cancel
         * @param array [string]mixed $params
         * @param array [string]array $params['invoice']
         * @return bool
         */
        wa('crm')->event('invoice_cancel', $params);
    }
}
