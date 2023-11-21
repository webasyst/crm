<?php

class crmInvoiceDealAttachDialogAction extends crmViewAction
{
    public function execute()
    {
        $invoice_id = $this->getRequest()->request('invoice_id', 0, waRequest::TYPE_INT);
        $deal_id = $this->getRequest()->request('deal_id', 0, waRequest::TYPE_INT);

        $funnels = [];
        $stages  = [];

        $this->checkDealAccess($deal_id);

        // Just empty deal, for new invoice
        $deal = $this->getDealModel()->getEmptyDeal();
        $now  = date('Y-m-d H:i:s');
        $deal = array_merge($deal, [
            'creator_contact_id' => wa()->getUser()->getId(),
            'create_datetime'    => $now,
            'update_datetime'    => $now
        ]);

        $funnel = $this->getFunnelModel()->getAvailableFunnel();
        if ($funnel) {
            $stage_id = $this->getFunnelStageModel()
                ->select('id')
                ->where('funnel_id = ?', (int) $funnel['id'])
                ->order('number')
                ->limit(1)
                ->fetchField('id');

            $deal = array_merge($deal, [
                'funnel_id' => $funnel['id'],
                'stage_id'  => $stage_id,
            ]);

            $funnels = $this->getFunnelModel()->getAllFunnels();
            if (!empty($funnels[$deal['funnel_id']])) {
                $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnels[$deal['funnel_id']]);
            }
        }

        $this->view->assign([
            'invoice_id' => $invoice_id,
            'deal'       => $deal,
            'funnels'    => $funnels,
            'stages'     => $stages
        ]);
    }

    /**
     * @return void
     * @throws crmAccessDeniedException
     * @throws crmNotFoundException
     * @throws waDbException
     * @throws waException
     */
    protected function checkDealAccess($deal_id)
    {
        $deal = null;
        if (empty($deal_id)) {
            return;
        } elseif ($deal_id > 0) {
            $deal = $this->getDealModel()->getById($deal_id);
        }
        if (!$deal) {
            $this->notFound(_w('Deal not found'));
        } elseif (!$this->getCrmRights()->deal($deal)) {
            $this->accessDenied();
        }
    }
}
