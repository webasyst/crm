<?php

abstract class crmFbPluginCallbackEvent
{
    /*
        array(
            'object' => 'page',
            'entry'  => array(
                0 => array(
                    'id'        => '627441297590584',
                    'time'      => 1526458606791,
                    'messaging' => array(
                        0 => array(
                            'sender'    => array(
                                'id' => '1989732291039281',
                            ),
                            'recipient' => array(
                                'id' => '627441297590584',
                            ),
                            'timestamp' => 1526458606474,
                            'message'   => array(
                                'mid'  => 'mid.$cAAI6p9KOnv5pmUsriljaAXYA_Z9S',
                                'seq'  => 834,
                                'text' => 'Hola!',
                            ),
                        ),
                    ),
                ),
            ),
        ),
     */
    /** @var array */
    protected $event;

    /** @var crmFbPluginImSource */
    protected $source;

    /** @var array */
    protected $options;

    public function __construct(array $event, crmFbPluginImSource $source, array $options = array())
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

    protected function throwUnknownEventException($message = 'error :/')
    {
        crmFbPlugin::sendError($message);
        throw new waException('Unsupported or unknown event');
    }
}