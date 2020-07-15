<?php

class crmFrontendViewAction extends crmViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);

        $this->view->assign('content_template', parent::getTemplate());
    }

    protected function getTemplate()
    {
        return 'templates/actions/Frontend.html';
    }
}
