<?php

class crmMessageWriteDealDialogAction extends crmSendEmailDialogAction
{
    public function execute()
    {
        $deal = $this->getDeal();
        $participants_ids = $this->getParticipantsIds($deal['participants']);

        $funnels = $this->getFunnelModel()->getAllFunnels(true);
        if (empty($funnels[$deal['funnel_id']])) {
            $funnel = reset($funnels);
            $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnel);
        } else {
            $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnels[$deal['funnel_id']]);
        }

        $this->view->assign(array(
            'deal'            => $deal,
            'stages'          => $stages,
            'funnels'         => $funnels,
            'participants'    => $this->getParticipantsData($participants_ids),
            'files'           => $deal['files'],
            'hidden_params'   => array(
                'deal_id' => $deal['id']
            ),
            'send_action_url' => wa()->getAppUrl('crm') . '?module=message&action=sendDeal',
            'action'          => self::ACT_DEAL_MESSAGE,
        ));
    }

    protected function getDeal()
    {
        $id = (int)$this->getParameter('deal_id');
        if ($id <= 0) {
            $this->notFound(_w('Deal not found'));
        }
        $deal = $this->getDealModel()->getDeal($id, true);
        if (!$deal) {
            $this->notFound(_w('Deal not found'));
        }
        if (!$this->getCrmRights()->deal($deal['id'])) {
            $this->accessDenied();
        }
        return $deal;
    }

    /**
     * @return crmContact
     */
    protected function getRecipientContact()
    {
        if ($this->contact !== null) {
            return $this->contact;
        }
        $id = (int)$this->getParameter('contact_id');
        return $this->contact = new crmContact($id);
    }
}
