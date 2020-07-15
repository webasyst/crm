<?php

class crmAtolonlinePluginReceipt
{
    /**
     * @var int
     */
    private static $company_id;
    /**
     * @var string|null
     */
    private static $token;
    /**
     * @var string|null
     */
    private static $error;
    /**
     * @var int|null
     */
    private static $api_version;
    /**
     * @var bool|null
     */
    private static $debug_mode;

    /**
     * @param bool $new_token
     * @return mixed|null
     * @throws waException
     */
    public static function getToken($new_token = false)
    {
        if (self::$token && !$new_token) {
            return self::$token;
        }
        $cache = new waVarExportCache('atolonline_token_'.self::$company_id, 60*60*24, 'crm');
        if ($cache) {
            self::$token = $cache->get();
            if (self::$token && !$new_token) {
                return self::$token;
            }
        }
        self::$token = null;

        $config = self::getConfig();

        $res = self::send('getToken', array('login' => $config[self::$company_id.':login'], 'pass' => $config[self::$company_id.':pass']));

        if (!empty($res['data']) && !empty($res['data']['token'])) {
            self::$token = $res['data']['token'];
        }
        if ($cache && self::$token) {
            $cache->set(self::$token);
        }
        return self::$token;
    }

    /**
     * @param $data
     * @param string $operation
     * @return bool|null
     */
    public static function sell($data, $operation = 'sell')
    {
        try {
            self::$company_id = $data['invoice']['company_id'];

            if ($data['invoice']['currency_id'] != 'RUB') {
                return false;
            }
            $rm = new crmAtolonlineReceiptModel();
            if ($operation == 'sell_refund' && empty($data['receipt'])) {
                return false;
            }
            $config = self::getConfig();

            if (empty($data['receipt']) || $operation == 'sell_refund') {
                $c = new waContact($data['invoice']['contact_id']);
                $receipt = array(
                    'transaction_id'  => isset($data['id']) && isset($data['transaction']['id'])
                        ? $data['transaction']['id'] : 0,
                    'invoice_id'      => $data['invoice']['id'],
                    'operation'       => $operation,
                    'receipt_data'    => '',
                    'customer_email'  => $c->get('email', 'default'),
                    'customer_phone'  => preg_replace('~^[7,8](\d{10})$~', '$1', $c->get('phone_confirmed', 'default')),
                    'amount'          => $data['invoice']['amount'],
                    'retry'           => 0,
                    'create_datetime' => date('Y-m-d H:i:s'),
                    'perefix_id'      => $config[self::$company_id.':external_id_prefix'],
                );
                $receipt['id'] = $rm->insert($receipt);

                $ipm = new crmInvoiceParamsModel();
                $ipm->replace(array(
                    'invoice_id' => $receipt['invoice_id'],
                    'name'       => 'receipt_id',
                    'value'      => $receipt['id'],
                ));
                if ($operation == 'sell_refund') {
                    $rm->updateById($data['receipt'], array('refund_id' => $receipt['id']));
                }
            } else {
                $receipt = $data['receipt'];
            }

            $cmd = $config[self::$company_id.':group_code'].'/'.$operation;

            if ($operation != 'sell_refund') {
                $params = array(
                    "external_id" => $config[self::$company_id.':external_id_prefix'].$receipt['id'],
                    "receipt"     => array(
                        "items"      => array(),
                        // v3 fields
                        "attributes" => array(
                            // "email" => $receipt['customer_email'],
                            // "phone" => $receipt['customer_phone'],
                            "sno" => $config[self::$company_id.':sno'],
                        ),
                        "payments"   => array(
                            array(
                                "sum"  => (float)$data['invoice']['amount'],
                                "type" => 1,
                            ),
                        ),
                        "total"      => (float)$data['invoice']['amount'],
                        // v4 fields
                        "client"     => array(
                            "email" => $receipt['customer_email'],
                            //"phone" => $receipt['customer_phone'],
                        ),
                        "company"     => array(
                            "email"           => $config[self::$company_id.':email'],
                            "sno"             => $config[self::$company_id.':sno'],
                            "inn"             => $config[self::$company_id.':inn'],
                            "payment_address" => $config[self::$company_id.':payment_address']
                        ),
                    ),
                    "service"     => array(
                        "callback_url"    => wa()->getRouteUrl('crm', array(
                            'plugin' => 'atolonline',
                            'module' => 'frontend',
                            'action' => 'atolonline',
                        ), true),
                        "inn"             => $config[self::$company_id.':inn'],
                        "payment_address" => $config[self::$company_id.':payment_address']
                    ),
                    "timestamp"   => date('d.m.Y H:i:s', strtotime($receipt['create_datetime'])),
                );

                $cnt = $total = 0;
                foreach ($data['invoice']['items'] as $i) {
                    if ($i['tax_percent'] && ($i['tax_type'] == 'APPEND' || !in_array(floatval($i['tax_percent']), crmAtolonlinePlugin::getAvailableTaxes()))) {
                        return null;
                    }
                    $tax_type = self::getTaxType($data['invoice'], $i, ifset($data['tax_type']));
                    $tax = self::getTax($data['invoice'], $i, $tax_type);
                    $item = array(
                        'name'     => $i['name'],
                        'price'    => (float)$i['price'],
                        'quantity' => (float)$i['quantity'],
                        'sum'      => $i['price'] * $i['quantity'],
                        'tax'      => $tax,
                        //"tax_sum"  => 0.00
                        // v4 fields
                        "payment_method" => $config[self::$company_id.':payment_method'],
                        "payment_object" => $config[self::$company_id.':payment_object'],
                        "measurement_unit" => 'Платеж',
                        "vat"      => array(
                            "type" => $tax,
                        ),
                    );
                    /* временно для теста
                    if ($tax_type == 'APPEND') {
                        if ((float)$i['quantity'] > 1) {
                            $item['name'] .= ' ('.(float)$i['quantity'].' шт.)';
                        }
                        $item['price'] = $item['sum'] = $item['sum'] + $item['sum'] * $data['invoice']['tax_percent'] / 100;
                        $item['quantity'] = 1;
                    }
                    */
                    $params['receipt']['items'][] = $item;
                    $total += $item['sum'];
                    $cnt++;
                }
                /* временно для теста
                if ($data['invoice']['tax_type'] == 'APPEND' && $total != $data['invoice']['amount']) {
                    $cnt--;
                    $params['receipt']['items'][$cnt]['price'] = $params['receipt']['items'][$cnt]['sum'] =
                        $data['invoice']['amount'] - $total + $params['receipt']['items'][$cnt]['sum'];
                }
                */
                if (!empty($receipt['customer_email'])) {
                    $params['receipt']['attributes']['email'] = $receipt['customer_email'];
                } elseif (!empty($receipt['customer_phone'])) {
                    $params['receipt']['attributes']['phone'] = $receipt['customer_phone'];
                } elseif (!empty($config[self::$company_id.':email'])) {
                    $params['receipt']['attributes']['email'] = $config[self::$company_id.':email'];
                } else {
                    $params['receipt']['attributes']['email'] = wa()->getSetting('email', null, 'webasyst');
                }
            } else {
                $params = (array)json_decode($data['receipt']['receipt_data'], true);
                $params['external_id'] = $config[self::$company_id.':external_id_prefix'].$receipt['id'];
            }

            $res = self::send($cmd, $params);

            $rm->updateById($receipt['id'], array('receipt_data' => json_encode($params, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE)));

            if (!empty($res['data']['uuid'])) {
                $upd = array(
                    'atol_uuid'      => $res['data']['uuid'],
                    'atol_timestamp' => date('Y-m-d H:i:s', strtotime($res['data']['timestamp'])),
                    'status'         => $res['data']['status'],
                    'error_id'       => ifset($res['data']['error']['id']),
                    'error_code'     => ifset($res['data']['error']['code']),
                    'error_type'     => ifset($res['data']['error']['type']),
                    'error_text'     => ifset($res['data']['error']['text']),
                );
                $rm->updateById($receipt['id'], $upd);
            }

            if ($res['status'] == 'ok') {
                return true;
            } elseif ($res['status'] == 'fail') {
                if ($operation == 'sell_refund') {
                    $rm->updateById($data['receipt'], array('refund_id' => null));
                }
            }
        } catch (waException $e) {
        }

        return false;
    }

