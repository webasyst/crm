<?php


class crmZadarmaPluginFrontendCallbackController extends waController
{
    public $plugin_id = "zadarma";
    public $key;
    public $secret;

    public $cb_data;

    public function execute()
    {
        if (isset($_GET['zd_echo'])) {
            exit($_GET['zd_echo']);
        } // link validator (need for Zadarma)

        $this->key = wa()->getSetting('key', '', array('crm', $this->plugin_id));
        $this->secret = wa()->getSetting('secret', '', array('crm', $this->plugin_id));

        $this->cb_data = waRequest::request(null, array(), waRequest::TYPE_ARRAY);
        //$this->dumpLog($this->cb_data);

        if (isset($this->cb_data['event'])) {
            /*
             * NOTIFY_START     - начало входящего звонка в АТС - по данному событию не создаём звонок. Ждём следующий.
             * NOTIFY_INTERNAL  - начало входящего звонка на внутренний номер АТС
             * NOTIFY_ANSWER    - ответ при звонке на внутренний или на внешний номер
             * NOTIFY_END       - конец входящего звонка на внутренний номер АТС
             * NOTIFY_OUT_START - начало исходящего звонка с АТС
             * NOTIFY_OUT_END   - конец исходящего звонка с АТС
             * NOTIFY_RECORD    - запись звонка готова для скачивания
             */
            switch ($this->cb_data['event']) {
                case 'NOTIFY_INTERNAL':
                case 'NOTIFY_OUT_START':
                    $this->handleNewCall();
                    break;
                case 'NOTIFY_ANSWER':
                    $this->handleAnswer();
                    break;
                case 'NOTIFY_END':
                case 'NOTIFY_OUT_END':
                    $this->handleHangup();
                    break;
                case 'NOTIFY_RECORD':
                    $this->handleRecord();
                    break;
            }
        }
    }

    protected function handleNewCall()
    {
        if ($this->cb_data['event'] == 'NOTIFY_OUT_START') {
            $call_data = array(
                'direction'            => 'OUT',
                'plugin_user_number'   => $this->cb_data['internal'],
                'plugin_client_number' => $this->cb_data['destination'],
            );
        } else {
            $call_data = array(
                'direction'            => 'IN',
                'plugin_gateway'       => $this->cb_data['called_did'],
                'plugin_user_number'   => $this->cb_data['internal'],
                'plugin_client_number' => $this->cb_data['caller_id'],
            );
        }

        $call_data += array(
            'plugin_id'       => $this->plugin_id,
            'plugin_call_id'  => $this->cb_data['pbx_call_id'],
            'create_datetime' => date('Y-m-d H:i:s'),
            'status_id'       => 'PENDING',
        );

        // !!!!!! outgoing via api call tracking
        if ($this->cb_data['event'] == 'NOTIFY_OUT_START') {
            $system_call = self::getCallModel()->getByField(array(
                'status_id'            => 'PENDING',
                'plugin_id'            => $this->plugin_id,
                'plugin_user_number'   => $this->cb_data['destination'], // magic 1
                'plugin_client_number' => $this->cb_data['internal'],    // magic 2
                'plugin_call_id'       => 'expected',                    // magic 3
            ));
            if ($system_call) {
                $id = $system_call['id'];
                self::getCallModel()->updateById($id, array(
                    'status_id'         => 'PENDING',
                    'plugin_call_id'    => $this->cb_data['pbx_call_id'],
                    'finish_datetime'   => null,
                    'plugin_record_id'  => null,
                    'notification_sent' => 0,
                    'duration'          => null,
                ));
                self::getCallModel()->handleCalls(array($id));
                if ($this->isZadarmaDebug()) {
                    $this->dumpLog('handleCalls() done');
                }
                return;
            }
        }

        // Make sure call id is unique
        $existing_call = self::getCallModel()->getByField(array(
            'status_id'            => 'PENDING',
            'plugin_id'            => $this->plugin_id,
            'plugin_user_number'   => $this->cb_data['internal'],
            'plugin_client_number' => $this->cb_data['destination'],
        ));
        if ($existing_call) {
            // Additional dial-ins sometimes come for existing calls when call
            // is routed to the same extension repeatedly.
            $id = $existing_call['id'];
            self::getCallModel()->updateById($id, array(
                'status_id'         => 'PENDING',
                'plugin_call_id'    => $this->cb_data['pbx_call_id'],
                'finish_datetime'   => null,
                'plugin_record_id'  => null,
                'notification_sent' => 0,
                'duration'          => null,
            ));
            if ($this->isZadarmaDebug()) {
                $this->dumpLog('Existing record updated, id='.$id);
            }
        } else {
            // Insert new call to db
            $id = self::getCallModel()->insert($call_data);
            if ($this->isZadarmaDebug()) {
                $this->dumpLog('New record created, id='.$id);
            }
        }

        self::getCallModel()->handleCalls(array($id));
        if ($this->isZadarmaDebug()) {
            $this->dumpLog('handleCalls() done');
        }
    }

    protected function handleAnswer()
    {
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['pbx_call_id']));
        if (!$call) {
            if ($this->isZadarmaDebug()) {
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
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['pbx_call_id']));
        if (!$call) {
            if ($this->isZadarmaDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        $call_data = array(
            'duration'        => $this->cb_data['duration'],
            'finish_datetime' => date('Y-m-d H:i:s'),
        );
        $call_data['status_id'] = $this->cb_data['disposition'] == 'answered' ? 'FINISHED' : 'DROPPED';

        self::getCallModel()->updateById($call['id'], $call_data);
        self::getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleRecord()
    {
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['pbx_call_id']));
        if (!$call) {
            if ($this->isZadarmaDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        $call_data = array(
            'plugin_record_id' => $this->cb_data['call_id_with_rec'],
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

    protected function isZadarmaDebug()
    {
        return defined('CRM_'.mb_strtoupper($this->plugin_id).'_DEBUG'); // overriden in unit tests
    }
}
