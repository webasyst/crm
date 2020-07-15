<?php

class crmContactImportResultAction extends crmContactsAction
{
    protected function getHash()
    {
        $date = $this->getDate();
        return "import/{$date}";
    }

    protected function getDate()
    {
        return $this->getParameter('date');
    }
}