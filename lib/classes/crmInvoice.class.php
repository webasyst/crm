<?php

class crmInvoice
{
    /**
     * @var array[string]waModel
     */
    static protected $models;

    public static function getState($state_id = null)
    {
        $states = self::getStates();
        if ($state_id) {
            return ifempty($states, $state_id, array('name' => $state_id));
        } else {
            return $states;
        }
    }

    public static function getStates($all = false)
    {
        $wa_app_url = wa()->getAppUrl('crm', true);
        $states = array(
            "DRAFT" => array(
                "id" => "DRAFT",
                "name" => _w('Draft'),
                "uri" => $wa_app_url."invoice/?state=draft",
                "icon" => "status-gray-tiny",
                "class" => "draft"
            ),
            "PENDING" => array(
                "id" => "PENDING",
                "name" => _w('Pending'),
                "uri" => $wa_app_url."invoice/?state=pending",
                "icon" => "status-green-tiny",
                "class" => "pending"
            ),
            "PROCESSING" => array(
                "id" => "PROCESSING",
                "name" => _w('Processing'),
                "uri" => $wa_app_url."invoice/?state=processing",
                "icon" => "light-bulb",
                "class" => "processing"
            ),
            "PAID" => array(
                "id" => "PAID",
                "name" => _w('Paid'),
                "uri" => $wa_app_url."invoice/?state=paid",
                "icon" => "yes-bw",
                "class" => "paid"
            ),
            "REFUNDED" => array(
                "id" => "REFUNDED",
                "name" => _w('Refunded'),
                "uri" => $wa_app_url."invoice/?state=refunded",
                "icon" => "no-bw",
                "class" => "refunded"
            ),
            "ARCHIVED" => array(
                "id" => "ARCHIVED",
                "name" => _w('Archived'),
                "uri" => $wa_app_url."invoice/?state=archived",
                "icon" => "trash",
                "class" => "archived"
            ),
        );

        if ($all) {
            return array(
                "ALL" => array(
                    "name" => _w('All'),
                    "uri" => $wa_app_url."invoice/?state=all"
                ),
            ) + $states;
        } else {
            return $states;
        }
    }

    /**
     * @param array $options
     * @return null|array
     */
    public static function cliInvoicesArchive($options = array())
    {
        self::getSettingsModel()->set('crm', 'invoices_archive_cli_start', date('Y-m-d H:i:s'));

        /**
         * @event start_invoices_archive_worker
         */
        wa('crm')->event('start_invoices_archive_worker');

        $im = new crmInvoiceModel();
        $now = date('Y-m-d');
        $count = 0;

        $list = $im->select('*')->where("due_date IS NOT NULL AND due_date < '$now' AND state_id = 'PENDING'")->fetchAll();
        foreach ($list as $invoice) {
            $im->updateById($invoice['id'], array(
                'state_id'        => 'ARCHIVED',
                'update_datetime' => date('Y-m-d H:i:s'),
            ));

            $crm_log_id = self::logPush('invoice_archived', $invoice);
            $params = [
                'crm_log_id' => $crm_log_id,
                'invoice'    => $invoice
            ];

            /**
             * @event invoice_expire
             * @param array [string]mixed $params
             * @param array [string]array $params['invoice']
             * @return bool
             */
            wa('crm')->event('invoice_expire', $params);
        }

        self::getSettingsModel()->set('crm', 'invoices_archive_cli_end', date('Y-m-d H:i:s'));

        return array(
            'total_count'     => $count,
            'processed_count' => $count,
            'count'           => $count,
            'done'            => $count,
        );
    }

    public static function getLastCliRunDateTime()
    {
        return self::getSettingsModel()->get('crm', 'invoices_archive_cli_end');
    }

    public static function isCliOk()
    {
        return !!self::getLastCliRunDateTime();
    }

    protected static function getSettingsModel()
    {
        return !empty(self::$models['asm']) ? self::$models['asm'] : (self::$models['asm'] = new waAppSettingsModel());
    }

