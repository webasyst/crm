<?php

abstract class crmSourceMessageSender
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
        $class_name .= 'MessageSender';
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
        $assigns = array_merge(
            (array)ifset($this->options['assign']),
            array(
                'message'    => $this->message,
                'source'     => $this->source->getInfo(),
                'icon_url'   => $this->source->getIcon(),
                'can_delete' => wa()->getUser()->isAdmin(),
                'hash'       => md5(time() . wa()->getUser()->getId()),
            ),
            $this->getAssigns()
        );
        return $this->renderTemplate($this->getTemplate(), $assigns);
    }

    /**
     * @override
     * @param array $data
     * @return array $result
     *  - string $result['status'] - 'ok' | 'fail'
     *  - array $result['errors']
     *  - array $result['response']
     */
    public function reply($data)
    {
        return array(
            'status' => 'ok',
            'errors' => array(),
            'response' => array()
        );
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
     * @param array $options
     * @return string
     */
    public static function renderSender(crmSource $source, $message, $options = array())
    {
        return self::getSenderInstance($source, $message, $options)->render();
    }

    /**
     * @param crmSource $source
     * @param array $message
     * @param array $data
     * @param array $options
     * @return mixed
     */
    public static function replyToMessage(crmSource $source, $message, $data, $options = array())
    {
        return self::getSenderInstance($source, $message, $options)->reply($data);
    }

    /**
     * @param crmSource $source
     * @param array|int $message
     * @param array $options
     * @return crmNullSourceMessageSender|crmSourceMessageSender
     */
    protected static function getSenderInstance(crmSource $source, $message, $options = array())
    {
        self::$runtime_cache['page_instances'] = (array)ifset(self::$runtime_cache['page_instances']);
        $hash = spl_object_hash($source);
        if (array_key_exists($hash, self::$runtime_cache['page_instances'])) {
            return self::$runtime_cache['page_instances'][$hash];
        }

        $cache = &self::$runtime_cache['page_instances'][$hash];

        if ($source instanceof crmNullSource) {
            return $cache = new crmNullSourceMessageSender($source, $message);
        }

        $class_name = self::getClassByType($source->getType());

        if (!$class_name) {
            return $cache = new crmNullSourceMessageSender($source, $message);
        }

        if (method_exists($class_name, 'factory')) {
            $object = call_user_func(array($class_name, 'factory'), $source, $message, $options);
        } else {
            $object = new $class_name($source, $message, $options);
        }

        if (!($object instanceof crmSourceMessageSender)) {
            return $cache = new crmNullSourceMessageSender($source, $message);
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
        $class = "crm{$type}SourceMessageSender";
        if (!class_exists($class)) {
            return null;
        }
        return $class;
    }
}
