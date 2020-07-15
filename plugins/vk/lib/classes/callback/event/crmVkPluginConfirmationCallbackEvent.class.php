<?php

class crmVkPluginConfirmationCallbackEvent extends crmVkPluginCallbackEvent
{
    /**
     * @return string
     * @throws waException
     */
    public function execute()
    {
        return (string)$this->source->getParam('verify_code');
    }
}
