<?php

class crmFrontendInvoicePaymentStatusController extends crmFrontendInvoicePaymentBaseController
{
    public function execute()
    {
        $response = $this->executeBackgroundCheck();
        $this->sendJson($response);
    }

    protected function executeBackgroundCheck()
    {
        $invoice = $this->loadInvoice();
        if (empty($invoice)) {
            return ['is_paid' => false, 'error' => true];
        }

        if ($invoice['state_id'] == crmInvoiceModel::STATE_PENDING) {
            try {
                $methods = $this->loadMethods($invoice);
                $plugin = null;
                foreach ($methods as $m) {
                    $plugin = $m['instance'] ?? crmPayment::getPlugin($m['plugin'], $m['company_id']);
                    if (crmPayment::pluginSupportsQRCode($plugin)) {
                        break;
                    }
                }
                crmPayment::statePolling($invoice, $plugin);

                // Reload invoice, to check status after polling
                $invoice = $this->getInvoiceModel()->getById($invoice['id']);
            } catch (Throwable $e) {
                waLog::log($e->getMessage(), 'crm/checkout.error.log');
                waLog::log($e->getTraceAsString(), 'crm/checkout.error.log');
            }
        }
        
        return [
            'is_paid' => in_array($invoice['state_id'], [
                crmInvoiceModel::STATE_PAID, 
                crmInvoiceModel::STATE_PROCESSING
            ]),
            'order_id' => $invoice['id'],
        ];
    }

}
