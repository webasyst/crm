<?php

class crmCallCheckStatusController extends crmJsonController
{
    public function execute()
    {
        $call_id = waRequest::post('id', null, waRequest::TYPE_INT);
        $call = $this->getCallModel()->getById($call_id);

        if (!$call) {
            $this->errors = array('message' => 'Call '.$call_id.' not found');
        }

        $this->response = $call;
    }
}