    /**
     * @param array $data
     * @throws waException
     */
    public static function receipt($data)
    {
        if (empty($data['uuid'])) {
            throw new waException('UUID not found');
        }
        $rm = new crmAtolonlineReceiptModel();
        $receipt = $rm->getByField('atol_uuid', $data['uuid']);
        if (!$receipt) {
            throw new waException('Receipt not found');
        }
        $im = new crmInvoiceModel();
        if ($invoice = (array)$im->getbyId($receipt['invoice_id'])) {
            self::$company_id = $invoice['company_id'];
        }
        $config = self::getConfig();

        $upd = array(
            'status'           => ifset($data['status']),
            'error_id'         => ifset($data['error']['id']),
            'error_code'       => ifset($data['error']['code']),
            'error_type'       => ifset($data['error']['type']),
            'error_text'       => ifset($data['error']['text']),
            'atol_daemon_code' => ifset($data['daemon_code']),
            'atol_device_code' => ifset($data['device_code']),
        );
        if (!empty($data['payload'])) {
            $upd['receipt_datetime'] = !empty($data['payload']['receipt_datetime'])
                ? date('Y:m:d H:i:s', strtotime(ifset($data['payload']['receipt_datetime']))) : null;
            $upd['fiscal_receipt_number'] = ifset($data['payload']['fiscal_receipt_number']);
            $upd['fn_number'] = ifset($data['payload']['fn_number']);
            $upd['ecr_registration_number'] = ifset($data['payload']['ecr_registration_number']);
            $upd['fiscal_document_number'] = ifset($data['payload']['fiscal_document_number']);
            $upd['fiscal_document_attribute'] = ifset($data['payload']['fiscal_document_attribute']);
            $upd['fns_site'] = ifset($data['payload']['fns_site']);
            $upd['shift_number'] = ifset($data['payload']['shift_number']);
        }
        if ($upd['status'] != 'done') {
            $upd['retry'] = $receipt['retry'] + 1;
        }
        $rm->updateByField('atol_uuid', $data['uuid'], $upd);

        if ($upd['status'] == 'done' && $receipt['customer_email']) {
            // Send notification email
            $receipt = $upd + $receipt;
            $iim = new crmInvoiceItemsModel();
            $invoice['items'] = $iim->getItems($receipt['invoice_id']);

            $subject = 'Кассовый чек #'.$receipt['fiscal_document_number'];
            $view = wa()->getView();
            $view->assign(array(
                'config'     => $config,
                'receipt'    => $receipt,
                'invoice'    => $invoice,
                'company_id' => self::$company_id,
            ));
            $body = $view->fetch(wa('crm')->getAppPath('plugins/atolonline/templates/messages/atol_receipt_done.ru_RU.html'));

            try {
                $m = new waMailMessage($subject, $body);
                $m->setTo($receipt['customer_email']);
                $sent = (bool) $m->send();
            } catch (Exception $e) {
                $sent = false;
            }
            $msg = $sent
                ? '"Receipt done" notification was sent to'.$receipt['customer_email']
                : 'Error: "Receipt done" notification was NOT sent to'.$receipt['customer_email'];
            waLog::log($msg, 'crm/plugins/atolonline/email.log');
        }
    }

