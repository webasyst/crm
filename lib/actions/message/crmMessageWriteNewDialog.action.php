<?php

class crmMessageWriteNewDialogAction extends crmSendEmailDialogAction
{
    protected $deal = null;
    public function execute()
    {
        $iframe = waRequest::request('iframe', 0, waRequest::TYPE_INT);
        if (!empty($iframe) && wa('crm')->whichUI('crm') !== '1.3') {
            $this->setLayout();
        }

        $dm = new crmDealModel();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        $deal = [];
        if ($this->deal) {
            $deal = $this->deal;
        } else {
            $funnel = $fm->getAvailableFunnel();
            if (!$funnel) {
                throw new waRightsException();
            }
            $stage_id = $fsm->select('id')
                ->where('funnel_id = ?', (int) $funnel['id'])
                ->order('number')
                ->limit(1)
                ->fetchField('id');

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
        }

        $funnels = $fm->getAllFunnels(true);
        if (empty($funnels[$deal['funnel_id']])) {
            throw new waException('Funnel not found');
        }
        $stages = $fsm->getStagesByFunnel($funnels[$deal['funnel_id']]);

        $this->view->assign(array(
            'iframe'          => $iframe,
            'recipients'      => [],
            'participants'    => [],
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
        $deal_id = (int)$this->getParameter('deal_id');
        if ($deal_id > 0) {
            $this->deal = $this->obtainDeal($deal_id);
            if (empty($id) && !empty($this->deal['contact_id'])) {
                $id = $this->deal['contact_id'];
            }
        }
        return $this->contact = new crmContact($id);
    }
}
