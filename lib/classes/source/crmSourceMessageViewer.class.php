<?php

abstract class crmSourceMessageViewer
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
    protected $message;

    /**
     * @var array
     */
    protected $options;

    public function __construct(crmSource $source, $message, array $options = array())
    {
        $this->source = $source;
        $this->options = $options;
        $this->message = self::typecastMessage($source, $message);
    }

    /**
     * @param crmSource $source
     * @param array|int $message
     * @param array $options
     * @return crmSourceMessageSender|null
     */
    public static function factory(crmSource $source, $message, array $options = array())
    {
        $class_name = get_class($source);
        $class_name .= 'MessageViewer';
        if (!class_exists($class_name)) {
            return null;
        }
        $object = new $class_name($source, $message, $options);
        if (!($object instanceof self)) {
            return null;
        }
        return $object;
    }

    protected static function typecastMessage(crmSource $source, $message)
    {
        $mm = new crmMessageModel();
        if (wa_is_int($message) && $message > 0) {
            $message = $mm->getMessage($message);
        }
        if (!$message || !is_array($message) || $message['source_id'] != $source->getId()) {
            $message = $mm->getEmptyRow();
            $message['source_id'] = $source->getId();
            $message['transport'] = $source->getType();
        }
        return $message;
    }

    /**
     * @return mixed
     */
    public function render()
    {
        $assigns = array_merge(array(
            'message' => $this->message,
            'source' => $this->source->getInfo(),
            'icon_url' => $this->source->getIcon(),
            'can_delete' => wa()->getUser()->isAdmin('crm'),
        ), $this->getAssigns());
        return $this->renderTemplate($this->getTemplate(), $assigns);
    }

    /**
     * @override
     * @return array
     */
    protected function getAssigns()
    {
        return array();
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

    abstract protected function getTemplate();

    /**
     * @param crmSource $source
     * @param array|int $message
     * @return string
     */
    public static function renderSource(crmSource $source, $message)
    {
        return self::getSenderInstance($source, $message)->render();
    }

    /**
     * @param crmSource $source
     * @param array|int $message
     * @return crmNullSourceMessageViewer|crmSourceMessageViewer
     */
    protected static function getSenderInstance(crmSource $source, $message)
    {
        self::$runtime_cache['page_instances'] = (array)ifset(self::$runtime_cache['page_instances']);
        $hash = spl_object_hash($source);
        if (array_key_exists($hash, self::$runtime_cache['page_instances'])) {
            return self::$runtime_cache['page_instances'][$hash];
        }

        $cache = &self::$runtime_cache['page_instances'][$hash];

        if ($source instanceof crmNullSource) {
            return $cache = new crmNullSourceMessageViewer($source, $message);
        }

        $class_name = self::getClassByType($source->getType());

        if (!$class_name) {
            return $cache = new crmNullSourceMessageViewer($source, $message);
        }

        if (method_exists($class_name, 'factory')) {
            $object = call_user_func(array($class_name, 'factory'), $source, $message);
        } else {
            $object = new $class_name($source);
        }

        if (!($object instanceof crmSourceMessageViewer)) {
            return $cache = new crmNullSourceMessageViewer($source, $message);
        }

        return $cache = $object;
    }


    /**
     * @param $type
     * @return null|string
     */
    protected static function getClassByType($type)
    {
        $type = is_scalar($type) ? (string)$type : '';
        $type = trim($type);
        if (strlen($type) <= 0) {
            return null;
        }
        $type = ucfirst(strtolower($type));
        $class = "crm{$type}SourceMessageViewer";
        if (!class_exists($class)) {
            return null;
        }
        return $class;
    }
}
