<?php

class crmMessageConversationAssociateDealSaveController extends crmJsonController
{
    public function execute()
    {
        $conversation_id = waRequest::post('conversation_id', 0, waRequest::TYPE_INT);
        if (!$conversation_id) {
            $this->errors = array('No conversation identifier');
            return;
        }

        $conversation = $this->getConversationModel()->getById($conversation_id);

        if (empty($conversation)) {
            $this->errors = array('conversation not found');
            return;
        }

        $contact = new crmContact($conversation['contact_id']);
        if (empty($contact) || !$contact->exists()) {
            $this->errors = array(_w('Contact not found'));
            return;
        }
        if (!$this->getCrmRights()->contact($contact)) {
            $this->errors = array('Access to the contact is denied.');
            return;
        }

        $deal = waRequest::post('deal', null, waRequest::TYPE_ARRAY_TRIM);
        if (empty($deal)) {
            $this->errors = array(_w('No data on the deal.'));
            return;
        }

        if ($deal['id'] > 0) {
            $deal = $this->getDealModel()->getDeal($deal['id'], false, true);
            if (!$deal) {
                $this->errors = array(_w('Deal not found'));
                return;
            }
            if (!$this->getCrmRights()->deal($deal)) {
                $this->errors = array(_w('Access to deal is denied.'));
                return;
            }

            if (!$conversation['user_contact_id'] && $deal['user_contact_id']) {
                $this->getConversationModel()->updateById($conversation['id'], array('user_contact_id' => $deal['user_contact_id']));
            }

            // update conversation
            $this->tieConversationWithDeal($conversation['id'], $deal['id']);
            return;
        }

        if ($deal['id'] == 0 && intval($deal['funnel_id']) && intval($deal['stage_id']) && trim($deal['name'])) {
            // Funnel rights
            if (!$this->getCrmRights()->funnel($deal['funnel_id'])) {
                $this->errors = array('Access to a funnel is denied');
                return;
            }

            // Create new deal
            $id = $this->getDealModel()->add(array(
                'contact_id'      => (int)$contact['id'],
                'status_id'       => 'OPEN',
                'name'            => trim($deal['name']),
                'funnel_id'       => (int)$deal['funnel_id'],
                'stage_id'        => (int)$deal['stage_id'],
                'user_contact_id' => $conversation['user_contact_id'] ? $conversation['user_contact_id'] : wa()->getUser()->getId(),
            ));

            if (!$conversation['user_contact_id']) {
                $this->getConversationModel()->updateById($conversation['id'], array('user_contact_id' => wa()->getUser()->getId()));
            }

            $this->tieConversationWithDeal($conversation['id'], $id);

            return;
        }

        $this->errors = array('Unknown error');
    }

    protected function tieConversationWithDeal($conversation_id, $deal_id)
    {
        if (!$conversation_id || !$deal_id) {
            return false;
        }

        // Update conversation
        $this->getConversationModel()->updateById($conversation_id, array('deal_id' => $deal_id));

        // message of this conversation
        $message_ids = array_keys($this->getMessageModel()->getByField(array('conversation_id' => $conversation_id), 'id'));
        $message_ids = waUtils::toIntArray($message_ids);
        $message_ids = waUtils::dropNotPositive($message_ids);

        if ($message_ids) {

            // Update all messages from this conversation
            $this->getMessageModel()->updateByField(array('id' => $message_ids), array('deal_id' => $deal_id));

            // relink log items
            $this->getLogModel()->updateByField(
                array(
                    'object_id' => $message_ids,
                    'object_type' => crmLogModel::OBJECT_TYPE_MESSAGE
                ),
                array(
                    'contact_id' => -$deal_id,
                )
            );
        }
    }
}
