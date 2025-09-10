<?php

class crmMessageConversationDealAttachDialogAction extends crmViewAction
{
    public function execute()
    {
        $this->checkConversationAccess();
        $this->view->assign($this->getCleanDealData());
    }

    /**
     * @throws waException
     * @throws waRightsException
     */
    protected function checkConversationAccess()
    {
        $conversation = null;
        $id = (int)$this->getRequest()->request('id');
        if ($id > 0) {
            $cm = new crmConversationModel();
            $conversation = $cm->getById($id);
        }
        if (!$conversation) {
            $this->notFound();
        }
        if (!$this->getCrmRights()->canEditConversation($conversation)) {
            $this->accessDenied();
        }
    }

    protected function getCleanDealData()
    {
        // Just empty deal, for new message
        $deal = $this->getDealModel()->getEmptyDeal();
        $now = date('Y-m-d H:i:s');
        $deal = array_merge($deal, array(
            'creator_contact_id' => wa()->getUser()->getId(),
            'create_datetime'    => $now,
            'update_datetime'    => $now,
        ));

        $funnel = $this->getFunnelModel()->getAvailableFunnel();
        if (!$funnel) {
            return array(
                'deal' => $deal,
                'funnels' => array(),
                'stages' => array()
            );
        }

        $stage_id = $this->getFunnelStageModel()->select('id')->where(
            'funnel_id = '.(int)$funnel['id']
        )->order('number')->limit(1)->fetchField('id');

        $deal = array_merge($deal, array(
            'funnel_id'          => $funnel['id'],
            'stage_id'           => $stage_id,
        ));

        $funnels = $this->getFunnelModel()->getAllFunnels(true);
        if (empty($funnels[$deal['funnel_id']])) {
            return array(
                'deal' => $deal,
                'funnels' => array(),
                'stages' => array()
            );
        }

        $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnels[$deal['funnel_id']]);

        return array(
            'deal'    => $deal,
            'funnels' => $funnels,
            'stages'  => $stages,
        );
    }
}
