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
        $states = [
            crmInvoiceModel::STATE_DRAFT => [
                "id" => crmInvoiceModel::STATE_DRAFT,
                "name" => _w('Draft'),
                "uri" => $wa_app_url."invoice/?state=draft",
                "icon" => "status-gray-tiny",
                "class" => "draft"
            ],
            crmInvoiceModel::STATE_PENDING => [
                "id" => crmInvoiceModel::STATE_PENDING,
                "name" => _w('Pending'),
                "uri" => $wa_app_url."invoice/?state=pending",
                "icon" => "status-green-tiny",
                "class" => "pending"
            ],
            crmInvoiceModel::STATE_PROCESSING => [
                "id" => crmInvoiceModel::STATE_PROCESSING,
                "name" => _w('Processing'),
                "uri" => $wa_app_url."invoice/?state=processing",
                "icon" => "light-bulb",
                "class" => "processing"
            ],
            crmInvoiceModel::STATE_PAID => [
                "id" => crmInvoiceModel::STATE_PAID,
                "name" => _w('Paid'),
                "uri" => $wa_app_url."invoice/?state=paid",
                "icon" => "yes-bw",
                "class" => "paid"
            ],
            crmInvoiceModel::STATE_REFUNDED => [
                "id" => crmInvoiceModel::STATE_REFUNDED,
                "name" => _w('Refunded'),
                "uri" => $wa_app_url."invoice/?state=refunded",
                "icon" => "no-bw",
                "class" => "refunded"
            ],
            crmInvoiceModel::STATE_ARCHIVED => [
                "id" => crmInvoiceModel::STATE_ARCHIVED,
                "name" => _w('Archived'),
                "uri" => $wa_app_url."invoice/?state=archived",
                "icon" => "trash",
                "class" => "archived"
            ],
        ];

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

        $list = $im->select('*')->where('due_date IS NOT NULL AND due_date < :now AND state_id = :state_pending', [
            'now' => $now, 
            'state_pending' => crmInvoiceModel::STATE_PENDING
        ])->fetchAll();
        foreach ($list as $invoice) {
            $im->updateById($invoice['id'], [
                'state_id'        => crmInvoiceModel::STATE_ARCHIVED,
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
        $invoice['state_id'] = crmInvoiceModel::STATE_PAID;
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
        $invoice['state_id'] = crmInvoiceModel::STATE_PENDING;
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
        $invoice['state_id'] = crmInvoiceModel::STATE_REFUNDED;
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
        if ($invoice['state_id'] != crmInvoiceModel::STATE_PENDING) {
            return [(new waRightsException())->getMessage()];
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = crmInvoiceModel::STATE_PAID;
        $im->updateById($invoice['id'], [
            'state_id'         => $invoice['state_id'],
            'payment_datetime' => date('Y-m-d H:i:s'),
            'update_datetime'  => date('Y-m-d H:i:s'),
        ]);

        if (!empty($invoice['recurrent_id'])) {
            $recurrent_model = new crmInvoiceRecurrentModel();
            $recurrent_record = $recurrent_model->getById($invoice['recurrent_id']);
            if (!empty($recurrent_record)) {
                $non_paid_count = $recurrent_record['non_paid_count'];
                if ($non_paid_count > 0) {
                    $non_paid_count = $recurrent_record['last_invoice_id'] == $invoice['id'] ? 0 : $non_paid_count - 1;
                    $recurrent_model->updateById($invoice['recurrent_id'], [
                        'non_paid_count' => $non_paid_count
                    ]);
                }
            }
        }

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
        if ($invoice['state_id'] != crmInvoiceModel::STATE_DRAFT) {
            return [(new waRightsException())->getMessage()];
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = crmInvoiceModel::STATE_PENDING;
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
        if ($invoice['state_id'] != crmInvoiceModel::STATE_DRAFT) {
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
        if ($invoice['state_id'] != crmInvoiceModel::STATE_PENDING) {
            return [(new waRightsException())->getMessage()];
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = crmInvoiceModel::STATE_ARCHIVED;
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
        if ($invoice['state_id'] != crmInvoiceModel::STATE_PAID || strtotime($invoice['payment_datetime']) < time() - 60 * 60) {
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
        $invoice['state_id'] = crmInvoiceModel::STATE_PENDING;
        $im->updateById($invoice['id'], [
            'state_id'         => $invoice['state_id'],
            'payment_datetime' => null,
            'update_datetime'  => date('Y-m-d H:i:s'),
        ]);

        return [];
    }

    public static function draft($invoice)
    {
        if ($invoice['state_id'] != crmInvoiceModel::STATE_PENDING) {
            return [(new waRightsException())->getMessage()];
        }
        $im = new crmInvoiceModel();
        $invoice['state_id'] = crmInvoiceModel::STATE_DRAFT;
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

    public static function recurrentIssue()
    {
        if (!crmHelper::isPremium()) {
            // Only for premium
            return;
        }

        $app_settings_model = new waAppSettingsModel();
        $last_start_ts = $app_settings_model->get('crm', 'recurrent_invoice_issue_last_start');
        if (!empty($last_start_ts) && $last_start_ts + 30*60 > time()) {
            // Recentry started (maybe still running)
            return;
        }

        $last_run_date = $app_settings_model->get('crm', 'recurrent_invoice_issue_last_run');
        if (!empty($last_run_date) && $last_run_date >= date('Y-m-d')) {
            // Already executed today
            return;
        }

        $app_settings_model->set('crm', 'recurrent_invoice_issue_last_start', time());

        $invoice_model = new crmInvoiceModel();
        $invoice_items_model = new crmInvoiceItemsModel();
        $invoice_recurrent_model = new crmInvoiceRecurrentModel();
        $currency_model = new crmCurrencyModel();

        // get issue list
        $issue_list = $invoice_recurrent_model->getIssueList();

        // prepare origin invoices
        $origin_ids = array_column($issue_list, 'origin_invoice_id');
        $origin_invoices = $invoice_model->getById($origin_ids);
        $currency_ids = array_column($origin_invoices, 'currency_id');
        $currency_info = $currency_model->getById($currency_ids);
        $origin_invoices = array_map(function($invoice) use ($currency_info) {
            unset($invoice['id']);
            unset($invoice['number']);
            unset($invoice['payment_datetime']);
            $invoice['create_datetime'] = date('Y-m-d H:i:s');
            $invoice['update_datetime'] = date('Y-m-d H:i:s');
            $invoice['creator_contact_id'] = 0;
            
            $result_invoice['currency_rate'] = ifset($currency_info[$invoice['currency_id']]['rate'], 1);
            if (!empty($invoice['due_days']) && $invoice['due_days'] > 0) {
                $invoice['due_date'] = date('Y-m-d', strtotime('+'.$invoice['due_days'].' day'));
            } elseif (!empty($invoice['due_date']) && !empty($invoice['invoice_date'])) {
                $issueed_date = new DateTimeImmutable($invoice['invoice_date']);
                $due_date = new DateTimeImmutable($invoice['due_date']);
                $interval = $issueed_date->diff($due_date);
                $invoice['due_date'] = date('Y-m-d', strtotime('+'.$interval->days.' day'));
            } else {
                unset($invoice['due_date']);
            }
            $invoice['invoice_date'] = date('Y-m-d');
            $invoice['state_id'] = crmInvoiceModel::STATE_DRAFT;
            return $invoice;
        }, $origin_invoices);

        // prepare origin invoice items
        $origin_invoice_items = $invoice_items_model->getByField(['invoice_id' => $origin_ids], true);
        $origin_invoice_items = array_reduce($origin_invoice_items, function($result, $item) {
            $invoice_id = $item['invoice_id'];
            if (!isset($result[$invoice_id])) {
                $result[$invoice_id] = [];
            }
            unset($item['id']);
            unset($item['invoice_id']);
            $result[$invoice_id][] = $item;
            return $result;
        }, []);

        $issued_invoice_ids = [];

        foreach ($issue_list as $recurrent) {
            $invoice = ifset($origin_invoices[$recurrent['origin_invoice_id']]);
            if (empty($invoice)) {
                continue;
            }

            $invoice_items = $origin_invoice_items[$recurrent['origin_invoice_id']] ?? [];
            $recurrent_counter = $recurrent['counter'] + 1;
            
            if (!empty($recurrent['number_template'])) {
                $invoice['number'] = str_replace('%COUNT', $recurrent_counter, $recurrent['number_template']);
                $invoice['number'] = str_replace('%MONTH', date('m'), $invoice['number']);
                $invoice['number'] = str_replace('%YEAR', date('Y'), $invoice['number']);
            }

            $invoice_id = $invoice_model->insert($invoice);
            $invoice['id'] = $invoice_id;
            $issued_invoice_ids[] = $invoice_id;
            if (!empty($recurrent['number_template']) && strpos($recurrent['number_template'], '%ID') !== false) {
                $invoice['number'] = str_replace('%ID', $invoice_id, $invoice['number']);
                $invoice_model->updateById($invoice_id, ['number' => $invoice['number']]);
            }

            if (!empty($invoice_items)) {
                $invoice_items = array_map(function($item) use ($invoice_id) {
                    $item['invoice_id'] = $invoice_id;
                    return $item;
                }, $invoice_items);

                $invoice_items_model->multipleInsert($invoice_items);
            }

            self::activate($invoice);

            $invoice_recurrent_model->updateById($recurrent['id'], [
                'counter' => $recurrent_counter,
                'non_paid_count' => $recurrent['non_paid_count'] + 1,
                'last_datetime' => date('Y-m-d H:i:s'),
                'last_invoice_id' => $invoice_id,
                'next_date' => date('Y-m-d', strtotime('+'.$recurrent['interval_value'].' '.$recurrent['interval_unit']))
            ]);
        }

        $app_settings_model->set('crm', 'recurrent_invoice_issue_last_run', date('Y-m-d'));

        return $issued_invoice_ids;
    }
}
