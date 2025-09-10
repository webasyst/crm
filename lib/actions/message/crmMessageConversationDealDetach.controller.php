<?php

class crmMessageConversationDealDetachController extends crmJsonController
{
    public function execute()
    {
        $conversation = $this->getConversation();
        $this->detach($conversation);

        $this->response = [
            'html' => $this->renderDealSelector()
        ];
    }

    protected function detach($conversation)
    {
        $this->getConversationModel()->updateById($conversation['id'], [
            'deal_id' => null
        ]);

        $messages = $this->getConversationMessages($conversation);

        if ($messages) {

            // detach deals
            $message_ids = waUtils::getFieldValues($messages, 'id');
            $this->getMessageModel()->updateById($message_ids, [
                'deal_id' => null
            ]);

            // relink message crm log items
            $lm = $this->getLogModel();
            foreach ($messages as $message) {
                $lm->updateByField([
                    'object_type' => crmLogModel::OBJECT_TYPE_MESSAGE,
                    'object_id' => $message['id']
                ], [
                    'contact_id' => $message['contact_id']
                ]);
            }
        }
    }

    /**
     * Get message ids by conversation ID and it's deal
     * @param array $conversation
     * @return array
     * @throws waException
     */
    protected function getConversationMessages($conversation)
    {
        if ($conversation['deal_id'] <= 0) {
            return [];
        }
        return $this->getMessageModel()->getByField([
            'conversation_id' => $conversation['id'],
            'deal_id' => $conversation['deal_id']
        ], 'id');
    }

    /**
     * @return array
     * @throws waException
     * @throws waRightsException
     */
    protected function getConversation()
    {
        $conversation_id = waRequest::post('id', null, waRequest::TYPE_INT);
        $cm = new crmConversationModel();
        $conversation = $cm->getById($conversation_id);
        if (!$conversation) {
            $this->notFound();
        }

        if (!$this->getCrmRights()->canEditConversation($conversation)) {
            $this->accessDenied();
        }

        return $conversation;
    }

    protected function getCleanDealData()
    {
        // Just empty deal, for new message
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

        $stage_id = $this->getFunnelStageModel()->select('id')->where(
            'funnel_id = '.(int)$funnel['id']
        )->order('number')->limit(1)->fetchField('id');

        $deal = array_merge($deal, [
            'funnel_id'          => $funnel['id'],
            'stage_id'           => $stage_id,
        ]);

        $funnels = $this->getFunnelModel()->getAllFunnels(true);
        if (empty($funnels[$deal['funnel_id']])) {
            return [
                'deal' => $deal,
                'funnels' => [],
                'stages' => []
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
        $template = wa()->getAppPath('templates/actions/message/MessageConversation.dealSelector.inc.html', 'crm');
        return $this->renderTemplate($template, array_merge(
            $this->getCleanDealData(),
            [
                'show_save_button' => true
            ]
        ));
    }
}
