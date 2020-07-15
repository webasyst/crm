<?php

/**
 * Class crmMessageOperationDeleteController
 *
 * Delete messages
 * Expected POST 'message_ids' - list of IDs
 * Response in json:
 *  {
 *      "status": "ok"
 *      "data": {
 *          "message_ids": [ ... ] - list of IDs
 *      }
 *  }
 */
class crmMessageOperationDeleteController extends crmMessageOperationController
{
    public function execute()
    {
        $message_ids = $this->getMessageIds();
        $message_ids = $this->getCrmRights()->dropUnallowedMessages($message_ids);

        if (!$message_ids) {
            return;
        }

        $this->getMessageModel()->delete($message_ids);
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
}
