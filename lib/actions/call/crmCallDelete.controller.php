<?php

class crmCallDeleteController extends crmJsonController
{
    public function execute()
    {
        $call_id = waRequest::post('id', null, waRequest::TYPE_INT);

        $this->validate($call_id);

        $this->deleteCall($call_id);
    }

    protected function validate($call_id)
    {
        $cm = new crmCallModel();
        $call = $cm->getById($call_id);

        if (!$call_id || !$call) {
            throw new waException(_w('Call not found'));
        }
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
    }

    protected function deleteCall($call_id)
    {
        $cm = new crmCallModel();
        $cm->deleteById($call_id);
    }
}
