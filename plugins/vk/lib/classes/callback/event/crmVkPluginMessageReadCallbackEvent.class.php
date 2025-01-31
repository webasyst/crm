<?php

class crmVkPluginMessageReadCallbackEvent extends crmVkPluginCallbackEvent
{
    public function execute()
    {
        if ($this->source->isDisabled()) {
            return 'ok';
        }

        $message = $this->source->findMessage($this->event['object']['read_message_id']);
        if (empty($message) || $message['direction'] === crmMessageModel::DIRECTION_IN) {
            return 'ok';
        }

        $this->source->handleMessageStatus($message['id'], crmImSource::STATUS_READ);
        return 'ok';
    }
}
