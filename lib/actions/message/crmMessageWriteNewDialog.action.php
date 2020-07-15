<?php

class crmMessageWriteNewDialogAction extends crmSendEmailDialogAction
{
    public function execute()
    {
        $dm = new crmDealModel();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        $funnel = $fm->getAvailableFunnel();
        if (!$funnel) {
            throw new waRightsException();
        }
        $stage_id = $fsm->select('id')->where(
            'funnel_id = '.(int)$funnel['id']
        )->order('number')->limit(1)->fetchField('id');

        // Just empty deal, for new message
        $now = date('Y-m-d H:i:s');
        $deal = $dm->getEmptyDeal();
        $deal = array_merge($deal, array(
            'creator_contact_id' => wa()->getUser()->getId(),
            'create_datetime'    => $now,
            'update_datetime'    => $now,
            'funnel_id'          => $funnel['id'],
            'stage_id'           => $stage_id,
        ));

        $funnels = $fm->getAllFunnels();
        if (empty($funnels[$deal['funnel_id']])) {
            throw new waException('Funnel not found');
        }
        $stages = $fsm->getStagesByFunnel($funnels[$deal['funnel_id']]);

        $this->view->assign(array(
            'deal'            => $deal,
            'stages'          => $stages,
            'funnels'         => $funnels,
            'send_action_url' => wa()->getAppUrl('crm') . '?module=message&action=sendNew',
            'action'          => self::ACT_NEW_MESSAGE,
        ));
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
