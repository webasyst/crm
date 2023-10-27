<?php

/**
 * This is a frontend controller accepting callbacks from Zebra Telecom
 * when new call is initiated or existing call changes status.
 *
 * Zebra Telecom documents the following vars via POST:
 * @see https://www.zebratelecom.ru/help/api/webhooks/
 */
class crmZebratelecomPluginFrontendCallbackController extends waController
{
    public $plugin_id = "zebratelecom";
    public $auth_token;
    public $call_body;

    public function execute()
    {
        $this->auth_token = wa()->getSetting('auth_token', '', array('crm', $this->plugin_id));
        $this->call_body = waRequest::post();

        if (isset($this->call_body['hook_event']) && $this->call_body['call_direction'] == 'outbound') {
            switch ($this->call_body['hook_event']) {
                case 'channel_create':
                    $this->handleNewCall();
                    break;
                case 'channel_answer':
                    $this->handleAnswer();
                    break;
                case 'channel_destroy':
                    $this->handleHangup();
                    break;
            }
        }
    }

    protected function handleNewCall()
    {
        $this->dumpLog($this->call_body);

        // !!!!!! outgoing via api call tracking
        if (isset($this->call_body['authorizing_id'])) {
            $device_number = $this->getPbxParamsModel()->getByField(array(
                'plugin_id' => $this->plugin_id,
                'name'      => 'device_id',
                'value'     => $this->call_body['authorizing_id'],
            ));
        } else {
            return;
        }

        // Get created call (with call_id expected)
        $system_call = $this->getCallModel()->getByField(array(
            'direction'          => 'OUT',
            'status_id'          => 'PENDING',
            'plugin_id'          => $this->plugin_id,
            'plugin_user_number' => $device_number['plugin_user_number'],
            'plugin_call_id'     => 'expected',
        ));
        if ($system_call) {
            $id = $system_call['id'];
            $this->getCallModel()->updateById($id, array(
                'status_id'         => 'PENDING',
                'plugin_call_id'    => $this->call_body['call_id'],
                'plugin_gateway'    => $this->call_body['callee_id_number'],
                'finish_datetime'   => null,
                'plugin_record_id'  => null,
                'notification_sent' => 0,
                'duration'          => null,
            ));
            self::getCallParamsModel()->set($id, array('bridge_id' => ifset($this->call_body['other_leg_call_id']))); // need for getting call record
            self::getCallModel()->handleCalls(array($id));
            if ($this->isZebraDebug()) {
                $this->dumpLog('handleCalls() done');
            }
            return;
        }

        // Get call for update plugin_call_id
        $updated_call = $this->getCallModel()->getByField(array(
            'direction'          => 'OUT',
            'status_id'          => 'CONNECTED',
            'plugin_id'          => $this->plugin_id,
            'plugin_call_id'     => ifset($this->call_body['other_leg_call_id']),
            'plugin_user_number' => $device_number['plugin_user_number'],
        ));
        if ($updated_call && $updated_call['status_id'] == 'PENDING' || $updated_call['status_id'] == 'CONNECTED') {
            $id = $updated_call['id'];
            $this->getCallModel()->updateById($id, array(
                'plugin_call_id' => $this->call_body['call_id'],
                'status_id'      => 'PENDING',
            ));
            return;
        }

        try {
            $plugin_numbers = self::getApi()->getPbxNumbers();
        } catch (waException $e) {}

        if ($this->crmPbxUsers($this->call_body['to'])) {
            $call_data = array(
                'direction' => 'IN',
            );
        } elseif ($this->crmPbxUsers($this->call_body['from'])) {
            $call_data = array(
                'direction' => 'OUT',
            );
        } elseif (isset($plugin_numbers) && in_array($this->call_body['to'], $plugin_numbers)) {
            $call_data = array(
                'direction' => 'IN',
            );
        } else {
            $call_data = array(
                'direction' => 'OUT',
            );
        }

        /* ~M~A~G~I~C~ */
        if ($call_data['direction'] == 'IN') {
            $call_data += array(
                'plugin_user_number'   => $device_number['plugin_user_number'],
                'plugin_client_number' => $this->call_body['caller_id_number'],
            );
        } else {
            $call_data += array(
                'plugin_user_number'   => $device_number['plugin_user_number'],
                'plugin_client_number' => $this->call_body['callee_id_number'],
            );
        }
        /* ~~~~~~~~~~~ */

        $call_data += array(
            'plugin_id'       => $this->plugin_id,
            'plugin_call_id'  => $this->call_body['call_id'],
            'create_datetime' => date('Y-m-d H:i:s'),
            'status_id'       => 'PENDING',
        );

        // Make sure call id is unique
        if ($call_data['direction'] == 'OUT') {
            $existing_call = self::getCallModel()->getByField(array(
                'plugin_id'          => $this->plugin_id,
                'plugin_user_number' => $call_data['plugin_user_number'],
                'status_id'          => array('PENDING', 'CONNECTED'),
            ));
        } else {
            $existing_call = self::getCallModel()->getByField(array(
                'plugin_id'          => $this->plugin_id,
                'plugin_call_id'     => $call_data['plugin_call_id'],
                'plugin_user_number' => $call_data['plugin_user_number'],
            ));
        }

        if ($existing_call) {
            // Additional dial-ins sometimes come for existing calls when call
            // is routed to the same extension repeatedly.
            $id = $existing_call['id'];
            self::getCallModel()->updateById($id, array(
                'status_id'         => 'PENDING',
                'finish_datetime'   => null,
                'notification_sent' => 0,
            ));
            if ($this->isZebraDebug()) {
                $this->dumpLog('Existing record updated, id='.$id);
            }
        } else {
            // Insert new call to db
            $id = self::getCallModel()->insert($call_data);
            self::getCallParamsModel()->set($id, array('bridge_id' => ifset($this->call_body['other_leg_call_id']))); // need for getting call record
            if ($this->isZebraDebug()) {
                $this->dumpLog('New record created, id='.$id);
            }
        }

        self::getCallModel()->handleCalls(array($id));
        if ($this->isZebraDebug()) {
            $this->dumpLog('handleCalls() done');
        }
    }

