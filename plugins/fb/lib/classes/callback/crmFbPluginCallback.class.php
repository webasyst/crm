<?php

class crmFbPluginCallback
{
    /**
     * @var array
     */
    protected $event;

    /**
     * @var crmFbPluginImSource
     */
    protected $source;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var crmFbPluginCallbackEvent
     */
    protected $event_object;

    public function __construct(array $event, crmFbPluginImSource $source, $options = array())
    {
        $this->event = $event;
        $this->source = $source;
        $this->options = $options;
        $this->event_object = $this->factoryCallbackEventInstance();
    }

    /**
     * @throws waException
     * @return crmFbPluginCallbackEvent
     */
    protected function factoryCallbackEventInstance()
    {
        if (!$this->event) {
            $this->throwUnknownEventException('no event');
        }

        // Check signature here
        if (isset($this->event['subscribe']['hub_verify_token']) && $this->event['subscribe']['hub_verify_token'] !== $this->source->getParam('verification_marker')) {
            $this->throwUnknownEventException('signature fail');
        }

        $allowed_event_types = array(
            'subscribe',
            'message',
        );

        $type = null;
        if (!empty($this->event['subscribe'])) {
            $type = 'subscribe';
            $this->event = $this->event['subscribe'];
        } elseif (!empty($this->event['message']) && !empty($this->event['message']['object']) && $this->event['message']['object'] == 'page') {
            $type = 'message';
            $this->event = $this->event['message'];
        }

        if (!in_array($type, $allowed_event_types)) {
            $this->throwUnknownEventException('invalid type');
        }

        $part_of_name = '';
        foreach (explode('_', $type) as $part) {
            $part_of_name .= ucfirst($part);
        }

        $class_name = "crmFbPluginCallbackEvent{$part_of_name}";
        if (!class_exists($class_name)) {
            $this->throwUnknownEventException($class_name .' class unkown');
        }

        $instance = new $class_name($this->event, $this->source, $this->options);
        if (!($instance instanceof crmFbPluginCallbackEvent)) {
            $this->throwUnknownEventException('not instant of crmFbPluginCallbackEvent');
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

    protected function throwUnknownEventException($message = 'error :/')
    {
        crmFbPlugin::sendError($message);
        throw new waException('Unsupported or unknown event');
    }
}