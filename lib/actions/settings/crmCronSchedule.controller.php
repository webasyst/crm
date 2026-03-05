<?php

class crmCronScheduleController extends waJsonController
{
    public function execute()
    {
        $cron_action = waRequest::get('cron_action', null, waRequest::TYPE_STRING_TRIM);
        if (empty($cron_action)) {
            throw new waException('Cron action required', 400);
        }

        $cron_config = wa('crm')->getConfig()->getCron();
        if (!isset($cron_config[$cron_action])) {
            throw new waException('Cron action not found', 404);
        }
        
        $expression = $cron_config[$cron_action]['expression'];
        if (!waCronController::isValidExpression($expression)) {
            throw new waException('Cron expression is not valid');
        }

        try {
            $result = (new waServicesApi())->schedule(
                $expression, 
                $cron_action, 
                wa()->getApp(), 
                [], 
                waNet::METHOD_GET, 
                ifset($cron_config[$cron_action]['timeout'], null)
            );
            if (empty($result) || empty($result['next_run_at']) || $result['status'] != 'READY') {
                throw new waException('Unexpected error');
            }
            $next_run_at = $this->formatDateTime($result['next_run_at']);
            if (empty($next_run_at)) {
                throw new waException('Unexpected error');
            }

            $this->response = [
                'next_run_at' => $next_run_at,
                'last_run_at' => $this->formatDateTime(ifempty($result['last_run_at'], date('Y-m-d H:i:s'))),
                'last_success_run_at' => $this->formatDateTime($result['last_success_run_at']),
                'failed_count' => $result['failed_count'],
                'cron_expression' => $result['cron_expression'],
            ];
        } catch (waException $e) {
            $this->errors = [
                'error_code' => $e->getCode(),
                'error_description' => $e->getMessage(),
            ];
        }
    }

    protected function formatDateTime($dt)
    {
        if (empty($dt)) {
            return null;
        }
        $ts = strtotime($dt);
        if (!$ts) {
            return null;
        }
        return waDateTime::format('humandatetime', $ts);
    }
}