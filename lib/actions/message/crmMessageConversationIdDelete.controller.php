<?php

class crmMessageConversationIdDeleteController extends crmJsonController
{
    public function execute()
    {
        $conversation_id = waRequest::post('id', null, waRequest::TYPE_INT);

        $cm = new crmConversationModel();
        $conversation = $cm->getById($conversation_id);
        if (!$conversation) {
            $this->errors = 'Empty conversation';
            return;
        }

        if (!$this->getCrmRights()->canEditConversation($conversation)) {
            $this->accessDenied();
        }

        $cm->delete($conversation_id);
    }
}
