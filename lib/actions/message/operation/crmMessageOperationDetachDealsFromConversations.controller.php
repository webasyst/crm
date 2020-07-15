<?php

/**
 * Class crmMessageOperationDetachDealsFromConversationsController
 *
 * Detach deals from conversations
 * Expected POST
 *      - int[] 'conversation_ids' - list of IDs
 *      - bool  'check' - if not empty passed we not actually do action, just check rights and returns back sieved by rights list of IDs
 *
 * Response in json:
 *  {
 *      "status": "ok"
 *      "data": {
 *          "conversation_ids": [ ... ] - list of IDs
 *          "text": - text about how much conversations be affected by the action due to insufficient access rights. \
 *              Make sense only in 'check' mode. Default value is empty string
 *      }
 *  }
 *
 *
 */
class crmMessageOperationDetachDealsFromConversationsController extends crmMessageOperationController
{
    public function execute()
    {
        $conversation_ids = $this->getConversationIds();
        $conversation_ids = $this->getCrmRights()->dropUnallowedConversations($conversation_ids, [
            'access_type' => 'edit'
        ]);

        if (!$conversation_ids) {
            return;
        }

        if ($this->isCheckMode()) {
            $this->response = [
                'conversation_ids' => $conversation_ids,
                'text' => $this->getCheckText(count($conversation_ids), count($this->getConversationIds()))
            ];
            return;
        }

        $this->detachDeals($conversation_ids);

        $this->response = [
            'conversation_ids' => $conversation_ids
        ];
    }

    /**
     * Ensure format of response
     */
    public function afterExecute()
    {
        // default "empty" response
        $empty = [
            'conversation_ids' => [],
            'text' => '',
        ];
        $this->response = array_merge($empty, $this->response);
    }

    /**
     * Detach conversations from deals
     * @param int[] $conversation_ids
     * @throws waException
     */
    protected function detachDeals($conversation_ids)
    {
        $conversations = $this->getConversationModel()->getById($conversation_ids);
        $deal_ids = waUtils::getFieldValues($conversations, 'deal_id');
        $deal_ids = waUtils::toIntArray($deal_ids);
        $deal_ids = waUtils::dropNotPositive($deal_ids);

        $this->getConversationModel()->updateById($conversation_ids, [
            'deal_id' => null
        ]);

        $messages = $this->getMessageModel()->getByField([
            'conversation_id' => $conversation_ids,
            'deal_id' => $deal_ids
        ], true);

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
}
