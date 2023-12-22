<?php

class crmConversationDealMethod extends crmMessageListMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $conversation_id = (int) ifempty($_json, 'conversation_id', 0);
        $deal_id = ifset($_json, 'deal_id', null);

        if (empty($conversation_id) + !is_numeric($deal_id) > 0) {
            throw new waAPIException(
                'required_param',
                sprintf_wp('Missing required parameters: %s.', sprintf_wp('“%s” and “%s”', 'conversation_id', 'deal_id')),
                400
            );
        } elseif ($conversation_id < 0) {
            throw new waAPIException('not_found', _w('Conversation not found.'), 404);
        } elseif (!$conversation = $this->getConversationModel()->getConversation($conversation_id)) {
            throw new waAPIException('not_found', _w('Conversation not found.'), 404);
        }

        $deal_id = (int) abs($deal_id);
        if ($deal_id === 0) {
            /** Переписка открепляется от сделки */
            $this->dealDetach($conversation);
        } else {
            /** Привязка переписки к сделке или изменение привязки к другой сделке */
            $this->associateDealSave($conversation, $deal_id);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }

    /**
     * @param $conversation
     * @return void
     * @throws waException
     */
    private function dealDetach($conversation)
    {
        if (is_null($conversation['deal_id'])) {
            return;
        }
        $this->getConversationModel()->updateById($conversation['id'], ['deal_id' => null]);

        $messages = $this->getMessageModel()->getByField([
            'conversation_id' => $conversation['id'],
            'deal_id'         => $conversation['deal_id']
        ], 'id');


        if ($messages) {
            /** detach deals */
            $message_ids = waUtils::getFieldValues($messages, 'id');
            $this->getMessageModel()->updateById($message_ids, ['deal_id' => null]);

            /** relink message crm log items */
            $lm = $this->getLogModel();
            foreach ($messages as $message) {
                $lm->updateByField([
                    'object_id'   => $message['id'],
                    'object_type' => crmLogModel::OBJECT_TYPE_MESSAGE
                ], [
                    'contact_id' => $message['contact_id']
                ]);
            }
        }
    }

    /**
     * @param $conversation
     * @param $deal_id
     * @return void
     * @throws waAPIException
     * @throws waDbException
     * @throws waException
     */
    private function associateDealSave($conversation, $deal_id)
    {
        if ($conversation['deal_id'] == $deal_id) {
            return;
        }

        $contact = new crmContact($conversation['contact_id']);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } elseif (!$this->getCrmRights()->contact($contact)) {
            throw new waAPIException('forbidden', _w('Access to contact denied.'), 403);
        } elseif (!$deal = $this->getDealModel()->getDeal($deal_id, false, true)) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        } elseif (!$this->getCrmRights()->deal($deal)) {
            throw new waAPIException('forbidden', _w('Access to deal denied.'), 403);
        }

        /** update conversation */
        $user_contact_id = (!$conversation['user_contact_id'] && $deal['user_contact_id'] ? ['user_contact_id' => $deal['user_contact_id']] : []);
        $this->getConversationModel()->updateById($conversation['id'], ['deal_id' => $deal_id] + $user_contact_id);

        /** message of this conversation */
        $message_ids = array_keys($this->getMessageModel()->getByField(['conversation_id' => $conversation['id']], 'id'));
        $message_ids = waUtils::toIntArray($message_ids);
        $message_ids = waUtils::dropNotPositive($message_ids);

        if ($message_ids) {
            /** update all messages from this conversation */
            $this->getMessageModel()->updateByField(['id' => $message_ids], ['deal_id' => $deal_id]);

            /** relink log items */
            $this->getLogModel()->updateByField(
                [
                    'object_id'   => $message_ids,
                    'object_type' => crmLogModel::OBJECT_TYPE_MESSAGE
                ], [
                    'contact_id' => -$deal_id,
                ]
            );
        }
    }
}
