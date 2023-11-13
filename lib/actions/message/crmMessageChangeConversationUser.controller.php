<?php

class crmMessageChangeConversationUserController extends crmJsonController
{
    const ACTION_SET = 'set';
    const ACTION_REMOVE = 'remove';

    public function execute()
    {
        $action = waRequest::post('action', null, waRequest::TYPE_STRING_TRIM);
        if (!$action) {
            $this->errors = array('Unkown action');
            return;
        }

        $conversation_id = waRequest::post('id', null, waRequest::TYPE_INT);
        $conversation = $this->getConversationModel()->getById($conversation_id);
        if (!$conversation) {
            $this->errors = array('Conversation not found');
            return;
        }

        $can_edit_conversation = $this->getCrmRights()->canEditConversation($conversation);
        if (!$can_edit_conversation) {
            $this->errors = array(_w('Access denied'));
            return;
        }

        $user_contact_id = waRequest::post('user_contact_id', null, waRequest::TYPE_INT);
        $user_contact = $after_user = new crmContact($user_contact_id);
        if ($action !== self::ACTION_REMOVE && (empty($user_contact) || !$user_contact->exists())) {
            $this->errors = array("User contact #{$user_contact_id} not found");
            return;
        }

        // Update conversation
        $data = array(
            'user_contact_id' => ($action == self::ACTION_REMOVE) ? null : $user_contact->getId(),
        );
        $this->getConversationModel()->updateById(array('id' => $conversation['id']), $data);

        // Update deal
        $deal = $this->getDealModel()->getById($conversation['deal_id']);
        if ($action !== self::ACTION_REMOVE && $deal && $deal['user_contact_id'] !== $user_contact->getId()) {
            $before_user = new crmContact($deal['user_contact_id']);
            $this->getDealModel()->updateParticipant($deal['id'], $user_contact->getId(), 'user_contact_id');

            $action_id = 'deal_transfer';
            $this->logAction($action_id, array('deal_id' => $deal['id']));
            $this->getLogModel()->log(
                $action_id,
                $deal['id'] * -1,
                $deal['id'],
                $before_user->getName(),
                $after_user->getName(),
                null,
                ['user_id_before' => $before_user->getId(), 'user_id_after' => $after_user->getId()]
            );
        }

        if ($action == self::ACTION_REMOVE) {
            return;
        }

        // Return new conversation responsible contact
        $view = wa()->getView();
        $view->assign(array(
            '_contact' => $after_user,
            '_type'    => 'responsible'
        ));
        $this->response = array(
            'html' => $view->fetch(wa()->getAppPath('templates/actions/message/MessageConversationContact.inc.html', 'crm')),
        );
    }
}
