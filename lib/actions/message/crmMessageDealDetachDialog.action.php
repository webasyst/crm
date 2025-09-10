<?php

class crmMessageDealDetachDialogAction extends crmBackendViewAction
{
    public function execute()
    {
        $message_id = waRequest::get("message_id", 0, waRequest::TYPE_INT);

        $message = $this->getMessageModel()->getById($message_id);

        if (empty($message)) {
            $this->notFound(_w('Message not found'));
        }

        if (empty($message['contact_id'])) {
            $this->notFound(_w('Message contact not found.'));
        }

        $contact = new crmContact($message['contact_id']);

        if (empty($contact) || !$contact->exists()) {
            $this->notFound(_w('Message contact not found.'));
        }

        $conversation = null;
        if (!empty($message['conversation_id'])) {
            $conversation = $this->getConversationModel()->getById($message['conversation_id']);
            if (!empty($conversation) && !$this->getCrmRights()->canEditConversation($conversation)) {
                $this->accessDenied();
            }
        }

        $this->view->assign([
            'contact'       => $contact,
            'message'       => $message,
            'is_admin'      => $this->getCrmRights()->isAdmin(),
            'deal'          => $this->getDealModel()->getById($message['deal_id']),
            'conversation'  => $conversation,
        ]);
    }
}
