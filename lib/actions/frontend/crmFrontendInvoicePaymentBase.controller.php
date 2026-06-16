<?php

abstract class crmFrontendInvoicePaymentBaseController extends waController
{
    protected $invoice_model;

    protected function getRequestHash()
    {
        return waRequest::param(
            'hash',
            waRequest::post('hash', waRequest::get('hash', null, waRequest::TYPE_STRING_TRIM), waRequest::TYPE_STRING_TRIM),
            waRequest::TYPE_STRING_TRIM
        );
    }

    protected function loadInvoice()
    {
        $hash = $this->getRequestHash();
        if (!$hash) {
            return null;
        }

        $invoice_id = intval(substr($hash, 16, -16));
        if (!$invoice_id) {
            return null;
        }

        $invoice = $this->getInvoiceModel()->getInvoice($invoice_id);
        if (!$invoice || $hash !== crmHelper::getInvoiceHash($invoice)) {
            return null;
        }

        return $invoice;
    }

    protected function loadMethods(array $invoice)
    {
        $currency_model = new crmCurrencyModel();
        $payment_model = new crmPaymentModel();
        $currencies = $currency_model->getAll('code');
        $methods = $payment_model->select('*')->where(
            'status=1 AND company_id='.(int)$invoice['company_id']
        )->order('sort')->fetchAll('id');

        $allowed_methods = [];
        foreach ($methods as $key => $m) {
            try {
                $plugin = crmPayment::getPlugin($m['plugin'], $m['company_id']);
                $allowed_currencies = $plugin->allowedCurrency();
                if ($allowed_currencies !== true) {
                    $allowed_currencies = (array)$allowed_currencies;
                    if (!array_intersect($allowed_currencies, array_keys($currencies))) {
                        continue;
                    }
                    if (!in_array($invoice['currency_id'], $allowed_currencies)) {
                        continue;
                    }
                }
                $m['instance'] = $plugin;
                $allowed_methods[$key] = $m;
            } catch (waException $e) {
            }
        }

        return $allowed_methods;
    }

    protected function sendJson(array $payload)
    {
        $this->getResponse()->addHeader('Content-Type', 'application/json');
        $this->getResponse()->sendHeaders();
        echo waUtils::jsonEncode($payload);
    }

    protected function getInvoiceModel()
    {
        if (!$this->invoice_model) {
            $this->invoice_model = new crmInvoiceModel();
        }
        return $this->invoice_model;
    }
}
