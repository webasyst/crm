<?php

class crmVkPluginMessageReplyCallbackEvent extends crmVkPluginMessageNewCallbackEvent
{
    protected function checkMessage()
    {
        // If there is a payload message was created in CRM backend and not need to process
        return empty($this->event['object']['payload']) && parent::checkMessage();
    }
}
