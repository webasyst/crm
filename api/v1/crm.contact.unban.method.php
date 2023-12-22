<?php

class crmContactUnbanMethod extends crmContactBanMethod
{
    public function execute()
    {
        $this->getData();
        $result = crmContactBlocker::unban($this->contact);
        $this->handleResult($result);
    }
}