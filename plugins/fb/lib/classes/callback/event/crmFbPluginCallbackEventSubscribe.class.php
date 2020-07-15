<?php

class crmFbPluginCallbackEventSubscribe extends crmFbPluginCallbackEvent
{
    /**
     * @return string
     */
    public function execute()
    {
        if ($this->source->isDisabled()) {
            return;
        }

        return ifempty($this->event['hub_challenge']);
    }
}