<?php

/**
 * Class crmMessageOperationAssociateDealWithConversationsController
 *
 * Associate conversations with deals
 * Expected POST
 *      - int[] 'conversation_ids' - list of IDs
 *      - int   'deal_id' - Deal ID
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
 */
class crmMessageOperationAssociateDealWithConversationsController extends crmMessageOperationController
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

        $result = $this->getDealInfo();
        if (!$result['deal']) {
            $this->errors[] = $result['error'];
            return;
        }
        $deal = $result['deal'];

        if ($this->isCheckMode()) {
            $this->response = [
                'conversation_ids' => $conversation_ids,
                'text' => $this->getCheckText(count($conversation_ids), count($this->getConversationIds()))
            ];
            return;
        }

        $this->associateWithDeal($conversation_ids, $deal);

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
     * @return array $result
     *      - array|null $result['deal']
     *      - null|string $result['error']
     * @throws waException
     */
    protected function getDealInfo()
    {
        $id = $this->getDealId();
        $deal = $this->getDealModel()->getDeal($id, false, true);
        if (!$deal) {
            return [
                'deal' => null,
                'error' => _w('Deal not found')
            ];
        }
        if (!$this->getCrmRights()->deal($deal)) {
            return [
                'deal' => null,
                'error' => _w('Access to deal is denied.')
            ];
        }
        return array(
            'deal' => $deal,
            'error' => null
        );
    }

    /**
     * @return int
     */
    protected function getDealId()
    {
        return $this->getRequest()->post('deal_id', 0, waRequest::TYPE_INT);
    }

    /**
     * Associate conversations with deal
     * @param int[] $conversation_ids
     * @param array $deal - db record
     * @throws waException
     */
    protected function associateWithDeal($conversation_ids, $deal)
    {
        $cm = $this->getConversationModel();

        if ($deal['user_contact_id']) {
            $cm->updateByField([
                'id' => $conversation_ids,
                'user_contact_id' => 0
            ], [
                'user_contact_id' => $deal['user_contact_id']
            ]);
        }

        // Update conversation
        $cm->updateById($conversation_ids, ['deal_id' => $deal['id']]);

        // message of this conversation
        $message_ids = array_keys($this->getMessageModel()->getByField(['conversation_id' => $conversation_ids], 'id'));
        $message_ids = waUtils::toIntArray($message_ids);
        $message_ids = waUtils::dropNotPositive($message_ids);

        if ($message_ids) {

            // Update all messages from this conversation
            $this->getMessageModel()->updateById($message_ids, ['deal_id' => $deal['id']]);

            // relink log items
            $this->getLogModel()->updateByField(
                [
                    'object_id' => $message_ids,
                    'object_type' => crmLogModel::OBJECT_TYPE_MESSAGE
                ],
                [
                    'contact_id' => -$deal['id'],
                ]
            );
        }
    }
}
