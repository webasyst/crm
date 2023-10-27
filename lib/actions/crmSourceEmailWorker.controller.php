<?php

class crmSourceEmailWorkerController extends crmJsonController
{
    public function execute()
    {
        session_write_close();
        crmSourceWorker::cliRun();
        crmRemindersPush::cliRun();
    }
}
