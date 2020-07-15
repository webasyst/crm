<?php

class crmCallFinishController extends crmJsonController
{
    public function execute()
    {
        $call_id = waRequest::post('id', 0, waRequest::TYPE_INT);
        if (!$call_id) {
            return;
        }
        $call = $this->getCallModel()->getById($call_id);
        if (empty($call)) {
            return;
        }

        $this->getCallModel()->updateById($call_id, array(
            'status_id' => "FINISHED"
        ));

        $action = new crmCallAction(array(
            'call_id' => array($call_id)
        ));
        $html = $action->display();

        $this->response['html'] = $html;
    }
}
