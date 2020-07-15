<?php
/**
 * Enables receive of telephony-related WebPush on all backend pages.
 * JS returned by initJs() is added in a <script> at the end of $wa->header() HTML.
 *
 * Also see lib/handlers/webasyst.backend_header.handler.php
 */
class crmPbxActions extends waActions
{
    protected function sdkAction()
    {
        $file = waRequest::param('file', '', 'string');
        switch($file) {
            // Extension is `djs` because default .htaccess file does not route
            // any static extensions to framework.
            case 'init.djs':
                return $this->initJs();

        }

        throw new waException('Not found', 404);
    }

    // /webasyst/crm/pbx/init.djs
    protected function initJs()
    {
        $this->getResponse()->addHeader('Content-type', 'application/javascript');
        $this->display(array(
            'crm_url' => wa()->getAppUrl('crm'),
        ), wa()->getAppPath('templates/actions/pbx/PbxInit.js'));
    }

    // Loaded inside iframe to show list of pending incoming calls
    protected function ifrLayoutAction()
    {
        // Plugins
        $pbx_plugins = wa('crm')->getConfig()->getTelephonyPlugins();

        // Visible contact vaults
        //$rights = new crmRights();
        //$vault_ids = array_fill_keys($rights->getAvailableVaultIds(), true);

        // Funnels data to color deals
        $funnels = $this->getFunnels();

        // Fetch clients and deals for all calls, format numbers etc.
        $client_ids = array();
        $deal_model = new crmDealModel();
        $call_model = new crmCallModel();
        //$deal_participants_model = new crmDealParticipantsModel();
        $calls = $call_model->getOngoingByUser();
        foreach($calls as &$call) {
            $telephony = ifset($pbx_plugins[$call['plugin_id']]);
            $call['user_number'] = $call['plugin_user_number'];
            $call['client_number'] = $call['plugin_client_number'];
            $call['clients_count'] = 0;
            if ($call['client_contact_id']) {
                $client_ids[$call['client_contact_id']] = $call['client_contact_id'];
            }
            if ($telephony) {
                $call['client_number'] = $telephony->formatClientNumber($call['plugin_client_number']);
                $call['user_number'] = $telephony->formatUserNumber($call['plugin_user_number']);

                $call['client'] = null;
                $call['clients'] = array();
                if ($call['client_contact_id']) {
                    $call['client'] = new waContact($call['client_contact_id']);
                    try {
                        $call['client']->getName();
                    } catch (waException $e) {
                        $call['client'] = null;
                    }
                }
                if ($call['client']) {
                    $call['deals'] = array();
                    if ($call['deal_id']) {
                        $call['deals'] = array($deal_model->getById($call['deal_id']));
                    } else {
                        $call['deals'] = $deal_model->getList(array(
                            'check_rights' => true,
                            'participants' => array($call['client_contact_id']),
                            'sort' => 'status_id',
                            'order' => 'ASC',
                            'limit' => 5,
                        ));
                    }
                    foreach ($call['deals'] as &$d) {
                        if (!empty($d['funnel_id']) && !empty($funnels[$d['funnel_id']])) {
                            $f = $d['funnel'] = $funnels[$d['funnel_id']];
                            if (isset($f['stages'][$d['stage_id']])) {
                                $d['stage'] = $f['stages'][$d['stage_id']];
                            }
                        }
                        /*
                        // Role in a deal
                        if ($d) {
                            $d['reminder_state'] = crmHelper::getDealReminderState($d['reminder_datetime']);
                            $d['reminder_title'] = crmHelper::getReminderTitle($d['reminder_state'], $d['reminder_datetime']);
                            $row = $deal_participants_model->getByField(array(
                                'contact_id' => $call['client_contact_id'],
                                'deal_id'    => $d['id'],
                            ));
                            $call['client']['role_id'] = ifset($row['role_id']);
                            $call['client']['role_label'] = ifset($row['label']);
                        }
                        */
                    }
                    unset($d);

                    $call['clients'] = $telephony->findClients($call['plugin_client_number']);

                    $call['clients_count'] = $call['client_contact_id'] ?
                        max(0, count($call['clients']) - 1) : count($call['clients']);
                }
            }
            //$client_ids += array_flip(array_keys($call['clients']));
            $call['client_tags'] = null;
        }
        unset($call);

        // Contact tags
        if ($client_ids) {
            $tm = new crmTagModel();
            $tags = $tm->getByContact(array_keys($client_ids));
            foreach($calls as &$call) {
                $call['client_tags'] = ifset($tags[$call['client']['id']], array());
            }
            unset($call);
        }

        $this->display(array(
            'show_layout' => !waRequest::isXMLHttpRequest(),
            'calls'       => $calls,
            'states'      => $states = wa('crm')->getConfig()->getCallStates()
        ));
    }

    // Helper for ifrLayoutAction()
    protected function getFunnels()
    {
        $funnel_model = new crmFunnelModel();
        $funnels = $funnel_model->getAllFunnels();

        $fsm = new crmFunnelStageModel();
        foreach($fsm->getAll() as $row) {
            if (!isset($funnels[$row['funnel_id']])) {
                continue;
            }
            $funnels[$row['funnel_id']]['stages'][$row['id']] = $row;
        }
        foreach($funnels as &$funnel) {
            $i = 0;
            $funnel['stages'] = ifset($funnel['stages'], array());
            foreach ($funnel['stages'] as $id => &$s) {
                $s['color'] = crmFunnel::getFunnelStageColor($funnel['open_color'], $funnel['close_color'], $i, count($funnel['stages']));
                $i++;
            }
        }
        return $funnels;
    }

    // Background process to clean up pending calls once in a while
    protected function workerAction()
    {
        self::cleanUpCalls();
        $this->displayJson('ok');
    }

    public static function cleanUpCalls()
    {
        // Wait 10 minutes before running it again
        $app_settings_model = new waAppSettingsModel();
        $last_call = $app_settings_model->get('crm', 'pbx_last_cleanup_time', '0');
        if (time() < $last_call + 600) {
            return;
        }
        $app_settings_model->set('crm', 'pbx_last_cleanup_time', time());

        /**
         * @event start_calls_cleanup_worker
         */
        wa('crm')->event('start_calls_cleanup_worker');

        // Loop over list of unfinished calls
        $call_model = new crmCallModel();
        $calls = $call_model->getByField('finish_datetime', null, 'id');
        foreach($calls as $c) {

            // Set `finish_datetime` of calls that are in finished status
            if ($c['status_id'] !== 'PENDING' && $c['status_id'] !== 'CONNECTED') {
                $call_model->updateById($c['id'], array(
                    'finish_datetime' => date('Y-m-d H:i:s'),
                ));
                continue;
            }

            // Don't bother further checking calls that started recently enough
            if (time() - strtotime($c['create_datetime']) < 30*60) {
                continue;
            }

            // When plugin is deleted or broken, default is to finish the call.
            $plugin_result = array(
                'status_id' => $c['status_id'] == 'PENDING' ? 'DROPPED' : 'FINISHED',
                'finish_datetime' => date('Y-m-d H:i:s'),
            );

            // Ask the plugin what to do with the call
            try {
                $plugin = wa('crm')->getConfig()->getTelephonyPlugins($c['plugin_id']);
                if ($plugin) {
                    $plugin_result = $plugin->checkZombieCall($c);
                }
            } catch (Exception $e) {
            }

            if ($plugin_result && is_array($plugin_result)) {
                $call_model->updateById($c['id'], $plugin_result);
            }
        }
    }
}
