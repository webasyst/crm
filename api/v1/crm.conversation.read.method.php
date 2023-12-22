<?php

class crmConversationReadMethod extends crmMessageListMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $conversation_id = (int) ifempty($_json, 'id', 0);

        if (empty($conversation_id)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        } elseif ($conversation_id < 0) {
            throw new waAPIException('not_found', _w('Conversation not found.'), 404);
        }

        $conversation = $this->getConversationModel()->getConversation($conversation_id);
        if (empty($conversation)) {
            throw new waAPIException('not_found', _w('Conversation not found.'), 404);
        } elseif (!$this->getCrmRights()->canViewConversation($conversation)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $this->getMessageReadModel()->setReadConversation($conversation['id']);

        $this->http_status_code = 204;
        $this->response = null;
    }
}
