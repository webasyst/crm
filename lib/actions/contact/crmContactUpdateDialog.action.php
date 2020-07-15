<?php

class crmContactUpdateDialogAction extends waViewAction
{
    public function execute()
    {
        $call = self::_getCall();

        if (empty($call)) {
            throw new Exception("Bad call id");
        }

        $this->view->assign(array(
            "call" => $call
        ));
    }

    private function _getCall()
    {
        $call_id = waRequest::request("call_id", null, waRequest::TYPE_STRING_TRIM);
        if (!$call_id) {
            return null;
        }
        $cm = new crmCallModel();
        $call = $cm->getById($call_id);
        return $call;
    }
}
