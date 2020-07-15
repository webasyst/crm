<?php

abstract class crmSourceSettingsPage
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
     * @return crmSourceSettingsPage|null
     */
    public static function factory(crmSource $source, array $options = array())
    {
        $class_name = get_class($source);
        $class_name .= 'SettingsPage';
        if (!class_exists($class_name)) {
            return null;
        }
        $object = new $class_name($source, $options);
        if (!($object instanceof self)) {
            return null;
        }
        return $object;
    }

    /**
     * @return mixed
     */
    public function render()
    {
        $assigns = array_merge(array(
            'source' => $this->source->getInfo(),
            'icon_url' => $this->source->getIcon()
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
     * @override
     * @param $data
     * @return array
     */
    public function processSubmit($data)
    {
        return array(
            'status' => 'ok',
            'errors' => array(),
            'response' => array()
        );
    }

    public static function renderSource(crmSource $source)
    {
        return self::getPageInstance($source)->render();
    }

    public static function processSourceSubmit(crmSource $source, $data)
    {
        if (isset($data['id'])) {
            unset($data['id']);
        }
        return self::getPageInstance($source)->processSubmit($data);
    }

    protected static function getPageInstance(crmSource $source)
    {
        self::$runtime_cache['page_instances'] = (array)ifset(self::$runtime_cache['page_instances']);
        $hash = spl_object_hash($source);
        if (array_key_exists($hash, self::$runtime_cache['page_instances'])) {
            return self::$runtime_cache['page_instances'][$hash];
        }

        $cache = &self::$runtime_cache['page_instances'][$hash];

        if (($source instanceof crmNullEmailSource) || ($source instanceof crmNullSource)) {
            return $cache = new crmNullSourceSettingsPage($source);
        }

        $class_name = self::getClassByType($source->getType());

        if (!$class_name) {
            return $cache = new crmNullSourceSettingsPage($source);
        }

        if (method_exists($class_name, 'factory')) {
            $object = call_user_func(array($class_name, 'factory'), $source);
        } else {
            $object = new $class_name($source);
        }

        if (!($object instanceof crmSourceSettingsPage)) {
            return $cache = new crmNullSourceSettingsPage($source);
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
        $class = "crm{$type}SourceSettingsPage";
        if (!class_exists($class)) {
            return null;
        }
        return $class;
    }
}
