<?php

class crmInvoiceDealDetachController extends crmJsonController
{
    public function execute()
    {
        $invoice_id = $this->getRequest()->post('invoice_id', 0, waRequest::TYPE_INT);

        if ($invoice_id < 1) {
            $this->notFound();
        }

        $invoice = $this->getInvoiceModel()->getById($invoice_id);
        if (!$invoice) {
            $this->notFound();
        }

        $this->getInvoiceModel()->updateById(
            $invoice_id,
            ['deal_id' => '0']
        );

        $this->response = [
            'html' => $this->renderDealSelector()
        ];
    }

    protected function getCleanDealData()
    {
        // Just empty deal, for new invoice
        $deal = $this->getDealModel()->getEmptyDeal();
        $now = date('Y-m-d H:i:s');
        $deal = array_merge($deal, [
            'creator_contact_id' => wa()->getUser()->getId(),
            'create_datetime'    => $now,
            'update_datetime'    => $now,
        ]);

        $funnel = $this->getFunnelModel()->getAvailableFunnel();
        if (!$funnel) {
            return [
                'deal' => $deal,
                'funnels' => [],
                'stages' => []
            ];
        }

        $stage_id = $this->getFunnelStageModel()
            ->select('id')
            ->where('funnel_id = ?', (int) $funnel['id'])
            ->order('number')
            ->limit(1)
            ->fetchField('id');

        $deal = array_merge($deal, [
            'funnel_id' => $funnel['id'],
            'stage_id'  => $stage_id
        ]);

        $funnels = $this->getFunnelModel()->getAllFunnels();
        if (empty($funnels[$deal['funnel_id']])) {
            return [
                'deal'    => $deal,
                'funnels' => [],
                'stages'  => []
            ];
        }

        $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnels[$deal['funnel_id']]);

        return [
            'deal'    => $deal,
            'funnels' => $funnels,
            'stages'  => $stages,
        ];
    }

    protected function renderDealSelector()
    {
        $actions_path = wa('crm')->whichUI('crm') === '1.3' ? 'actions-legacy' : 'actions';
        $template = wa()->getAppPath("templates/$actions_path/message/MessageConversation.dealSelector.inc.html", 'crm');
        return $this->renderTemplate($template, array_merge(
            $this->getCleanDealData(),
            ['show_save_button' => true]
        ));
    }
}
