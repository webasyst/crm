<?php

/**
 * Class crmMessageOperationDetachDealsController
 *
 * Detach deals from messages
 * Expected POST
 *  'message_ids' - list of IDs
 *
 * Response in json:
 *  {
 *      "status": "ok"
 *      "data": {
 *          "message_ids": [ ... ] - list of IDs
 *      }
 *  }
 */
class crmMessageOperationDetachDealsController extends crmMessageOperationController
{
    public function execute()
    {
        $message_ids = $this->getMessageIds();
        $message_ids = $this->getCrmRights()->dropUnallowedMessages($message_ids);

        if (!$message_ids) {
            return;
        }

        $this->detachDeals($message_ids);

        $this->response = [
            'message_ids' => $message_ids
        ];
    }

    /**
     * Ensure format of response
     */
    public function afterExecute()
    {
        // default "empty" response
        $empty = [
            'message_ids' => [],
        ];
        $this->response = array_merge($empty, $this->response);
    }

    /**
     * Detach messages from deals
     * @param int[] $message_ids
     */
    protected function detachDeals($message_ids)
    {
        $messages = $this->getMessageModel()->getById($message_ids);

        if (!$messages) {
            return;
        }

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
