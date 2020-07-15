<?php

class crmFbPluginEntryMessage
{
    protected $message;

    public function __construct(array $message)
    {
        $this->message = $message;
    }

    public function getSenderId()
    {
        return ifempty($this->message['sender']['id']);
    }

    public function getResipientId()
    {
        return ifempty($this->message['recipient']['id']);
    }

    public function getMessageId()
    {
        return ifempty($this->message['message']['mid']);
    }

    public function getText()
    {
        return ifempty($this->message['message']['text']);
    }

    /**
     * @return array|null
     */
    public function getAttachments()
    {
        if (empty($this->message['message']['attachments'])) {
            return null;
        }
        $attachments = array();
        foreach ($this->message['message']['attachments'] as $attachment) {
            if (empty($attachment['payload']['url']) || empty($attachment['type'])) {
                continue;
            }
            $attachments[$attachment['type']][] = $attachment['payload']['url'];
        }
        return !empty($attachments) ? $attachments : null;
    }
}