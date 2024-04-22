<?php

class crmContactUpdateDialogAction extends waViewAction
{
    public function execute()
    {
        $client_name = '';
        $bonded_call = false;
        $call = self::_getCall();

        if (empty($call)) {
            throw new Exception("Bad call id");
        }
        if (!empty($call['client_contact_id'])) {
            $client = new waContact($call['client_contact_id']);
            if ($client->exists()) {
                $bonded_call = true;
                $client_name = $client->getName();
            }
        }

        $this->view->assign([
            'call' => $call,
            'bonded_call' => $bonded_call,
            'client_name' => $client_name,
            'client_number' => crmHelper::formatCallNumber($call),
        ]);
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
