<?php

class crmConversationDeleteMethod extends crmApiAbstractMethod
{    
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $conversation_id = (int) $this->get('id', true);

        if ($conversation_id < 0 || !$conversation = $this->getConversationModel()->getById($conversation_id)) {
            throw new waAPIException('not_found', _w('Conversation not found'), 404);
        } elseif (!$this->getCrmRights()->canEditConversation($conversation)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $this->getConversationModel()->delete($conversation_id);

        $this->http_status_code = 204;
        $this->response = null;

        if (!wa()->getUser()->isAdmin('crm')) {
            return;
        }

        // ban contact if needed
        $_json = $this->readBodyAsJson();
        $do_ban = ifempty($_json, 'ban_contact', false);
        if ($do_ban && !empty($conversation['contact_id'])) {
            $contact = $this->getContactModel()->getById($conversation['contact_id']);
            if (!empty($contact)) {
                $reason = ifempty($_json, 'reason', null);
                crmContactBlocker::ban($contact, $reason);
            }
        }
    }
}
