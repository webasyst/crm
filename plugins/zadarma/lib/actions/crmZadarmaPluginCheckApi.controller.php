<?php

class crmZadarmaPluginCheckApiController extends crmJsonController
{
    public function execute()
    {
        $result = false;
        try {
            $api = new crmZadarmaPluginApi();
            $result = $api->checkApi();
        } catch (waException $e) {}

        $this->response = $result;
    }
}