    protected function handleAnswer()
    {
        $this->dumpLog($this->call_body);
        $user_number = $this->getPbxParamsModel()->getByField(array(
            'plugin_id' => $this->plugin_id,
            'name'      => 'device_id',
            'value'     => $this->call_body['authorizing_id'],
        ));
        $call = self::getBestMatchingCall($user_number['plugin_user_number']);
        if (!$call) {
            $this->dumpLog('Received ANSWER frontend callback for unknown CallID');
            return;
        }

        self::getCallModel()->updateById($call['id'], array(
            'plugin_user_number' => $user_number['plugin_user_number'],
            'status_id'          => 'CONNECTED',
        ));
        self::deletePendingDuplicates($call);
        self::getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleHangup()
    {
        $this->dumpLog($this->call_body);
        $call = $this->getCallModel()->getByField(array(
            'plugin_id' => $this->plugin_id,
            'plugin_call_id' => $this->call_body['call_id'],
        ));

        if (!$call) {
            return;
        }

        $user_number = $this->getPbxParamsModel()->getByField(array(
            'plugin_id' => $this->plugin_id,
            'name'      => 'device_id',
            'value'     => $this->call_body['authorizing_id'],
        ));

        if ($call['status_id'] == "REDIRECTED") {
            return;
        }

        if (!$call || $call['plugin_user_number'] != $user_number['plugin_user_number']) {
            if ($this->isZebraDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        $call_data = array(
            'finish_datetime' => date('Y-m-d H:i:s'),
            'duration'        => $this->call_body['duration_seconds'],
            'status_id'       => 'FINISHED',
        );

        // Getting call record url
        try {
            $bridge_id = self::getCallParamsModel()->getOne($call['id'], 'bridge_id');
            $rec_url = self::getApi()->getRecordUrl($this->call_body['timestamp'], $bridge_id);
            if ($rec_url) {
                $call_data['plugin_record_id'] = $rec_url;
            }
        } catch (waException $e) {}

        if ($call['status_id'] == 'PENDING') {
            $call_data['status_id'] = 'DROPPED';
            $call_data['duration'] = null;
            $call_data['plugin_record_id'] = null;
        }

        $this->getCallModel()->updateById($call['id'], $call_data);
        $this->getCallModel()->handleCalls(array($call['id']));

        self::getCallParamsModel()->delete($call['id']);
    }

    // Deduce user extension number from POST
    protected function getUserNumber()
    {
        $plugin_numbers = self::getApi()->getPbxNumbers();

        if ($this->crmPbxUsers($this->call_body['to'])) {
            $number = $this->call_body['to'];
        } elseif ($this->crmPbxUsers($this->call_body['from'])) {
            $number = $this->call_body['from'];
        } elseif (array_search($this->call_body['to'], $plugin_numbers)) {
            $number = $this->call_body['to'];
        } else {
            $number = $this->call_body['from'];
        }

        return $number;
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
            'plugin_call_id' => $this->call_body['call_id'],
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

    protected static function getPbxParamsModel()
    {
        static $pbx_params_model = null;
        if (!$pbx_params_model) {
            $pbx_params_model = new crmPbxParamsModel();
        }
        return $pbx_params_model;
    }

    protected static function crmPbxUsers($number)
    {
        $pbx_users_model = new crmPbxUsersModel();
        $sql = "SELECT * FROM `crm_pbx`
                WHERE `plugin_id` = 'zebratelecom'
                  AND `plugin_user_number` LIKE '%".$pbx_users_model->escape($number, 'like')."%'";

        return $pbx_users_model->query($sql)->fetchAll();
    }

    protected static function getApi()
    {
        static $api = null;
        if (!$api) {
            $api = new crmZebratelecomPluginApi();
        }
        return $api;
    }

    protected function dumpLog($message)
    {
        waLog::dump($message, 'crm/plugins/'.$this->plugin_id.'.log');
    }

    protected function fatal()
    {
        exit; // overriden in unit tests
    }

    protected function isZebraDebug()
    {
        return defined('CRM_'.mb_strtoupper($this->plugin_id).'_DEBUG'); // overriden in unit tests
    }
}
