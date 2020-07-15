<?php

class crmTwitterPluginMention
{
    protected $mention;

    public function __construct($mention)
    {
        $this->mention = $mention;
    }

    public function getField($name)
    {
        return ifset($this->mention[$name]);
    }

    public function getId()
    {
        return $this->getField('id_str');
    }

    public function getText()
    {
        $text = nl2br($this->getField('text'));
        return $text;
    }

    /**
     * @return crmTwitterPluginUser
     */
    public function getUser()
    {
        $user = $this->getField('user');
        return new crmTwitterPluginUser($user);
    }
}