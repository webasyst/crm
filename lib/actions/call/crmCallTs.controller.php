<?php

class crmCallTsController extends crmJsonController
{
    public function execute()
    {
        $asm = new waAppSettingsModel();
        $this->response = $asm->get('crm', 'call_ts', false);
    }
}
