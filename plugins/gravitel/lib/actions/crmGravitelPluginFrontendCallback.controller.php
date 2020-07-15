<?php


class crmGravitelPluginFrontendCallbackController extends waController
{
    public $plugin_id = "gravitel";
    public $crm_key;

    public $cb_data;

    public function execute()
    {
        $this->crm_key = wa()->getSetting('crm_key', '', array('crm', $this->plugin_id));
        $this->cb_data = waRequest::request(null, array(), waRequest::TYPE_ARRAY);

        $this->dumpLog($this->cb_data);

        if ($this->crm_key !== ifset($this->cb_data['crm_token'])) {
            $this->dumpLog('Received a callback with an invalid crm_token');
            header("HTTP/1.0 401 Invalid token");
            echo json_encode(array('error' => 'Invalid token'));
        }

        if (isset($this->cb_data['cmd']) && $this->cb_data['cmd'] == 'event') {
            switch ($this->cb_data['type']) {
                case 'INCOMING':
                case 'OUTGOING':
                    $this->handleNewCall();
                    break;
                case 'ACCEPTED':
                    $this->handleAnswer();
                    break;
                case 'COMPLETED':
                case 'CANCELLED':
                    $this->handleHangup();
                    break;
            }
        }

        if (isset($this->cb_data['cmd']) && $this->cb_data['cmd'] == 'history' && !empty($this->cb_data['link'])) {
            $this->handleRecord();
        }
    }

    protected function handleNewCall()
    {
        if ($this->cb_data['type'] == 'OUTGOING') {
            $call_data = array(
                'direction' => 'OUT',
            );
        } else {
            $call_data = array(
                'direction'      => 'IN',
                'plugin_gateway' => $this->cb_data['diversion'],
            );
        }

        $call_data += array(
            'plugin_user_number'   => strstr($this->cb_data['user'], "@", true), // Alternative: define by ext number, but in the documentation this is not a required parameter
            'plugin_client_number' => $this->cb_data['phone'],
            'plugin_id'            => $this->plugin_id,
            'plugin_call_id'       => $this->cb_data['callid'],
            'create_datetime'      => date('Y-m-d H:i:s'),
            'status_id'            => 'PENDING',
        );

        // Make sure call id is unique
        $existing_call = self::getCallModel()->getByField(array(
            'plugin_id'          => $this->plugin_id,
            'plugin_call_id'     => $call_data['plugin_call_id'],
            'plugin_user_number' => $call_data['plugin_user_number'],
        ));
        if ($existing_call) {
            // Additional dial-ins sometimes come for existing calls when call
            // is routed to the same extension repeatedly.
            $id = $existing_call['id'];
            self::getCallModel()->updateById($id, array(
                'status_id'         => 'PENDING',
                'finish_datetime'   => null,
                'plugin_record_id'  => null,
                'notification_sent' => 0,
                'duration'          => null,
            ));
            if ($this->isGravitelDebug()) {
                $this->dumpLog('Existing record updated, id='.$id);
            }
        } else {
            // Insert new call to db
            $id = self::getCallModel()->insert($call_data);
            if ($this->isGravitelDebug()) {
                $this->dumpLog('New record created, id='.$id);
            }
        }

        self::getCallModel()->handleCalls(array($id));
        if ($this->isGravitelDebug()) {
            $this->dumpLog('handleCalls() done');
        }
    }

    protected function handleAnswer()
    {
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['callid']));
        if (!$call) {
            if ($this->isGravitelDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        self::getCallModel()->updateById($call['id'], array(
            'status_id' => 'CONNECTED',
        ));
        self::deletePendingDuplicates($call);
        self::getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleHangup()
    {
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['callid']));
        if (!$call) {
            if ($this->isGravitelDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        $call_data['finish_datetime'] = date('Y-m-d H:i:s');

        $call_data['status_id'] = $this->cb_data['type'] == 'COMPLETED' ? 'FINISHED' : 'DROPPED';
        $call_data['duration'] = $call_data['status_id'] == 'FINISHED' ? time() - strtotime($call['create_datetime']) : null;

        self::getCallModel()->updateById($call['id'], $call_data);
        self::getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleRecord()
    {
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['callid']));
        if (!$call) {
            if ($this->isGravitelDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        $call_data = array(
            'plugin_record_id' => $this->cb_data['link'],
            'duration'         => $this->cb_data['duration'],
        );

        self::getCallModel()->updateById($call['id'], $call_data);
        self::getCallModel()->handleCalls(array($call['id']));
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

    protected function dumpLog($message)
    {
        waLog::dump($message, 'crm/plugins/'.$this->plugin_id.'.log');
    }

    protected function fatal()
    {
        exit; // overriden in unit tests
    }

    protected function isGravitelDebug()
    {
        return defined('CRM_'.mb_strtoupper($this->plugin_id).'_DEBUG'); // overriden in unit tests
    }
}
