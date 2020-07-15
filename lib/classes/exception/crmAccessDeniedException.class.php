<?php

class crmAccessDeniedException extends waRightsException
{
    public function __construct($message = null, $code = 403, $previous = null)
    {
        parent::__construct($message, $code, $previous);

        // user might lost access to last page (for example page to calls)
        // and to prevent "stuck-ing" in this page, delete this last_url
        wa()->getUser()->delSettings('crm', 'last_url');

    }

    public function __toString()
    {
        if (waSystemConfig::isDebug() || wa()->getEnv() !== 'backend') {
            return parent::__toString();
        }

        if (waRequest::isXMLHttpRequest()) {
            $message = $this->getMessage();
            if ($message === _ws('Access denied')) {
                $message = '';
            }
            $template = wa()->getAppPath('templates/actions/DialogErrorAccessDenied.html', 'crm');
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
