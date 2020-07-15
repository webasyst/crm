<?php

class crmMessageConversationIdCheckController extends waJsonController
{
    public function execute()
    {
        $conversation_id = waRequest::post('id', null, waRequest::TYPE_INT);
        if ($conversation_id) {
            $mm = new crmMessageModel();
            $last_id = $mm->select('*')->where('conversation_id='.$conversation_id)->order('id DESC')->limit(1)->fetchField('id');
            $this->response = $last_id;
        } else {
            $this->errors = 'Empty conversation ID';
        }
    }
}
