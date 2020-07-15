<?php

/**
 * Class crmMessageOperationDeleteConversationsController
 *
 * Delete conversations
 * Expected POST
 *      - int[] 'conversation_ids'  - list of IDs
 *      - bool  'check'             - if not empty passed we not actually do action, just check rights and returns back sieved by rights list of IDs
 * Response in json:
 *  {
 *      "status": "ok"
 *      "data": {
 *          "conversation_ids": [ ... ] - list of IDs
 *          "text": - text about how much conversations be affected by the action due to insufficient access rights. \
 *              Make sense only in 'check' mode. Default value is empty string
 *      }
 *  }
 */
class crmMessageOperationDeleteConversationsController extends crmMessageOperationController
{
    public function execute()
    {
        $conversation_ids = $this->getConversationIds();
        $conversation_ids = $this->getCrmRights()->dropUnallowedConversations($conversation_ids, array(
            'access_type' => 'edit'
        ));

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


        $this->getConversationModel()->delete($conversation_ids);

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
}
