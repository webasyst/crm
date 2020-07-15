<?php

class crmTelphinPluginTelephony extends crmPluginTelephony
{
    public function getNumbers()
    {
        $result = $params = array();
        $phones = $this->getApi()->getPhoneExtensions();
        foreach ($phones as $e) {
            $result[$e['name']] = $e['name'].'@'.$e['domain'];
            $params[] = array(
                'plugin_id'          => 'telphin',
                'plugin_user_number' => $e['name'],
                'name'               => 'extension_id',
                'value'              => $e['id'],
            );
        }

        // Delete all records in crm_pbx
        $this->getPbxModel()->deleteByField(
            array(
                'plugin_id' => 'telphin',
            )
        );
        // And in crm_pbx_params:
        $this->getPbxParamsModel()->deleteByField(
            array(
                'plugin_id' => 'telphin',
            )
        );

        // And add new
        $this->getPbxModel()->multipleInsert(
            array(
                'plugin_id'          => 'telphin',
                'plugin_user_number' => array_keys($result),
            )
        );
        // And save extension_id (ex. for call init..) in crm_pbx_params:
        $this->getPbxParamsModel()->multipleInsert($params);

        return $result;
    }

    public function getRecordHref($call)
    {
        return array(
            'href'    => 'javascript:void('.json_encode($call['plugin_record_id']).');',
            'onclick' => 'telphinHandleDownload(event,this,'.json_encode(array(
                    'r' => $call['plugin_record_id'],
                    'c' => $call['plugin_call_id'],
                )).')',
        );
    }

    public function checkZombieCall($call)
    {

        // Is this a finished call?
        try {
            $telphin_call = $this->getApi()->getHistoryCall($call['plugin_call_id']);

            // Convert finish datetime from GMT to server timezone
            $finish_datetime = new DateTime($telphin_call['hangup_time_gmt'], new DateTimeZone('GMT'));
            $finish_datetime->setTimezone(new DateTimeZone(waDateTime::getDefaultTimeZone()));

            // Is there a record uuid?
            $record_uuid = null;
            foreach ($telphin_call['cdr'] as $part) {
                if (!empty($part['record_uuid'])) {
                    $record_uuid = $part['record_uuid'];
                }
            }

            return array(
                'duration'         => $telphin_call['bridged_duration'],
                'finish_datetime'  => $finish_datetime->format('Y-m-d H:i:s'),
                'status_id'        => $telphin_call['bridged_duration'] > 0 ? 'FINISHED' : 'DROPPED',
                'plugin_record_id' => $record_uuid,
            );
        } catch (Exception $e) {
            // Nope, not a finished call
        }

        // Is this an ongoing call?
        $ongoing_calls = $this->getApi()->getOngoingCalls();
        foreach ($ongoing_calls as $c) {

            if ($c['call_flow'] == 'IN') {
                if ($call['direction'] == 'OUT') {
                    continue;
                }
                $plugin_user_number = explode('@', $c['called_extension']['name'], 2);
                $plugin_user_number = $plugin_user_number[0];
                $plugin_client_number = $c['caller_id_number'];
            } else {
                if ($call['direction'] == 'IN') {
                    continue;
                }
                $plugin_user_number = explode('@', $c['caller_extension']['name'], 2);
                $plugin_user_number = $plugin_user_number[0];
                $plugin_client_number = $c['called_number']; // !!! not tested
            }

            if ($call['plugin_user_number'] != $plugin_user_number || $call['plugin_client_number'] != $plugin_client_number) {
                continue;
            }

            // Ok, this seems to be a valid call, don't do anything about it yet
            return null;
        }

        // This is not a valid ongoing call, neither it's a history call
        waLog::dump('Strange call not found in telphin API', $call, 'crm/plugins/telphin.log');
        // Let the app mark it as finished.
        throw new waException('call not found in API');
    }

    public function isRedirectAllowed($call)
    {
        if ($call['status_id'] == "PENDING" || $call['status_id'] == "CONNECTED") {
            return true;
        }
        return false;
    }

    /**
     * Returns candidates to redirect the call.
     *
     * Can use API.
     *
     * The current user should be excluded from the result.
     * @param $call
     * @return array
     */
    public function getRedirectCandidates($call)
    {
        $candidates = $this->getNumbers();
        if (isset($candidates[$call['plugin_user_number']])) {
            unset($candidates[$call['plugin_user_number']]);
        }
        return $candidates;
    }

    /**
     * @param array $call
     * @param string $number - Number to which the call will be redirected.
     * @return array
     * @throws waException
     */
    public function redirect($call, $number)
    {
        $call_params = self::getCallParamsModel()->get($call['id']);

        if (empty($call_params)) {
            return null;
        }

        return $this->getApi()->redirect($call_params['extension_id'], $call_params['call_api_id'], $number);
    }

    protected static function getCallParamsModel()
    {
        static $call_params_model = null;
        if (!$call_params_model) {
            $call_params_model = new crmCallParamsModel();
        }
        return $call_params_model;
    }

    public function isInitCallAllowed()
    {
        return true;
    }

    public function initCall($number_from, $number_to, $call)
    {
        $from_extension = $this->getPbxParamsModel()->getByField(array('plugin_id' => 'telphin', 'plugin_user_number' => $number_from, 'name' => 'extension_id'));
        $this->getApi()->initCall($number_from, $number_to, $from_extension['value'], $call);
    }

    protected function getApi()
    {
        $api = new crmTelphinPluginApi();
        return $api;
    }
}
