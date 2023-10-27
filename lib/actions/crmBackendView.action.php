<?php

abstract class crmBackendViewAction extends crmViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);

        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new crmDefaultLayout());
        }
    }

    public function preExecute()
    {
        $this::checkSkipUpdateLastPage();
        parent::preExecute();
    }

    public static function checkSkipUpdateLastPage()
    {
        if (waRequest::get('iframe', false)) {
            waRequest::setParam('skip_update_last_page', '1');
        }
    }
}
