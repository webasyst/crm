<?php

class crmMessageDealDetachController extends crmJsonController
{
    public function execute()
    {
        $message_id = waRequest::post("message_id", null, waRequest::TYPE_INT);
        if (empty($message_id)) {
            $this->errors = ['No message identifier'];
            return;
        }

        $message = $this->getMessageModel()->getById($message_id);

        if (empty($message)) {
            $this->errors = [_w('Message not found')];
            return;
        }
        
        $detach_type = waRequest::post('detach_type', 'conversation', waRequest::TYPE_STRING_TRIM);

        if ($detach_type == 'conversation') {
            $this->detachDealFromConversation($message);
        } else {
            $no_deal_conversation = $this->getConversationModel()->getByField([
                'source_id' => $message['source_id'],
                'contact_id' => $message['contact_id'],
                'deal_id' => [null, 0],
                'is_closed' => 0,
            ]);
            if (empty($no_deal_conversation)) {
                $this->extractNewConversation($message);
            } else {
                $this->bindToConversation($message, $no_deal_conversation['id']);
            }
        }
    }

    protected function detachDealFromConversation($message)
    {
        if (empty($message['conversation_id'])) {
            return false;
        }

        $conversation = $this->getConversationModel()->getById($message['conversation_id']);
        if (empty($conversation)) {
            return false;
        }

        $this->detach($conversation);
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

    protected function extractNewConversation($message)
    {
        $messages_in_conversation = $this->getMessageModel()->countByField(['conversation_id' => $message['conversation_id']]);
        if ($messages_in_conversation <= 1) {
            return $this->detachDealFromConversation($message);
        }
        
        $summary = ifset($message, 'subject', '');

        $data = [
            'source_id' => $message['source_id'],
            'contact_id' => $message['contact_id'],
            'user_contact_id' => wa()->getUser()->getId(),
            'summary' => $summary,
        ];

        $conversation_id = $this->getConversationModel()->add($data, $message['transport']);
        $this->getMessageModel()->updateById($message['id'], [
            'conversation_id' => $conversation_id,
            'deal_id' => null,
        ]);

        return $conversation_id;
    }

    protected function bindToConversation($message, $conversation)
    {
        $this->getMessageModel()->updateById($message['id'], [
            'conversation_id' => $conversation['id'],
            'deal_id' => $conversation['deal_id'],
        ]);

        $conversation_update = [ 'count' => '+1' ];
        if ($conversation['last_message_id'] < $message['id']) {
            $conversation_update['last_message_id'] = $message['id'];
            $conversation_update['update_datetime'] = $message['create_datetime'];
            if ($message['direction'] === crmMessageModel::DIRECTION_IN) {
                $conversation_update['summary'] = ifset($message, 'subject', '');
            }
        }
        $this->getConversationModel()->updateById($conversation['id'], $conversation_update);
    }
}
