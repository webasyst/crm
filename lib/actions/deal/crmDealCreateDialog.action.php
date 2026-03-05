<?php
/**
 * Dialog to create a deal from shop order. Shows in Shop app on Order page.
 */
class crmDealCreateDialogAction extends crmViewAction
{
    public function execute()
    {
        $funnels = $this->getFunnels();
        if (!$funnels) {
            return;
        }
        $dm = new crmDealModel();

        if (waRequest::get('ui', wa()->whichUI('crm')) === '1.3') {
            $this->setTemplate('templates/actions-legacy/deal/DealCreateDialog.html');
        }

        $this->view->assign(array(
            'order_id'   => waRequest::request('order_id'),
            'funnels'    => $funnels,
            'open_deals' => $dm->getOpenDeals(waRequest::request('contact_id')),
        ));
    }

    protected function getFunnels()
    {
        $funnel_model = new crmFunnelModel();
        $funnels = $funnel_model->getAllFunnels();

        $fsm = new crmFunnelStageModel();

        foreach ($funnels as &$f) {
            $f['stages'] = array_values($fsm->getStagesByFunnel($f));
        }
        unset($f);

        return $funnels;
    }
}
