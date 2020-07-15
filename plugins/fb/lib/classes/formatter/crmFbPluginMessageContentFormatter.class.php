<?php

abstract class crmFbPluginMessageContentFormatter
{
    protected $message;
    public function __construct($message)
    {
        $this->message = $message;
    }

    public function execute()
    {
        return $this->renderTemplate($this->getTemplate(), $this->getAssigns());
    }

    /**
     * @return array
     */
    abstract protected function getAssigns();

    /**
     * @return string
     */
    abstract protected function getTemplate();

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
}