<?php

class crmTelphinPluginCheckApiController extends crmJsonController
{
    public function execute()
    {
        $result = false;
        try {
            $api = new crmTelphinPluginApi();
            $result = $api->checkApi();
        } catch (waException $e) {}

        $this->response = $result;
    }
}