    /**
     * @param array $receipt
     * @return bool
     * @throws waException
     */
    public static function check($receipt)
    {
        $im = new crmInvoiceModel();
        if ($invoice = $im->getbyId($receipt['invoice_id'])) {
            self::$company_id = $invoice['company_id'];
        }
        $config = self::getConfig();

        $cmd = $config[self::$company_id.':group_code'].'/report/'.$receipt['atol_uuid'];

        $res = self::send($cmd);

        if ($res['status'] == 'ok') {
            self::receipt($res['data']);
            return isset($res['data']['status']) && $res['data']['status'] != 'fail';
        } else {
            $rm = new crmAtolonlineReceiptModel();
            $upd = array('retry' => $receipt['retry'] + 1);
            $rm->updateByField('atol_uuid', $receipt['atol_uuid'], $upd);
        }
        return false;
    }

    /**
     * @return mixed|null
     * @throws waException
     */
    public static function getConfig()
    {
        $config = wa('crm')->getPlugin('atolonline')->getSettings();
        if (!self::$api_version) {
            self::$api_version = !empty($config[self::$company_id.':api_version']) ? $config[self::$company_id.':api_version'] : 3;
        }
        if (!self::$debug_mode) {
            self::$debug_mode = !empty($config[self::$company_id.':debug_mode']) ? $config[self::$company_id.':debug_mode'] : 'off';
        }
        if (empty($config[self::$company_id.':payment_object'])) {
            $payment_object = crmAtolonlinePlugin::getPaymentObject();
            $payment_object = reset($payment_object);
            $config[self::$company_id.':payment_object'] = $payment_object['value'];
        }
        if (empty($config[self::$company_id.':payment_method'])) {
            $payment_method = crmAtolonlinePlugin::getPaymentMethod();
            $payment_method = reset($payment_method);
            $config[self::$company_id.':payment_method'] = $payment_method['value'];
        }
        return $config;
    }

