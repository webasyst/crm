<?php

class crmFrontendFormSubmitController extends crmJsonController
{
    /**
     * waInstaller force write (wrote) module=frontend rule
     * So we could not use own module prefix in routing, like frontendForm/
     * So we could not use multi action controller(s)
     * Just single action controller
     * So we have to use delegation
     */
    public function execute()
    {
        // delegation
        $controller = new crmFrontendFormActions(array('return' => true));
        $result = $controller->run('submit');
        $this->response = $result['response'];
        $this->errors = $result['errors'];
    }
}
