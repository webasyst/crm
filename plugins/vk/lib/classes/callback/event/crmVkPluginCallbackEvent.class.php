<?php

abstract class crmVkPluginCallbackEvent
{
    /**
     * @var array
     */
    protected $event;

    /**
     * @var crmVkPluginImSource
     */
    protected $source;

    /**
     * @var array
     */
    protected $options;

    public function __construct(array $event, crmVkPluginImSource $source, array $options = array())
    {
        $this->event = $event;
        $this->source = $source;
        $this->options = $options;
    }

    /**
     * @return string
     * @throws waException
     */
    abstract public function execute();
}
