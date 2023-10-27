<?php

/**
 * This is a frontend controller accepting callbacks from Mango Office (Telecom)
 * when new call is initiated or existing call changes status.
 * @see https://www.mango-office.ru/upload/api/MangoOffice_VPBX_API_v1.8.pdf
 */
class crmMangoPluginFrontendCallbackController extends waController
{
    public $plugin_id = "mango";
    public $api_key;
    public $sign_key;

    public $event_type;
    public $event_name;
    public $call_body;
    public $call_id;

    public function execute()
    {
        $this->api_key = wa()->getSetting('api_key', '', array('crm', $this->plugin_id));
        $this->sign_key = wa()->getSetting('sign_key', '', array('crm', $this->plugin_id));

        $this->event_type = waRequest::param('event_type', null, 'string');
        $this->event_name = waRequest::param('event_name', null, 'string');
        $this->call_body = json_decode(waRequest::post('json', null), JSON_FORCE_OBJECT);
        $this->call_id = $this->call_body['call_id'];

        $my_sign = $this->getSign($this->call_body);
        $sign = waRequest::post('sign', null, 'string');

        $this->dumpLog($this->call_body);

        if ($sign !== $my_sign) {
            return;
        }

        if ($this->event_type == "events" && $this->event_name == "call") {
            switch ($this->call_body['call_state']) {
                case 'Appeared':
                    $this->handleNewCall();
                    break;
                case 'Connected':
                    $this->handleAnswer();
                    break;
                case 'Disconnected':
                    $this->handleHangup();
                    break;
                case 'OnHold':
                    $this->handleOnHold();
                    break;
            }
        }
        if ($this->event_type == "events" && $this->event_name == "recording" && $this->call_body['recording_state'] == "Completed") {
            $this->setRecordId();
        }
    }