    public static function accept($invoice)
    {
        $errors = [];
        $transaction_model = new waTransactionModel();
        $transactions = $transaction_model->select('*')->where("
            app_id = 'crm' AND order_id = i:invoice_id
            AND state = s:state_auth AND type = s:type
        ", [
            'invoice_id' => $invoice['id'],
            'state_auth' => waPayment::STATE_AUTH,
            'type'       => waPayment::OPERATION_AUTH_ONLY
        ])->fetchAll('id');
        if ($transactions) {
            foreach ($transactions as $t) {
                $payment = new crmPayment();
                $response = $payment->capture(array(
                    'transaction' => $t,
                ));
                if ($response['result'] !== 0) {
                    $errors[] = 'Transaction error'.(isset($response['description']) ? ': '.$response['description'] : '');
                }
            }
        }
        if ($errors) {
            return $errors;
        }
        $now = date('Y-m-d H:i:s');
        $im = new crmInvoiceModel();
        $invoice['state_id'] = 'PAID';
        $im->updateById($invoice['id'], [
            'state_id'         => $invoice['state_id'],
            'payment_datetime' => $now,
            'update_datetime'  => $now,
        ]);
        $crm_log_id = self::logPush('invoice_paid', $invoice);
        $params = [
            'invoice'    => $invoice,
            'crm_log_id' => $crm_log_id
        ];

        /**
         * @event invoice_payment
         * @param array [string]mixed $params
         * @param array [string]array $params['invoice']
         * @return bool
         */
        wa('crm')->event('invoice_payment', $params);

