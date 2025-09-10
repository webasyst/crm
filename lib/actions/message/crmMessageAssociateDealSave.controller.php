<?php

class crmMessageAssociateDealSaveController extends crmJsonController
{
    public function execute()
    {
        $message_id = waRequest::post('message_id', 0, waRequest::TYPE_INT);
        if (!$message_id) {
            $this->errors = array('No message identifier');
            return;
        }

        $message = $this->getMessageModel()->getMessage($message_id);

        if (empty($message)) {
            $this->errors = array(_w('Message not found'));
            return;
        }

        $contact = new crmContact($message['contact_id']);
        if (empty($contact) || !$contact->exists()) {
            $this->errors = array(_w('Contact not found'));
            return;
        }
        if (!$this->getCrmRights()->contact($contact)) {
            $this->errors = array('Access to the contact is denied.');
            return;
        }

        $deal_data = waRequest::post('deal', null, waRequest::TYPE_ARRAY_TRIM);

        if (empty($deal_data)) {
            $this->errors = array(_w('No data on the deal.'));
            return;
        }

        if (ifset($deal_data['id']) == 'none') {
            $this->errors = array('Deal no required');
            return;
        }

        $bind_type = waRequest::post('bind_type', 'conversation', waRequest::TYPE_STRING_TRIM);

        $deal_data['id'] = (int)ifset($deal_data['id']);

        if ($deal_data['id'] > 0) {
            $deal = $this->getDealModel()->getDeal($deal_data['id'], false, true);
            if (!$deal) {
                $this->errors = array(_w('Deal not found'));
                return;
            }
            if (!$this->getCrmRights()->deal($deal)) {
                $this->errors = array(_w('Access to deal is denied.'));
                return;
            }

            // update message
            $this->getMessageModel()->updateById($message['id'], array('deal_id' => $deal['id']));
            // update log
            $this->getLogModel()->updateByField(
                array(
                    'object_id' => $message['id'],
                    'object_type' => crmLogModel::OBJECT_TYPE_MESSAGE
                ),
                array(
                    'contact_id' => -$deal['id'],
                )
            );

            if ($bind_type == 'conversation') {
                $this->addDealToConversation($message, $deal['id']);
            } else {
                $deal_conversation = $this->getConversationModel()->getByField([
                    'source_id' => $message['source_id'],
                    'contact_id' => $message['contact_id'],
                    'deal_id' => $deal['id'],
                    'is_closed' => 0,
                ]);
                if (empty($deal_conversation)) {
                    $this->extractNewConversation($message, $deal['id']);
                } else {
                    $this->bindToConversation($message, $deal_conversation['id']);
                }
            }

            $this->response = array(
                'deal_url' => wa()->getAppUrl('crm')."deal/{$deal['id']}",
            );
            return;
        }

        if ($deal_data['id'] == 0 && intval($deal_data['funnel_id']) && intval($deal_data['stage_id']) && trim($deal_data['name'])) {
            // Funnel rights
            if (!$this->getCrmRights()->funnel($deal_data['funnel_id'])) {
                $this->errors = array(_w('Access denied'));
                return;
            }

            // Create new deal
            $id = $this->getDealModel()->add(array(
                'contact_id'      => (int)$contact['id'],
                'status_id'       => 'OPEN',
                'name'            => trim($deal_data['name']),
                'description'     => ifempty($message['body']),
                'funnel_id'       => (int)$deal_data['funnel_id'],
                'stage_id'        => (int)$deal_data['stage_id'],
                'user_contact_id' => wa()->getUser()->getId(),
            ));
            // update call
            $this->getMessageModel()->updateById($message['id'], array('deal_id' => $id));
            // update log
            $this->getLogModel()->updateByField(
                array(
                    'object_id' => $message['id'],
                    'object_type' => crmLogModel::OBJECT_TYPE_MESSAGE
                ),
                array(
                    'contact_id' => -$id,
                )
            );

            if ($bind_type == 'conversation') {
                $this->addDealToConversation($message, $id);
            } else {
                $this->extractNewConversation($message, $id);
            }

            $this->response = array(
                'deal_url' => wa()->getAppUrl('crm')."deal/{$id}",
            );
            return;
        }

        $this->errors = array('Unknown error');
    }

    /**
     * @param array $message
     * @param int $deal_id
     * @return bool
     */
    protected function addDealToConversation($message, $deal_id)
    {
        if (empty($message['conversation_id']) || !$deal_id) {
            return false;
        }

        $conversation = $this->getConversationModel()->getById($message['conversation_id']);
        if (empty($conversation)) {
            return false;
        }

        $this->getConversationModel()->updateById($conversation['id'], ['deal_id' => $deal_id]);

        // message of this conversation
        $message_ids = array_keys($this->getMessageModel()->getByField(['conversation_id' => $conversation['id']], 'id'));
        $message_ids = waUtils::toIntArray($message_ids);
        $message_ids = waUtils::dropNotPositive($message_ids);

        if ($message_ids) {
            // Update all messages from this conversation
            $this->getMessageModel()->updateByField(['id' => $message_ids], ['deal_id' => $deal_id]);

            // relink log items
            $this->getLogModel()->updateByField(
                [
                    'object_id' => $message_ids,
                    'object_type' => crmLogModel::OBJECT_TYPE_MESSAGE
                ],
                [
                    'contact_id' => -$deal_id,
                ]
            );
        }
    }

    protected function extractNewConversation($message, $deal_id)
    {
        $messages_in_conversation = $this->getMessageModel()->countByField(['conversation_id' => $message['conversation_id']]);
        if ($messages_in_conversation <= 1) {
            return $this->addDealToConversation($message, $deal_id);
        }
        
        $summary = ifset($message, 'subject', '');

        $data = [
            'source_id' => $message['source_id'],
            'contact_id' => $message['contact_id'],
            'user_contact_id' => wa()->getUser()->getId(),
            'summary' => $summary,
            'deal_id' => $deal_id,
        ];

        $conversation_id = $this->getConversationModel()->add($data, $message['transport']);
        $this->getMessageModel()->updateById($message['id'], [
            'conversation_id' => $conversation_id,
            'deal_id' => $deal_id,
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
