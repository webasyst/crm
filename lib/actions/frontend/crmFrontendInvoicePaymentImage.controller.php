<?php

class crmFrontendInvoicePaymentImageController extends crmFrontendInvoicePaymentBaseController
{
    public function execute()
    {
        $response = $this->loadPaymentImage();
        $this->sendJson($response);
    }

    protected function loadPaymentImage()
    {
        $invoice = $this->loadInvoice();
        if (empty($invoice) || $invoice['state_id'] != crmInvoiceModel::STATE_PENDING) {
            return ['error' => true];
        }

        try {
            $methods = $this->loadMethods($invoice);
            $payment_image = crmPayment::getPaymentImage($methods, $invoice);
            if (!$payment_image) {
                return ['error' => true];
            }

            return [
                'payment_image' => $payment_image,
                'status_check_url' => wa()->getRouteUrl('crm/frontend/invoicePaymentStatus', [
                    'hash' => $this->getRequestHash()
                ]),
            ];
        } catch (Throwable $e) {
            waLog::log($e->getMessage(), 'crm/checkout.error.log');
            waLog::log($e->getTraceAsString(), 'crm/checkout.error.log');
            return ['error' => true];
        }
    }

}
