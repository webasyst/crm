<?php

/**
 * Class crmMessageOperationMarkController
 */
abstract class crmMessageOperationMarkController extends crmMessageOperationController
{
    /**
     * Get message ids and conversation all together with checking rights
     * @return array: <message_ids>, <conversation_ids>
     * @throws waException
     */
    protected function getData()
    {
        $message_ids = $this->getMessageIds();
        if ($message_ids) {
            // drop unallowed messages
            $message_ids = $this->getCrmRights()->dropUnallowedMessages($message_ids);
            return [$message_ids, []];
        }

        $conversation_ids = $this->getConversationIds();

        // mark conversation as read/unread not edit access type, so NOT pass 'access_type' => 'edit'
        $conversation_ids = $this->getCrmRights()->dropUnallowedConversations($conversation_ids);

        $message_ids = $this->getLastMessageIds($conversation_ids);

        // not extra check rights for messages, cause already checked for conversations

        return [$message_ids, $conversation_ids];
    }

    /**
     * Ensure format of response
     */
    public function afterExecute()
    {
        // default "empty" response
        $empty = [
            'message_ids'      => [],
            'conversation_ids' => [],
        ];
        $this->response = array_merge($empty, $this->response);
    }
}
