<?php

class crmSourceEmailWorkerController extends crmJsonController
{
    public function execute()
    {
        session_write_close();

        $forced_source = waRequest::post('forced_source');
        if (!empty($forced_source)) {
            crmSourceWorker::cliRun(['sources' => [$forced_source]]);
        } else {
            crmSourceWorker::cliRun();
            crmRemindersPush::cliRun();            
        }

        if (!empty(crmSourceWorker::$not_finished)) {
            $this->response = [
                'not_finished' => crmSourceWorker::$not_finished,
            ];            
        }
    }
}
