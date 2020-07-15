<?php

class crmViewBlock
{
    protected $cache = array();

    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $id;

    public function __construct($id, $options = array())
    {
        $this->id = $id;
        $this->options = $options;
    }

    public function getId()
    {
        return $this->id;
    }

    protected function getTemplateFolder()
    {
        return wa()->getAppPath('templates/blocks/', 'crm');
    }

    /**
     * @param array $options
     * @return crmRights
     */
    protected function getCrmRights($options = array())
    {
        return new crmRights($options);
    }

    public function render($assign = array())
    {
        $template_folder = $this->getTemplateFolder();
        $template_path = "{$template_folder}{$this->id}.html";
        if (!file_exists($template_path)) {
            return '';
        }
        $assign = array_merge($assign, (array)$this->getAssigns(), (array)ifset($this->options['assign']));
        $assign['id'] = $this->getId();
        return $this->renderTemplate($template_path, $assign);
    }

    /**
     * @return string[]
     */
    protected function getBlocks()
    {
        $assigns['namespace'] = 'source';
        return (array)ifset($this->options['blocks']);
    }

    /**
     * @override
     * @return array
     */
    protected function getAssigns()
    {
        // override
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

    /**
     * @param $method_key
     * @param $method
     * + arguments
     * @return mixed
     */
    protected function callCachedMethod($method_key, $method)
    {
        if (array_key_exists($method_key, $this->cache)) {
            return $this->cache[$method_key];
        }
        $method = array($this, $method);
        $arguments = array_slice(func_get_args(), 2);
        return $this->cache[$method_key] = call_user_func_array($method, $arguments);
    }
}
