<?php

class crmGravitelPluginCheckApiController extends crmJsonController
{
    public function execute()
    {
        $result = false;
        try {
            $api = new crmGravitelPluginApi();
            $result = $api->checkApi();
        } catch (waException $e) {}

        $this->response = $result;
    }
}