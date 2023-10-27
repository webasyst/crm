<?php

class crmVkPluginVkMessage extends crmVkPluginVkEntity
{
    protected $access_token;

    public function __construct($entity, array $options = array())
    {
        parent::__construct($entity, $options);
        $this->access_token = (string)ifset($options['access_token']);
    }

    protected function getField($name)
    {
        $info = $this->getInfo();
        return ifset($info[$name]);
    }

    public function getDate()
    {
        return $this->getField('date');
    }

    public function getDatetime()
    {
        $date = $this->getDate();
        return $date > 0 ? date('Y-m-d H:i:s', $date) : null;
    }

    public function getFwdMessages()
    {
        $fwd_messages = (array)$this->getField('fwd_messages');
        return $fwd_messages ? $fwd_messages : array();
    }

    public function isImportant()
    {
        return (bool)$this->getField('important');
    }

    /**
     * @depecated
     * @return bool
     */
    public function isDeleted()
    {
        return (bool)$this->getField('deleted');
    }

    /**
     * @depecated
     * @return bool
     */
    public function hasEmoji()
    {
        return (bool)$this->getField('emoji');
    }

    public function getFromId()
    {
        return $this->getField('from_id');
    }

    /**
     * @depecated
     * @return mixed|null
     */
    public function getTitle()
    {
        return $this->getField('title');
    }

    public function getText()
    {
        $text = $this->getField('text');

        return (null === $text ? $this->getField('body') : $text);
    }

    public function getGeo()
    {
        return $this->getField('geo');
    }

    public function getSticker()
    {
        $info = $this->getInfo();
        if (isset($info['extra']) && array_key_exists('sticker', $info['extra'])) {
            return $info['extra']['sticker'];
        }

        $sticker = null;
        $attachments = (array)$this->getField('attachments');
        foreach ($attachments as $attachment) {
            if ($attachment['type'] == 'sticker') {
                $sticker = $attachment['sticker'];
                break;
            }
        }
        return $this->info['extra']['sticker'] = $sticker;
    }

    /**
     * @return array
     */
    public function getAttachments()
    {
        return (array)$this->getField('attachments');
    }

    public function markAsRead()
    {
        $api = new crmVkPluginApi($this->access_token);
        return $api->markAsRead(array('message_ids' => array($this->getId())));
    }

    /**
     * @return array
     */
    protected function loadInfo()
    {
        $api = new crmVkPluginApi($this->access_token);
        $info = $api->getMessage($this->getId());
        return $info;
    }
}
