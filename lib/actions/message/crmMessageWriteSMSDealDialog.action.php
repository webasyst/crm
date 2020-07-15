<?php

/**
 * Class crmMessageWriteSMSDealDialogAction
 *
 * Dialog to write SMS from deal context (deal page)
 */
class crmMessageWriteSMSDealDialogAction extends crmSendSMSDialogAction
{
    public function execute()
    {
        $deal = $this->getDeal();

        $funnels = $this->getFunnelModel()->getAllFunnels();
        $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnels[$deal['funnel_id']]);

        $this->view->assign(array(
            'deal'            => $deal,
            'stages'          => $stages,
            'funnels'         => $funnels,
            'contact'         => $this->getContact()
        ));
    }

    protected function getDeal()
    {
        $id = (int)$this->getParameter('deal_id');
        if ($id <= 0) {
            $this->notFound(_w('Deal not found'));
        }
        $deal = $this->getDealModel()->getDeal($id);
        if (!$deal) {
            $this->notFound(_w('Deal not found'));
        }
        if (!$this->getCrmRights()->deal($deal['id'])) {
            $this->accessDenied();
        }
        return $deal;
    }

    public function getSendActionUrl()
    {
        return wa()->getAppUrl('crm') . '?module=message&action=sendSMSDeal';
    }
}
