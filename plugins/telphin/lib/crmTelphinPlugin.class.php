<?php

class crmTelphinPlugin extends waPlugin
{
    // hander for backend_assets event
    public function backendAssetsHandler(&$params)
    {
        $version = $this->info['version'];
        if (waSystemConfig::isDebug()) {
            $version .= '.'.filemtime($this->path.'/js/telphin.js');
        }
        $sources = array();
        $sources[] = '<link rel="stylesheet" href="'.$this->getPluginStaticUrl().'css/telphin.css?v'.$version.'">';
        $sources[] = '<script type="text/javascript" src="'.$this->getPluginStaticUrl().'js/telphin.js?v'.$version.'"></script>';

        return join("", $sources);
    }

    // hander for backend event pbx_numbers_assigned, pbx_numbers_added, pbx_numbers_deleted
    // called when user saves Settigns -> PBX page
    // When user is assigned to or removed from a telphin number, we need to set up
    // API callbacks accordingly.
    public function pbxNumbersHandler(&$params, $event_name = null)
    {
        /*
            $params represent rows from table on Settings -> PBX page in backend.

            pbx_numbers_assigned: all rows from db table
            array(
              0 => array(
                'plugin_id' => 'telphin',
                'plugin_user_number' => '1111*101',
                'contact_id' => 956651,
              ),
              1 => array(
                'plugin_id' => 'telphin',
                'plugin_user_number' => '1111*102',
                'contact_id' => 1,
              ),
              2 => array(
                'plugin_id' => 'testpbx',
                'plugin_user_number' => '89994445566',
                'contact_id' => 124057,
              ),
            )

            pbx_numbers_added: list of rows added
            pbx_numbers_deleted: list of rows deleted
        */

        try {
            $api = new crmTelphinPluginApi();
            $telphin_extensions = $api->getPhoneExtensions();
        } catch (waException $e) {
            return;
        }

        $extensions_add_callback = array();
        $extensions_remove_callback = array();

        switch ($event_name) {
            case 'pbx_numbers_deleted':

                // Candidates for callback removal: if there are no users assigned to the extension,
                // we need to call API and stop receiving callbacks about those numbers.
                $extensions_remove_callback = array();
                foreach($params as $row) {
                    if ($row['plugin_id'] == $this->id) {
                        $extensions_remove_callback[$row['plugin_user_number']] = $row['plugin_user_number'];
                    }
                }

                // For each candidate, check if there are other users assigned to it
                $pbx_users_model = new crmPbxUsersModel();
                $rows = $pbx_users_model->getByField(array(
                    'plugin_id' => 'telphin',
                    'plugin_user_number' => array_values($extensions_remove_callback),
                ), true);
                foreach($rows as $row) {
                    unset($extensions_remove_callback[$row['plugin_user_number']]);
                }

                break;

            case 'pbx_numbers_added':

                // Make a list of numbers to ensure we're subscribed for callbacks for
                foreach($params as $row) {
                    if ($row['plugin_id'] == $this->id) {
                        $extensions_add_callback[$row['plugin_user_number']] = $row['plugin_user_number'];
                    }
                }

                break;

            case 'pbx_numbers_assigned':

                //
                // For this event, $params contains all rows from crm_pbx table.
                // We should subscribe for what is used and unsubscribe for what is not used.
                //

                foreach($telphin_extensions as $ext) {
                    $extensions_remove_callback[$ext['name']] = $ext['name'];
                }

                foreach($params as $row) {
                    if ($row['plugin_id'] == $this->id) {
                        $extensions_add_callback[$row['plugin_user_number']] = $row['plugin_user_number'];
                        unset($extensions_remove_callback[$row['plugin_user_number']]);
                    }
                }

                break;

            default:
                return; // unknown event
        }

        // Add and remove callbacks according to $extensions_add_callback, $extensions_remove_callback
        foreach($telphin_extensions as $ext) {

            if (!isset($extensions_remove_callback[$ext['name']]) && !isset($extensions_add_callback[$ext['name']])) {
                continue;
            }

            // Type of events to be added for this extension
            $events = array();
            if (isset($extensions_add_callback[$ext['name']])) {
                $events = array(
                    'dial-in'  => 'dial-in',  // incoming call initiated
                    'dial-out' => 'dial-out', // outgoing call initiated
                    'answer'   => 'answer',   // call started
                    'hangup'   => 'hangup',   // call ended
                );
            }

            // Read existing events of an extension and figure out what to do with them:
            // delete what should be deleted, keep what should be kept.
            foreach($api->getExtEvents($ext['id']) as $event) {
                try {
                    switch($api->isCallbackUrl($event['url'])) {
                        case 'old':
                            // Clear old events after API key changed
                            $api->deleteExtEvent($ext['id'], $event['id']);
                            break;
                        case 'current':
                            if (isset($events[$event['event_type']])) {
                                unset($events[$event['event_type']]);
                            } else {
                                $api->deleteExtEvent($ext['id'], $event['id']);
                            }
                            break;
                    }
                } catch (waException $e) {
                    $log = array('Error deleting extension event in pbx_numbers_assigned handler');
                    $log[] = $e->getMessage();
                    $log[] = $e->getFullTraceAsString();
                    waLog::log(join("\n", $log), 'crm/plugins/telphin.log');
                }
            }

            // Subscribe for events for this ext, if needed
            foreach($events as $event_type) {
                try {
                    $api->createExtEvent($ext['id'], $event_type);
                } catch (waException $e) {
                    $log = array('Error creating extension event in pbx_numbers_assigned handler');
                    $log[] = $e->getMessage();
                    $log[] = $e->getFullTraceAsString();
                    waLog::log(join("\n", $log), 'crm/plugins/telphin.log');
                }
            }

        }
    }

