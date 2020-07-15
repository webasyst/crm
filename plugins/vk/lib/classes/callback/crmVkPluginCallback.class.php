<?php

class crmVkPluginCallback
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

    /**
     * @var crmVkPluginCallbackEvent
     */
    protected $event_object;

    public function __construct(array $event, crmVkPluginImSource $source, $options = array())
    {
        $this->event = $event;
        $this->source = $source;
        $this->options = $options;
        $this->event_object = $this->factoryCallbackEventInstance();
    }

    /**
     * @throws waException
     * @return crmVkPluginCallbackEvent
     */
    protected function factoryCallbackEventInstance()
    {
        if (!$this->event || empty($this->event['type'])) {
            $this->throwUnknownEventException();
        }

        $event_group_id = (int)ifset($this->event['group_id']);
        $source_group_id = $this->source->getGroupId();
        if ($event_group_id <= 0 || $source_group_id <= 0 || $event_group_id !== $source_group_id) {
            $this->throwInvalidGroupIdException();
        }

        $event_secret_key = ifset($this->event['secret']);
        $source_secret_key = $this->source->getSecretKey();
        if ($source_secret_key && $source_secret_key !== $event_secret_key) {
            $this->throwInvalidSecretKeyException();
        }

        $type = $this->event['type'];

        $allowed_event_types = array(
            'confirmation',
            'message_new'
        );

        if (!in_array($type, $allowed_event_types)) {
            $this->throwUnknownEventException();
        }

        $part_of_name = '';
        foreach (explode('_', $type) as $part) {
            $part_of_name .= ucfirst($part);
        }

        $class_name = "crmVkPlugin{$part_of_name}CallbackEvent";
        if (!class_exists($class_name)) {
            $this->throwUnknownEventException();
        }

        $instance = new $class_name($this->event, $this->source, $this->options);
        if (!($instance instanceof crmVkPluginCallbackEvent)) {
            $this->throwUnknownEventException();
        }

        return $instance;
    }

    /**
     * @return string
     * @throws waException
     */
    public function process()
    {
        return $this->event_object->execute();
    }

    protected function throwUnknownEventException()
    {
        throw new waException('Unsupported or unknown event');
    }

    protected function throwInvalidGroupIdException()
    {
        throw new waException('Invalid group_id');
    }

    protected function throwInvalidSecretKeyException()
    {
        throw new waException('Invalid secret key');
    }
}
