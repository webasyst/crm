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
            $this->errors = array('Message not found');
            return;
        }

        $contact = new crmContact($message['contact_id']);
        if (empty($contact) || !$contact->exists()) {
            $this->errors = array('Contact not found');
            return;
        }
        if (!$this->getCrmRights()->contact($contact)) {
            $this->errors = array('Access to a contact is denied');
            return;
        }

        $deal_data = waRequest::post('deal', null, waRequest::TYPE_ARRAY_TRIM);

        if (empty($deal_data)) {
            $this->errors = array('No data on the deal');
            return;
        }

        if (ifset($deal_data['id']) == 'none') {
            $this->errors = array('Deal no required');
            return;
        }

        $deal_data['id'] = (int)ifset($deal_data['id']);

        if ($deal_data['id'] > 0) {
            $deal = $this->getDealModel()->getDeal($deal_data['id'], false, true);
            if (!$deal) {
                $this->errors = array('Deal not found');
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

            $this->addDealToConversation($message, $deal['id']);

            $this->response = array(
                'deal_url' => wa()->getAppUrl('crm')."deal/{$deal['id']}",
            );
            return;
        }

        if ($deal_data['id'] == 0 && intval($deal_data['funnel_id']) && intval($deal_data['stage_id']) && trim($deal_data['name'])) {
            // Funnel rights
            if (!$this->getCrmRights()->funnel($deal_data['funnel_id'])) {
                $this->errors = array('Access denied');
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

            $this->addDealToConversation($message, $id);

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
        if (!$conversation) {
            return false;
        }

        if (!$conversation['deal_id']) {
            return $this->getConversationModel()->updateById($conversation['id'], array('deal_id' => $deal_id));
        }

        return false;
    }
}
