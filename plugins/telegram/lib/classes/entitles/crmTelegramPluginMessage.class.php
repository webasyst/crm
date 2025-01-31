<?php

class crmTelegramPluginMessage
{
    protected $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    protected function getMessageField($name)
    {
        if (isset($this->message['result'])) {
            return ifset($this->message['result'][$name]);
        }
        if (isset($this->message['edited_message'])) {
            return ifset($this->message['edited_message'][$name]);
        }
        return ifset($this->message['message'][$name]);
    }

    public function getSenderField($name)
    {
        if (isset($this->message['result'])) {
            return ifset($this->message['result']['from'][$name]);
        }
        if (isset($this->message['edited_message'])) {
            return ifset($this->message['edited_message']['from'][$name]);
        }
        return ifset($this->message['message']['from'][$name]);
    }

    public function getChatField($name)
    {
        if (isset($this->message['result'])) {
            return ifset($this->message['result']['chat'][$name]);
        }
        if (isset($this->message['edited_message'])) {
            return ifset($this->message['edited_message']['chat'][$name]);
        }
        return ifset($this->message['message']['chat'][$name]);
    }

    public function getUpdateId()
    {
        return ifset($this->message['update_id']);
    }

    public function getId()
    {
        return $this->getMessageField('message_id');
    }

    public function getDate()
    {
        return $this->getMessageField('date');
    }

    public function getDatetime()
    {
        $date = $this->getDate();
        return $date > 0 ? date('Y-m-d H:i:s', $date) : null;
    }

    public function getText()
    {
        return $this->getMessageField('text');
    }

    public function getEntities()
    {
        return $this->getMessageField('entities');
    }

    public function getSticker()
    {
        return $this->getMessageField('sticker');
    }

    public function getPhoto()
    {
        return $this->getMessageField('photo');
    }

    public function getAudio()
    {
        return $this->getMessageField('audio');
    }

    public function getVoice()
    {
        return $this->getMessageField('voice');
    }

    public function getVideo()
    {
        return $this->getMessageField('video');
    }

    public function getVideoNote()
    {
        return $this->getMessageField('video_note');
    }

    public function getLocation()
    {
        return $this->getMessageField('location');
    }

    public function getVenue()
    {
        return $this->getMessageField('venue');
    }

    public function getDocument()
    {
        return $this->getMessageField('document');
    }

    public function getCaption()
    {
        return $this->getMessageField('caption');
    }

    public function getCaptionEntities()
    {
        return $this->getMessageField('caption_entities');
    }

    public function getAttachments()
    {
        return ifset($this->message['attachments']);
    }

    public function getForwardData()
    {
        return $this->getMessageField('forward_from');
    }

    public function getContactData()
    {
        return $this->getMessageField('contact');
    }
}
