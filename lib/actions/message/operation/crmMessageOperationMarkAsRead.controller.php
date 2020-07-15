<?php

/**
 * Class crmMessageOperationMarkAsReadController
 *
 * Controller for mass marking as read list of messages/conversations
 * If controller is dealing with conversations mark last conversations messages
 *
 * If client requested marking for messages it must POST 'message_ids' list of message ids
 * If client requested marking for conversation it must POST 'conversation_ids' list of conversation ids
 */
class crmMessageOperationMarkAsReadController extends crmMessageOperationMarkController
{
    public function execute()
    {
        // get prepared and type-cast data from request
        list($message_ids, $conversation_ids) = $this->getData();

        // no messages, nothing to do
        if (!$message_ids) {
            return;
        }

        // mark messages
        $marked_message_ids = $this->markMessagesAsRead($message_ids, $this->getUserId());

        $this->response = [
            'message_ids' => $marked_message_ids,
            'conversation_ids' => $conversation_ids,
        ];
    }

    /**
     * @param int[] $message_ids
     * @param int $contact_id
     * @return int[]
     * @throws waException
     */
    protected function markMessagesAsRead($message_ids, $contact_id)
    {
        if (!wa_is_int($contact_id) || $contact_id <= 0) {
            return [];
        }
        $mrm = $this->getMessageReadModel();
        $mrm->setRead($message_ids, $contact_id);

        $statuses = $mrm->getReadStatus($message_ids, $contact_id, true);
        $statuses = array_filter($statuses, function ($status, $_) {
            return (bool)$status;
        }, ARRAY_FILTER_USE_BOTH);

        return array_keys($statuses);
    }
}
