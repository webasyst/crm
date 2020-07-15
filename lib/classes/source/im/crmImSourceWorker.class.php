<?php

abstract class crmImSourceWorker extends crmSourceWorker
{
    /**
     * @var crmImSource
     */
    protected $source;

    public static function factory(crmImSource $source, array $options = array())
    {
        $class_name = get_class($source);
        $class_name .= 'Worker';
        if (!$class_name) {
            return null;
        }
        if (!class_exists($class_name)) {
            return null;
        }
        $object = new $class_name($source, $options);
        if (!($object instanceof self)) {
            return null;
        }
        return $object;
    }
}
