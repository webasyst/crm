<?php

class crmYandextelephonyPluginCheckApiController extends crmJsonController
{
    public function execute()
    {
        $result = false;
        try {
            $api = new crmYandextelephonyPluginApi();
            $result = $api->checkApi();
        } catch (waException $e) {}

        $this->response = $result;
    }
}