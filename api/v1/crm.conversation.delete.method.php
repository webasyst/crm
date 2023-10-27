<?php

class crmConversationDeleteMethod extends crmMessageListMethod
{    
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $conversation_id = (int) $this->get('id', true);

        if ($conversation_id < 0 || !$conversation = $this->getConversationModel()->getById($conversation_id)) {
            throw new waAPIException('not_found', 'Conversation not found', 404);
        } elseif (!$this->getCrmRights()->canEditConversation($conversation)) {
            throw new waAPIException('forbidden', 'Access denied', 403);
        }

        $this->getConversationModel()->delete($conversation_id);

        $this->http_status_code = 204;
        $this->response = null;
    }
}
