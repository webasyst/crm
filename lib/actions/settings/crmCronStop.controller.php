<?php

class crmCronStopController extends waJsonController
{
    public function execute()
    {
        $cron_action = waRequest::post('cron_action', null, waRequest::TYPE_STRING_TRIM);
        if (empty($cron_action)) {
            throw new waException('Cron action required', 400);
        }

        $cron_config = wa('crm')->getConfig()->getCron();
        if (!isset($cron_config[$cron_action])) {
            throw new waException('Cron action not found', 404);
        }

        try {
            $result = (new waServicesApi())->deleteJob($cron_action, wa()->getApp());
            $this->response = ifset($result);
        } catch (waException $e) {
            $this->errors = [
                'error_code' => $e->getCode(),
                'error_description' => $e->getMessage(),
            ];
        }
    }
}