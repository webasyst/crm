<?php

class crmFbPluginFbUser
{
    protected $id;
    protected $marker_token;
    protected $info;

    protected $ttl = 120; // 2min

    public function __construct($id, $marker_token)
    {
        $this->id = (int)$id;
        $this->marker_token = (string)$marker_token;
    }

    protected function loadInfo()
    {
        $api = new crmFbPluginApi($this->marker_token);
        return $api->getUser($this->id);
    }

    public function getInfo()
    {
        if ($this->info) {
            return $this->info;
        }
        $info = $this->getFromCache();
        if ($info) {
            return $this->info = $info;
        }
        $this->info = $this->loadInfo();
        if (!$this->info) {
            throw new waException("Couldn't load data from fb.com");
        }
        $this->id = $this->id > 0 ? $this->id : $this->info['id'];
        $this->setIntoCache($this->info);
        return $this->info;
    }

    #################################
    ############# Cache #############
    #################################

    protected function formCacheKey()
    {
        return get_class($this) . '/' . $this->id;
    }

    protected function getFromCache()
    {
        $key = $this->formCacheKey();
        $cache = new waSerializeCache($key, $this->ttl);
        return $cache->get();
    }

    protected function setIntoCache($data)
    {
        $key = $this->formCacheKey();
        $cache = new waSerializeCache($key, $this->ttl);
        return $cache->set($data);
    }
}