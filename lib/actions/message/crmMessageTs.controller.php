<?php

class crmMessageTsController extends crmJsonController
{
    public function execute()
    {
        $asm = new waAppSettingsModel();
        $this->response = $asm->get('crm', 'message_ts', false);
    }
}