        return [];
    }

    public static function refuse($invoice)
    {
        $errors = [];
        $transaction_model = new waTransactionModel();
        $transactions = $transaction_model->select('*')->where("
            app_id = 'crm' AND order_id = i:invoice_id
            AND state = s:state_auth AND type = s:type
        ", [
            'invoice_id' => $invoice['id'],
            'state_auth' => waPayment::STATE_AUTH,
            'type'       => waPayment::OPERATION_AUTH_ONLY
        ])->fetchAll('id');
        if ($transactions) {
            foreach ($transactions as $t) {
                $payment = new crmPayment();
                $response = $payment->void([
                    'transaction' => $t,
                ]);
                if ($response['result'] !== 0) {
                    $errors[] = 'Transaction error'.(isset($response['description']) ? ': '.$response['description'] : '');
                }
            }
        }
        if ($errors) {
            return $errors;
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = 'PENDING';
        $im->updateById($invoice['id'], [
            'state_id'        => $invoice['state_id'],
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);
        self::logPush('invoice_paiment_canceled', $invoice);

        return [];
    }

    public static function refund($invoice, $data = null)
    {
        $errors = [];
        $transaction_model = new waTransactionModel();
        $transactions = $transaction_model->select('*')->where("
            app_id = 'crm' AND order_id = i:invoice
            AND state = s:captured
            AND (type = s:type_1 OR type = s:type_2)
        ", [
            'invoice'  => $invoice['id'],
            'captured' => waPayment::STATE_CAPTURED,
            'type_1'   => waPayment::OPERATION_AUTH_ONLY,
            'type_2'   => waPayment::OPERATION_AUTH_CAPTURE
        ])->fetchAll('id');
        if ($transactions) {
            foreach ($transactions as $t) {
                $module = waPayment::factory($t['plugin']);
                if (in_array(waPayment::OPERATION_REFUND, $module->supportedOperations())) {
                    $payment  = new crmPayment();
                    $response = $payment->refund([
                        'transaction'   => $t,
                        'refund_amount' => $t['amount']
                    ]);
                    if ($response['result'] !== 0) {
                        $errors[] = 'Transaction error'.(isset($response['description']) ? ': '.$response['description'] : '');
                    }
                }
            }
        }
        if ($errors) {
            return $errors;
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = 'REFUNDED';
        $im->updateById($invoice['id'], [
            'state_id'        => $invoice['state_id'],
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);
        $crm_log_id = self::logPush('invoice_refunded', $invoice);
        $params = [
            'invoice'    => $invoice,
            'data'       => $data,
            'crm_log_id' => $crm_log_id
        ];

        /**
         * @event invoice_refund
         * @param array $params
         * @return bool
         */
        wa('crm')->event('invoice_refund', $params);

        return [];
    }

    public static function paid($invoice)
    {
        if ($invoice['state_id'] != 'PENDING') {
            return [(new waRightsException())->getMessage()];
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = 'PAID';
        $im->updateById($invoice['id'], [
            'state_id'         => $invoice['state_id'],
            'payment_datetime' => date('Y-m-d H:i:s'),
            'update_datetime'  => date('Y-m-d H:i:s'),
        ]);
        $log_id = self::logPush('invoice_paid', $invoice);
        $params = [
            'invoice'    => $invoice,
            'crm_log_id' => $log_id,
        ];

        /**
         * @event invoice_payment
         * @param array [string]mixed $params
         * @param array [string]array $params['invoice']
         * @return bool
         */
        wa('crm')->event('invoice_payment', $params);

        return [];
    }

    public static function activate($invoice)
    {
        if ($invoice['state_id'] != 'DRAFT') {
            return [(new waRightsException())->getMessage()];
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = 'PENDING';
        $im->updateById($invoice['id'], [
            'state_id'        => $invoice['state_id'],
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);
        $action = 'invoice_issue';
        if (!class_exists('waLogModel')) {
            wa('webasyst');
            $log_model = new waLogModel();
            $log_model->add($action, ['invoice_id' => $invoice['id']], $invoice['contact_id']);
        }
        $log_id = self::logPush($action, $invoice);
        $params = [
            'crm_log_id' => $log_id,
            'invoice'    => $invoice
        ];

        /**
         * @event invoice_activate
         * @param array [string]mixed $params
         * @param array [string]array $params['invoice']
         * @return bool
         */
        wa('crm')->event('invoice_activate', $params);

        return [];
    }

    public static function delete($invoice)
    {
        if ($invoice['state_id'] != 'DRAFT') {
            return [(new waRightsException())->getMessage()];
        }
        $im = new crmInvoiceModel();
        $iim = new crmInvoiceItemsModel();
        $ipm = new crmInvoiceParamsModel();
        $im->deleteById($invoice['id']);
        $iim->deleteByField('invoice_id', $invoice['id']);
        $ipm->deleteByField('invoice_id', $invoice['id']);
        self::logPush('invoice_delete', $invoice);

        return [];
    }

    public static function archive($invoice)
    {
        if ($invoice['state_id'] != 'PENDING') {
            return [(new waRightsException())->getMessage()];
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = 'ARCHIVED';
        $im->updateById($invoice['id'], [
            'state_id'        => $invoice['state_id'],
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);
        $crm_log_id = self::logPush('invoice_archived', $invoice);
        $params = [
            'crm_log_id' => $crm_log_id,
            'invoice'    => $invoice
        ];

        /**
         * @event invoice_expire
         * @param array [string]mixed $params
         * @param array [string]array $params['invoice']
         * @return bool
         */
        wa('crm')->event('invoice_expire', $params);

        return [];
    }

    public static function cancel($invoice)
    {
        if ($invoice['state_id'] != 'PAID' || strtotime($invoice['payment_datetime']) < time() - 60 * 60) {
            return [(new waRightsException())->getMessage()];
        }
        $tm = new waTransactionModel();
        $transactions = $tm->select('*')->where("
            app_id = 'crm' AND order_id = i:invoice_id
            AND state = s:captured
            AND (type = s:type_1 OR type = s:type_2)
        ", [
            'invoice_id' => $invoice['id'],
            'captured'   => waPayment::STATE_CAPTURED,
            'type_1'     => waPayment::OPERATION_AUTH_ONLY,
            'type_2'     => waPayment::OPERATION_AUTH_CAPTURE
        ])->fetchAll();
        if ($transactions) {
            return [(new waRightsException())->getMessage()];
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = 'PENDING';
        $im->updateById($invoice['id'], [
            'state_id'         => $invoice['state_id'],
            'payment_datetime' => null,
            'update_datetime'  => date('Y-m-d H:i:s'),
        ]);

        return [];
    }

    public static function draft($invoice)
    {
        if ($invoice['state_id'] != 'PENDING') {
            return [(new waRightsException())->getMessage()];
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = 'DRAFT';
        $im->updateById($invoice['id'], [
            'state_id'        => $invoice['state_id'],
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);
        $crm_log_id = self::logPush('invoice_cancel', $invoice);
        $params = [
            'crm_log_id' => $crm_log_id,
            'invoice'    => $invoice
        ];

        /**
         * @event invoice_cancel
         * @param array [string]mixed $params
         * @param array [string]array $params['invoice']
         * @return bool
         */
        wa('crm')->event('invoice_cancel', $params);

        return [];
    }

    /**
     * @param $action
     * @param $invoice
     * @return int
     */
    private static function logPush($action, $invoice)
    {
        $contact_id = (empty($invoice['deal_id']) ? ifempty($invoice, 'contact_id', null) : -1 * $invoice['deal_id']);

        return (int) (new crmLogModel())->log(
            $action,
            $contact_id,
            ifset($invoice, 'id', null)
        );
    }
}
