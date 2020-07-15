<?php

abstract class crmSourceHelper
{
    /**
     * @var array
     */
    protected static $runtime_cache = array();

    /**
     * @var array
     */
    protected $cache = array();

    /**
     * @var crmSource
     */
    protected $source;

    /**
     * @var array
     */
    protected $options;

    public function __construct(crmSource $source, array $options = array())
    {
        $this->source = $source;
        $this->options = $options;
    }

    /**
     * @param crmSource $source
     * @param array $options
     * @return crmSourceHelper|null
     */
    public static function factory(crmSource $source, array $options = array())
    {
        return self::getHelperInstance($source, $options);
    }

    /**
     * @override
     * @param $message
     * @return array
     */
    public function workupMessageInList($message)
    {
        // override it
        return $message;
    }

    /**
     * @override
     * @param array $message
     * @param array $log_item
     * @return array
     */
    public function workupMessageLogItemHeader($message, $log_item)
    {
        // override it
        return $log_item;
    }

    /**
     * @override
     * @param array $message
     * @return array
     */
    public function workupMessageLogItemBody($message)
    {
        // override it
        return $message;
    }

    /**
     * @override
     * @param array $message
     * @return array
     */
    public function workupMessagePopupItem($message)
    {
        // override it
        return $message;
    }

    /**
     * @override
     * @param $conversation
     * @return mixed
     */
    public function workupConversation($conversation)
    {
        // override it
        return $conversation;
    }

    /**
     * @override
     * @param $conversation
     * @param $messages
     * @return mixed
     */
    public function workupMessagesInConversation($conversation, $messages)
    {
        // override it
        return $messages;
    }

    protected function renderTemplate($template, $assign = array())
    {
        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign($assign);
        $html = $view->fetch($template);
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $html;
    }

    protected static function getHelperInstance(crmSource $source, $options = array())
    {
        self::$runtime_cache['page_instances'] = (array)ifset(self::$runtime_cache['page_instances']);
        $hash = spl_object_hash($source);
        if (array_key_exists($hash, self::$runtime_cache['page_instances'])) {
            return self::$runtime_cache['page_instances'][$hash];
        }

        $cache = &self::$runtime_cache['page_instances'][$hash];

        if (($source instanceof crmNullEmailSource) || ($source instanceof crmNullSource)) {
            return $cache = new crmNullSourceHelper($source);
        }

        $class_name = get_class($source);
        $class_name .= 'Helper';
        if (!class_exists($class_name)) {
            return $cache = new crmNullSourceHelper($source);
        }

        $object = new $class_name($source, $options);

        if (!($object instanceof crmSourceHelper)) {
            return $cache = new crmNullSourceHelper($source);
        }

        return $cache = $object;
    }
}
