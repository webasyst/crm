<?php

abstract class crmVkPluginVkEntity
{
    protected $id;
    protected $info;
    protected $options;

    protected $ttl = 120;     // 2min

    public function __construct($entity, $options = array())
    {
        if (is_scalar($entity)) {
            $this->id = (string)$entity;
        } elseif (is_array($entity)) {
            $this->info = $entity;
            $this->id = (int)ifset($entity['id']);
        } else {
            throw new crmVkPluginException("Invalid argument");
        }
        $this->options = $options;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return array
     * @throws crmVkPluginException
     */
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
            throw new crmVkPluginException("Couldn't load data from vk.ru");
        }
        $this->id = $this->id > 0 ? $this->id : $this->info['id'];
        $this->setIntoCache($this->info);
        return $this->info;
    }

    protected function getField($name)
    {
        $info = $this->getInfo();
        return isset($info[$name]) ? $info[$name] : null;
    }

    /**
     * @return array
     */
    abstract protected function loadInfo();

    protected function formCacheKey()
    {
        return get_class($this) . '/' . $this->id;
    }

    protected function getFromCache()
    {
        if (isset($this->options['cache']) && $this->options['cache'] === false) {
            return null;
        }

        $cache_options = array(
            'ttl' => $this->ttl
        );
        if (isset($this->options['cache']) && is_array($this->options['cache'])) {
            $cache_options = array_merge($cache_options, $this->options['cache']);
        }

        $key = $this->formCacheKey();
        $cache = new waSerializeCache($key, $cache_options['ttl']);
        return $cache->get();
    }

    protected function setIntoCache($data)
    {
        $key = $this->formCacheKey();
        $cache = new waSerializeCache($key, $this->ttl);
        return $cache->set($data);
    }
}
