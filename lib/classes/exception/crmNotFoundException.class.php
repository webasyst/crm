<?php

class crmNotFoundException extends waException
{
    public function __construct($message = '', $code = 500, $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }

    public function __toString()
    {
        if (waSystemConfig::isDebug() || wa()->getEnv() !== 'backend') {
            return parent::__toString();
        }

        if (waRequest::isXMLHttpRequest()) {
            $message = $this->getMessage();
            if ($message === _ws('Page not found')) {
                $message = '';
            }
            $template = wa()->getAppPath('templates/actions/DialogErrorNotFound.html', 'crm');
            return $this->renderTemplate($template, array('text' => $message));
        }

        return parent::__toString();
    }

    protected function renderTemplate($template, $assign = array())
    {
        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign($assign);
        $result = $view->fetch($template);
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $result;
    }
}
