<?php

class crmMessageWriteReplyDialogController extends waViewController
{
    public function execute()
    {
        $message_id = waRequest::post('id', null, waRequest::TYPE_INT);
        $mm = new crmMessageModel();
        $message = $mm->getById($message_id);
        if (!empty($message['transport']) && $message['transport'] == 'SMS') {
            throw new waException("SMS reply on SMS message not supported");
        } else {
            $this->executeAction(new crmMessageWriteReplyEmailDialogAction());
        }
    }
}