    protected function handleNewCall()
    {
        // !!!!!! outgoing via api call tracking
        if (isset($this->call_body['command_id']) && isset($this->call_body['from']['taken_from_call_id'])) {
            $this->getCallModel()->updateById($this->call_body['command_id'], array(
                'plugin_call_id' => $this->call_body['call_id'],
                'status_id'      => 'PENDING',
            ));
            return;
        }
        if (isset($this->call_body['command_id'])) {
            $expected_call = $this->getCallModel()->getByField(array('id' => $this->call_body['command_id'], 'plugin_call_id' => 'expected'));
            if ($expected_call) {
                $this->getCallModel()->updateById($expected_call['id'], array('plugin_call_id' => $this->call_body['call_id']));
            }
            return;
        }

        // Make sure it's not an internal call
        if (isset($this->call_body['from']['extension']) && isset($this->call_body['to']['extension'])) {
            return; // Ignore internal calls
        }

        if ($this->call_body['seq'] == 1 && $this->call_body['location'] == 'ivr' || isset($this->call_body['to']['line_number'])) {
            $call_data = array(
                'direction'            => 'IN',
                'plugin_user_number'   => $this->call_body['to']['number'],
                'plugin_client_number' => $this->call_body['from']['number'],
                'plugin_gateway'       => ifset($this->call_body['to']['line_number']),
            );
        } else {
            $call_data = array(
                'direction'            => 'OUT',
                'plugin_user_number'   => $this->call_body['from']['extension'],
                'plugin_client_number' => $this->call_body['to']['number'],
            );
        }

        $call_data += array(
            'plugin_id'       => $this->plugin_id,
            'plugin_call_id'  => $this->call_body['call_id'],
            'create_datetime' => date('Y-m-d H:i:s'),
            'status_id'       => 'PENDING',
        );

        if (isset($this->call_body['from']['taken_from_call_id'])) {
            // Update status_id for main call:
            $this->getCallModel()->updateByField(
                array(
                    'plugin_id'      => $this->plugin_id,
                    'plugin_call_id' => $this->call_body['from']['taken_from_call_id'],
                ),
                array(
                    'status_id' => 'REDIRECTED'
                )
            );

            // a new transferred call
            $call_data['plugin_user_number'] = $this->call_body['to']['extension'];
            $call_data['plugin_client_number'] = $this->call_body['from']['number'];

            // If this is not a group call and it's incoming, look for the parent call and update plugin_user_number
            // And at the same time and plugin_call_id
            if (!isset($this->call_body['to']['acd_group']) && $call_data['direction'] == 'IN') {
                $main_call = $this->getCallModel()->getByField(
                    array(
                        'status_id'          => 'REDIRECTED',
                        'plugin_call_id'     => $this->call_body['from']['taken_from_call_id'],
                        'plugin_user_number' => $this->call_body['to']['line_number'],
                    )
                );
                if ($main_call) {
                    $this->getCallModel()->updateById($main_call['id'], array(
                            'status_id'          => 'PENDING',
                            'plugin_call_id'     => $this->call_id,
                            'plugin_user_number' => ifset($this->call_body['to']['extension']),
                            'notification_sent'  => 0,
                        )
                    );
                    self::getCallModel()->handleCalls(array($main_call['id']));
                }
            }

            // If this is an incoming call that is allocated to a group, then do not update the id.
            // but we are waiting for someone from the group to pick up the phone.
            if (isset($this->call_body['to']['acd_group']) && $this->call_body['seq'] == 1 && $call_data['direction'] == 'IN') {
                return;
            }
        }

        // Make sure call id is unique
        $existing_call = self::getCallModel()->getByField(array(
            'plugin_id'          => $this->plugin_id,
            'plugin_call_id'     => $call_data['plugin_call_id'],
        ));
        if ($existing_call) {
            // Additional dial-ins sometimes come for existing calls when call
            // is routed to the same extension repeatedly.
            $id = $existing_call['id'];
            self::getCallModel()->updateById($id, array(
                'status_id'         => 'PENDING',
                'finish_datetime'   => null,
                'notification_sent' => 0,
            ));
            if ($this->isMangoDebug()) {
                $this->dumpLog('Existing record updated, id='.$id);
            }
        } else {
            // Insert new call to db
            $id = self::getCallModel()->insert($call_data);
            if ($this->isMangoDebug()) {
                $this->dumpLog('New record created, id='.$id);
            }
        }

        self::getCallModel()->handleCalls(array($id));
        if ($this->isMangoDebug()) {
            $this->dumpLog('handleCalls() done');
        }
    }

    protected function handleAnswer()
    {
        // Let's find the bell
        if (isset($this->call_body['from']['taken_from_call_id']) && isset($this->call_body['to']['acd_group'])) {
            $call = $this->getCallModel()->getByField(
                array(
                    'plugin_call_id'       => $this->call_body['from']['taken_from_call_id'],
                    'plugin_client_number' => $this->call_body['from']['number'],
                )
            );
            if ($call) {
                $this->getCallModel()->updateById($call['id'],
                    array(
                        'status_id'          => 'CONNECTED',
                        'plugin_call_id'     => $this->call_id,
                        'plugin_user_number' => $this->call_body['to']['extension'],
                        'notification_sent'  => 0,
                    )
                );
                self::getCallModel()->handleCalls(array($call['id']));
            } else {
                $this->dumpLog('Received ANSWER frontend callback for unknown CallID');
                return;
            }
        } else {
            $user_number = self::getUserNumber();
            $call = self::getBestMatchingCall($user_number);
            if (!$call) {
                $this->dumpLog('Received ANSWER frontend callback for unknown CallID');
                return;
            }

            self::getCallModel()->updateById($call['id'], array(
                'plugin_user_number' => $user_number,
                'status_id'          => 'CONNECTED',
            ));
            self::deletePendingDuplicates($call);
            self::getCallModel()->handleCalls(array($call['id']));
        }
    }

