<?php

class crmTwitterPluginDirectMessage
{
    protected $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    protected function getField($name)
    {
        return ifset($this->message[$name]);
    }

    protected function getMessageField($name)
    {
        return ifset($this->message[$this->getType()][$name]);
    }

    protected function getMessageData($name)
    {
        $data = $this->getMessageField('message_data');
        return ifset($data[$name]);
    }

    public function getId()
    {
        return $this->getField('id');
    }

    public function getType()
    {
        return $this->getField('type');
    }

    public function getSenderId()
    {
        return $this->getMessageField('sender_id');
    }

    public function getRecipientId()
    {
        return ifset($this->message['message_create']['target']['recipient_id']);
    }

    public function getText()
    {
        $text = nl2br($this->getMessageData('text'));
        return $text;
    }
}