    // Handler for start_calls_cleanup_worker.
    // This runs periodic (cron-based) cleanup tasks.
    public function callsCleanupHandler(&$params=null)
    {
        $this->deleteDroppedDuplicates();
        $this->fetchDelayedRecordIds();
    }

    // Single client's call can be routed to several user ext numbers.
    // We keep several records with the same `crm_call.plugin_call_id`,
    // while the call is pending.
    // As soon as the call is answered, we delete duplicates. This is done in
    // crmTelphinPluginFrontendCallbackController->deletePendingDuplicates().
    // This routine, on the other hand, handles duplicates in case call is never answered.
    protected function deleteDroppedDuplicates()
    {
        $call_model = new crmCallModel();
        $call_params_model = new crmCallParamsModel();
        $log_model = new crmLogModel();

        // Any calls scheduled for cleanup?
        $sql = "SELECT id, plugin_call_id
                FROM crm_call AS c
                    JOIN crm_call_params AS cp
                        ON cp.call_id=c.id
                WHERE c.plugin_id='telphin'
                    AND c.finish_datetime < ?
                    AND cp.name='need_cleanup'";
        $call_ids = array();
        $plugin_call_ids = array();
        foreach($call_model->query($sql, date('Y-m-d H:i:s')) as $row) {
            $plugin_call_ids[$row['plugin_call_id']] = $row['plugin_call_id'];
            $call_ids[$row['id']] = $row['id'];
        }
        if (!$plugin_call_ids) {
            return;
        }

        // Any scheduled calls have duplicates?
        $sql = "SELECT plugin_call_id, count(*) AS cnt
                FROM crm_call
                WHERE plugin_id = 'telphin'
                    AND plugin_call_id IN (?)
                GROUP BY plugin_call_id
                HAVING cnt > 1";
        $plugin_call_ids = array_keys(
            $call_model->query($sql, array($plugin_call_ids))->fetchAll('plugin_call_id')
        );
        if (!$plugin_call_ids) {
            return;
        }

        // For each call that has duplicates,
        // select the master copy to protect from deletion
        $master_ids = array();
        foreach($plugin_call_ids as $plugin_call_id) {
            $master_id = $call_model->select('id')
                ->where("plugin_id = 'telphin'")
                ->where('plugin_call_id=?', $plugin_call_id)
                ->order("duration DESC, FIELD(status_id, 'REDIRECTED', 'FINISHED', 'CONNECTED') DESC, id")
                ->limit(1)->fetchField();
            $master_ids[] = $master_id;
        }

        if ($plugin_call_ids && $master_ids) {
            // Select all duplicates
            $duplicate_call_ids = array_keys($call_model->query(
                "SELECT id FROM crm_call
                 WHERE plugin_id = 'telphin'
                    AND duration <= 0
                    AND status_id IN ('PENDING', 'DROPPED')
                    AND plugin_call_id IN (?)
                    AND id NOT IN (?)",
                $plugin_call_ids,
                $master_ids
            )->fetchAll('id'));

            // Delete duplicates and their params
            $call_model->deleteById($duplicate_call_ids);
            $call_params_model->deleteByField(array(
                'call_id' => $duplicate_call_ids,
            ));
            $log_model->deleteByField(array(
                'object_id' => $duplicate_call_ids
            ));
        }

        // Processed calls no longer require any more cleanup in future
        $call_params_model->deleteByField(array(
            'name' => 'need_cleanup',
            'call_id' => $call_ids,
        ));
    }


    //
    // Experience shows that API call record is sometimes not ready yet
    // at the time of hangup callback. Normally callback saves ID into
    // crm_call.plugin_record_id, but about 1 out of 20 calls it doesn't.
    // This method is called via cron to fetch missed records later.
    // (declared public, and also parameters are for unit tests)
    //
    public function fetchDelayedRecordIds($api = null, $last_call = null)
    {
        if (empty($last_call)) {
            $app_settings_model = new waAppSettingsModel();
            $last_call = $app_settings_model->get(array('crm', 'telphin'), 'last_cleanup_time', null);
            $app_settings_model->set(array('crm', 'telphin'), 'last_cleanup_time', time());
            if (!$last_call) {
                return;
            }
        }

        // Fetch a list of calls with no record ids,
        // making sure at least 5 minutes passed since call is finished
        $call_model = new crmCallModel();
        $sql = "SELECT *
                FROM crm_call
                WHERE plugin_id='telphin'
                    AND plugin_record_id IS NULL
                    AND finish_datetime >= ?
                    AND finish_datetime < ?
                LIMIT 20";
        $calls = $call_model->query($sql,
            date('Y-m-d H:i:s', $last_call - 300),
            date('Y-m-d H:i:s', time() - 300)
        )->fetchAll();

        if (empty($calls)) {
            return;
        }

        if (!$api) {
            try {
                $api = new crmTelphinPluginApi();
            } catch (waException $e) {
                return;
            }
        }

        foreach($calls as $call) {
            try {
                $telphin_call = $api->getHistoryCall($call['plugin_call_id']);
            } catch (waException $e) {
                continue;
            }
            foreach(ifset($telphin_call['cdr'], array()) as $subcall) {
                if (!empty($subcall['record_uuid'])) {
                    $call_model->updateById($call['id'], array(
                        'plugin_record_id' => $subcall['record_uuid'],
                    ));
                    continue 2;
                }
            }
        }
    }

}