    protected function handleHangup()
    {
        $user_number = $this->getUserNumber();
        $call = $this->getBestMatchingCall($user_number);

        if (!$call) {
            if ($this->isMangoDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        if ($call['status_id'] == "REDIRECTED") {
            return;
        }

        $call_data = array(
            'finish_datetime' => date('Y-m-d H:i:s'),
            'duration'        => time() - strtotime($call['create_datetime']),
            'status_id'       => 'FINISHED',
        );

        if ($call['status_id'] == 'PENDING' && $this->call_body['seq'] == 2) {
            $call_data['status_id'] = 'DROPPED';
            $call_data['duration'] = null;
        }

        $this->getCallModel()->updateById($call['id'], $call_data);
        $this->getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleOnHold()
    {
        $user_number = self::getUserNumber();
        $call = self::getBestMatchingCall($user_number);
        if (!$call) {
            $this->dumpLog('Received ANSWER frontend callback for unknown CallID');
            return;
        }

        if ($call['status_id'] == "FINISHED") {
            return;
        }

        self::getCallModel()->updateById($call['id'], array(
            'plugin_user_number' => $user_number,
            'status_id'          => 'PENDING',
        ));
        self::deletePendingDuplicates($call);
        self::getCallModel()->handleCalls(array($call['id']));
    }

    protected function setRecordId()
    {
        if (empty($this->call_body['recording_id'])) {
            return;
        }

        $this->getCallModel()->updateByField('plugin_call_id', $this->call_id, array(
            'plugin_record_id' => $this->call_body['recording_id'],
        ));
    }

    // Deduce user extension number from POST
    protected function getUserNumber()
    {
        if (isset($this->call_body['to']['extension'])) {
            return $this->call_body['to']['extension'];
        } else {
            return $this->call_body['from']['extension'];
        }
    }

    /*
       When a single client call is routed to several user extension numbers in parallel,
       there are several records with the same `crm_call.plugin_call_id` in DB.
       This helper function selects the one matching user extension.
    */
    protected function getBestMatchingCall($user_number)
    {
        $calls = $this->getCallModel()->getByField(array(
            'plugin_id'      => $this->plugin_id,
            'plugin_call_id' => $this->call_id,
        ), true);
        if (!$calls) {
            return null;
        }

        $ext_match = null;
        foreach ($calls as $call) {
            if ($call['plugin_user_number'] == $user_number) {
                $ext_match = $call;
                break;
            }
        }

        return ifset($ext_match, reset($calls));
    }

    /*
       Single client's call can be routed to several user ext numbers.
       We keep several records with the same `crm_call.plugin_call_id`,
       while the call is pending.
       As soon as the call is answered, we delete duplicates.
    */
    protected function deletePendingDuplicates($call)
    {
        $this->getCallModel()->exec(
            "DELETE FROM crm_call
                 WHERE plugin_id = '{$this->plugin_id}'
                    AND plugin_call_id = ?
                    AND status_id IN ('PENDING', 'DROPPED')
                    AND id <> ?",
            $call['plugin_call_id'],
            $call['id']
        );
    }

    /**
     * Creation of a signature for exchange and verification of data Mango API
     * @param array $data
     * @return string
     */
    protected function getSign($data)
    {
        $json = json_encode($data);
        return hash('sha256', $this->api_key.$json.$this->sign_key);
    }

    protected static function getCallModel()
    {
        static $call_model = null;
        if (!$call_model) {
            $call_model = new crmCallModel();
        }
        return $call_model;
    }

    protected static function getCallParamsModel()
    {
        static $call_params_model = null;
        if (!$call_params_model) {
            $call_params_model = new crmCallParamsModel();
        }
        return $call_params_model;
    }

    protected function dumpLog($message)
    {
        waLog::dump($message, 'crm/plugins/'.$this->plugin_id.'.log');
    }

    protected function fatal()
    {
        exit; // overriden in unit tests
    }

    protected function isMangoDebug()
    {
        return defined('CRM_'.mb_strtoupper($this->plugin_id).'_DEBUG'); // overriden in unit tests
    }
}
