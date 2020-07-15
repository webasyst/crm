<?php

class crmZadarmaPluginPbxUsersController extends crmJsonController
{
    public function execute()
    {
        $users = array();
        try {
            $api = new crmZadarmaPluginApi();
            $users = $api->getPbxUsers();
        } catch (waException $e) {}

        $this->response = $users;
    }
}