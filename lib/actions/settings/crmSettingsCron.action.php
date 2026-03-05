<?php

class crmSettingsCronAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $cm = new waWebasystIDClientManager();
        $is_waid_connected = $cm->isConnected();
        $is_cron_service_available = $is_waid_connected && $this->isCronServiceAvailable();

        $cron_config = wa('crm')->getConfig()->getCron();
        $scheduled = [];
        $waid_error = '';
        try {
            $scheduled = $is_waid_connected && $is_cron_service_available ? (new waServicesApi)->getJobs('crm') : [];
        } catch (waException $e) {
            $waid_error = $e->getMessage();
        }
        
        $scheduled = array_reduce($scheduled, function($res, $item) {
            $res[$item['action']] = $item;
            return $res;
        }, []);

        $cron_actions = array_map(function($alias, $cron) use ($scheduled) {
            $last_run_ts = $cron['last_call_ts'] = waCronController::lastRunTs('crm', $alias);
            if (!empty($cron['legacy_cli']) && $last_cli_ts = crmWorkerCron::lastLegacyRunTs($cron['legacy_cli'])) {
                if (empty($last_run_ts) || $last_cli_ts > $last_run_ts) {
                    $last_run_ts = $last_cli_ts;
                    $cron['legacy_cli_run'] = true;
                }
            }

            $cron['last_call_datetime'] = empty($last_run_ts) ? '' : date('Y-m-d H:i:s', $last_run_ts);
            $cron['alias'] = $alias;
            $cron['action'] = ifset($cron['action'], $alias);
            $cron['scheduled'] = ifset($scheduled[$alias], null);

            $cron['is_healthy'] = !empty($last_run_ts) 
                && waCronController::nextRunAt($cron['expression'], $last_run_ts) > strtotime('- 1 day') 
                && waCronController::nextRunAt($cron['expression'], $last_run_ts, 5) > time();
            return $cron;
        }, array_keys($cron_config), $cron_config);

        $this->view->assign([
            'is_waid'         => $is_waid_connected,
            'is_cron_service' => $is_cron_service_available,
            'waid_error'      => $waid_error,
            'cron_actions'    => $cron_actions,
            'has_non_halthy'  => !empty(array_filter($cron_actions, function($cron) {
                return !$cron['is_healthy'];
            })),
            'root_path'       => $this->getConfig()->getRootPath() . DIRECTORY_SEPARATOR
        ]);
    }

    protected function isCronServiceAvailable()
    {
        $config = new waServicesApiUrlConfig();
        $provider = new waServicesUrlsProvider([
            'config' => $config
        ]);
        $url = $provider->getServiceUrl('CRON');
        return !empty($url);
    }
}
