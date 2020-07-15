<?php

/**
 * Sipuni webhook controller.
 * @see https://sipuni.com/idealats/pdf/SIPUNI%20%D0%BE%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5%20HTTP%20API.pdf
 */
class crmSipuniPluginFrontendCallbackController extends waController
{
    public $plugin_id = "sipuni";
    public $cb_data;

    public function execute()
    {
        $this->cb_data = waRequest::get();

        $this->dumpLog($this->cb_data);

        if (isset($this->cb_data['event'])) {
            switch ($this->cb_data['event']) {
                case '1':
                    $this->handleNewCall();
                    break;
                case '3':
                    $this->handleAnswer();
                    break;
                case '2':
                case '4':
                    $this->handleHangup();
                    break;
            }
        }

        echo json_encode(array('success' => true));
    }

    protected function handleNewCall()
    {
        // Make sure it's not an internal call
        if ($this->cb_data['src_type'] == $this->cb_data['dst_type']) {
            return; // Ignore internal calls
        }

        if ($this->cb_data['src_type'] == '1') {
            $call_data = array(
                'direction'            => 'IN',
                'plugin_user_number'   => $this->cb_data['short_dst_num'],
                'plugin_client_number' => $this->cb_data['src_num'],
            );
        } else {
            $call_data = array(
                'direction'            => 'OUT',
                'plugin_user_number'   => $this->cb_data['short_src_num'],
                'plugin_client_number' => $this->cb_data['dst_num'],
            );
        }

        // !!!!!! outgoing via api call tracking
        if ($call_data['direction'] == 'OUT') {
            $system_call = $this->getCallModel()->getByField(array(
                'direction' => 'OUT',
                'status_id' => 'PENDING',
                'plugin_id' => $this->plugin_id,
                'plugin_call_id' => 'expected',
                'plugin_user_number' => $call_data['plugin_user_number'],
            ));
            if ($system_call) {
                $this->getCallModel()->updateById($system_call['id'], array(
                    'plugin_call_id' => $this->cb_data['call_id'],
                ));
                return;
            }
        }

        $call_data += array(
            'plugin_id'       => $this->plugin_id,
            'plugin_call_id'  => $this->cb_data['call_id'],
            'create_datetime' => date('Y-m-d H:i:s'),
            'status_id'       => 'PENDING',
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
            if ($this->isSipuniDebug()) {
                $this->dumpLog('Existing record updated, id='.$id);
            }
        } else {
            // Insert new call to db
            $id = self::getCallModel()->insert($call_data);
            if ($this->isSipuniDebug()) {
                $this->dumpLog('New record created, id='.$id);
            }
        }

        self::getCallModel()->handleCalls(array($id));
        if ($this->isSipuniDebug()) {
            $this->dumpLog('handleCalls() done');
        }
    }

    protected function handleAnswer()
    {
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['call_id']));
        if (!$call) {
            if ($this->isSipuniDebug()) {
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
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['call_id']));
        if (!$call) {
            if ($this->isSipuniDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        $call_data = array(
            'plugin_record_id' => $this->cb_data['call_record_link'],
            'duration'         => time() - strtotime($call['create_datetime']),
            'finish_datetime'  => date('Y-m-d H:i:s'),
        );
        $call_data['status_id'] = $call_data['duration'] > 0 ? 'FINISHED' : 'DROPPED';

        if ($call_data['status_id'] == 'DROPPED') {
            unset($call_data['plugin_record_id']);
        }

        self::getCallModel()->updateById($call['id'], $call_data);
        self::getCallModel()->handleCalls(array($call['id']));
    }

    // Deduce user extension number from POST
    protected function getUserNumber()
    {
        if ($this->cb_data['src_type'] == '1') {
            return $this->cb_data['short_dst_num'];
        } else {
            return $this->cb_data['short_src_num'];
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
            'plugin_call_id' => $this->cb_data['call_id'],
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

    protected function dumpLog($message)
    {
        waLog::dump($message, 'crm/plugins/'.$this->plugin_id.'.log');
    }

    protected function fatal()
    {
        exit; // overriden in unit tests
    }

    protected function isSipuniDebug()
    {
        return defined('CRM_'.mb_strtoupper($this->plugin_id).'_DEBUG'); // overriden in unit tests
    }
}
