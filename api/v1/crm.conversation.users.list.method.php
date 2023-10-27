<?php

class crmConversationUsersListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $has_access_to_user_filter = $this->getCrmRights()->getConversationsRights() >= crmRightConfig::RIGHT_CONVERSATION_ALL;
        if (!$has_access_to_user_filter) {
            return;
        }
        
        $userpic_size = waRequest::get('userpic_size', 32, waRequest::TYPE_INT);
        $user_ids = array_keys($this->getConversationModel()->select('DISTINCT(user_contact_id) id')->where('user_contact_id IS NOT NULL AND user_contact_id <> 0')->fetchAll('id'));
        $users = $this->getContactsMicrolist(
            $user_ids,
            ['id', 'name', 'userpic'],
            $userpic_size
        );
        $this->response = $users;
    }
}