    /**
     * @param string $cmd
     * @param array|null $params
     * @return array
     * @throws waException
     */
    public static function send($cmd, $params = null)
    {
        if (isset($params['company_id'])) {
            self::$company_id = intval($params['company_id']);
            unset($params['company_id']);
        }
        if (isset($params['api_version'])) {
            self::$api_version = $params['api_version'];
            unset($params['api_version']);
        }
        if (isset($params['debug_mode'])) {
            self::$debug_mode = $params['debug_mode'];
            unset($params['debug_mode']);
        }
        if ($params) {
            $params = json_encode($params, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
        }

        $response = self::sendCmd($cmd, $params);

        if (!self::$error) {
            $json = json_decode($response, true);

            $token_errors = array(4, 5, 6, 12, 13, 14, 22);

            if (
                $cmd != 'getToken' &&
                (empty($json['status']) || $json['status'] == 'fail') &&
                !empty($json['error']['code']) &&
                in_array($json['error']['code'], $token_errors)
            ) {
                self::getToken(true);
                $response = self::sendCmd($cmd, $params);
                $json = json_decode($response, true);
            }
        }
        if (!empty($json)) {
            return array('status' => 'ok', 'data' => $json);
        } else {
            self::log('Error sending command "'.$cmd.'": '.self::$error, $params, $response);
            return array('status' => 'error', 'error' => self::$error);
        }
    }

    /**
     * @param string $cmd
     * @param array|null $params
     * @return mixed|null
     * @throws waException
     */
    private static function sendCmd($cmd, $params)
    {
        $headers = array();
        if (self::$api_version && self::$api_version != 3) {
            $headers[] = 'Content-type: application/json; charset=utf-8';
        }
        if ($cmd != 'getToken') {
            if ($headers) {
                $headers[] = 'Token: '.self::getToken();
            } else {
                $cmd .= '?tokenid='.self::getToken();
            }
        }
        $url = self::getApiUrl().$cmd;

        self::$error = null;
        if (!($ch = curl_init())) {
            self::$error = 'curl init error';
        }
        if (curl_errno($ch) != 0) {
            self::$error = 'curl init error: '.curl_errno($ch);
        }

        $response = null;
        if (!self::$error) {

            set_time_limit(30);

            @curl_setopt($ch, CURLOPT_URL, $url);
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            if ($headers) {
                @curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            if ($params) {
                @curl_setopt($ch, CURLOPT_POST, 1);
                @curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            } else {
                @curl_setopt($ch, CURLOPT_POST, 0);
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            $response = @curl_exec($ch);

            if (curl_errno($ch) != 0) {
                self::$error = 'curl error: '.curl_errno($ch);
            }
            curl_close($ch);

            self::log('Sent cmd "'.$url.'", token "'.self::$token."'", $params, $response);
        }
        return $response;
    }

    /**
     * @param string $id
     * @param array|null $params
     * @param array|bool $response
     * @param string $file
     * @return void
     */
    public static function log($id, $params, $response, $file = 'send.log')
    {
        $msg = "$id\nparams: ".var_export($params, true)."\nresponse: ".var_export($response, true);
        waLog::log($msg, 'crm/plugins/atolonline/'.$file);
    }

    /**
     * @param array $invoice
     * @param array $item
     * @param string $tax_type
     * @return string
     */
    protected static function getTaxType($invoice, $item, $tax_type)
    {
        return $item['tax_type'] ? $item['tax_type'] : ($tax_type ? $tax_type : $invoice['tax_type']);
    }

    /**
     * @param array $invoice
     * @param array $item
     * @param string $tax_type
     * @return string
     */
    protected static function getTax($invoice, $item, $tax_type)
    {
        $tax = 'none';
        $tax_percent = $item['tax_percent'] ? $item['tax_percent'] : $invoice['tax_percent'];
        if ($tax_type != 'NONE' && $tax_type && $tax_percent == 0) {
            $tax = 'vat0';
        } elseif ($tax_type == 'INCLUDE' && $tax_percent == 10) {
            $tax = 'vat10';
        } elseif ($tax_type == 'INCLUDE' && $tax_percent == 18) {
            $tax = 'vat18';
        } elseif ($tax_type == 'INCLUDE' && $tax_percent == 20) {
            $tax = 'vat20';
        } elseif ($tax_type == 'APPEND' && $tax_percent == 10) {
            $tax = 'vat110';
        } elseif ($tax_type == 'APPEND' && $tax_percent == 18) {
            $tax = 'vat118';
        } elseif ($tax_type == 'APPEND' && $tax_percent == 20) {
            $tax = 'vat120';
        }
        return $tax;
    }

    /**
     * @return string
     */
    private static function getApiUrl()
    {
        $url = self::$debug_mode == 'on' ? 'testonline.atol.ru' : 'online.atol.ru';
        return self::$api_version == 3 ? "https://$url/possystem/v3/" : "https://$url/possystem/v4/";
    }
}
