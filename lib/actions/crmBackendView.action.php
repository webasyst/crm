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
}
