<?php

class crmTwitterPluginUser
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    protected function getField($name)
    {
        return ifset($this->user[$name]);
    }

    public function getId()
    {
        return $this->getField('id_str');
    }

    public function getName()
    {
        $name = $this->getField('name');
        return $name;
    }

    public function getLogin()
    {
        return $this->getField('screen_name');
    }

    public function getLocation()
    {
        $location = $this->getField('location');
        return $location;
    }

    public function getUrl()
    {
        return $this->getField('url');
    }

    public function getTimezone()
    {
        return $this->getField('time_zone');
    }

    public function getPhotoUrl()
    {
        return $this->getField('profile_image_url_https');
    }
}
