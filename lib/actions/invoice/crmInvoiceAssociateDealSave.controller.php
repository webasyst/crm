<?php

class crmInvoiceAssociateDealSaveController extends crmJsonController
{
    public function execute()
    {
        $invoice_id = waRequest::post('invoice_id', 0, waRequest::TYPE_INT);
        $deal = waRequest::post('deal', [], waRequest::TYPE_ARRAY_TRIM);
        $deal_id = (int) ifempty($deal, 'id', 0);

        if (!$invoice_id || $invoice_id < 0) {
            $this->errors = [_w('No invoice identifier')];
            return;
        }

        $invoice = $this->getInvoiceModel()->getById($invoice_id);
        if (empty($invoice)) {
            $this->errors = [_w('Invoice not found')];
            return;
        }

        if ($deal_id > 0) {
            $deal = $this->getDealModel()->getDeal($deal_id, false, true);
            if (!$deal) {
                $this->errors = [_w('Deal not found')];
                return;
            } elseif (!$this->getCrmRights()->deal($deal)) {
                $this->errors = [_w('Access to deal is denied.')];
                return;
            }

            // update invoice
            $this->getInvoiceModel()->updateById($invoice_id, ['deal_id' => $deal_id]);

            return;
        } elseif ($deal_id == 0 && intval($deal['funnel_id']) && intval($deal['stage_id']) && trim($deal['name'])) {
            // Funnel rights
            if (!$this->getCrmRights()->funnel($deal['funnel_id'])) {
                $this->errors = ['Access to a funnel is denied'];
                return;
            }

            // Create new deal
            $id = $this->getDealModel()->add([
                'contact_id'      => (int) $invoice['contact_id'],
                'status_id'       => crmDealModel::STATUS_OPEN,
                'name'            => trim($deal['name']),
                'funnel_id'       => (int) $deal['funnel_id'],
                'stage_id'        => (int) $deal['stage_id'],
                'user_contact_id' => wa()->getUser()->getId()
            ]);

            // update invoice
            $this->getInvoiceModel()->updateById($invoice_id, ['deal_id' => $id]);

            return;
        }

        $this->errors = [_w('Unknown error')];
    }
}
