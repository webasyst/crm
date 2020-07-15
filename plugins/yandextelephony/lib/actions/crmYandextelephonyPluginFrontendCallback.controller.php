<?php

/**
 * This is a frontend controller accepting callbacks from Yandex.Telephony API (MightyCall)
 * when new call is initiated or existing call changes status.
 *
 * Yandex.Telephony documents the following vars via POST:
 * @see https://api.yandex.mightycall.ru/api/doc/#webhook-уведомления
 *
 * EventType      string  Тип события
 *      IncomingCall            - Появление нового входящего звонка со внешнего номера
 *      IncomingCallRinging     - Начало дозвона до пользователя с указанным добавочным номером
 *      IncomingCallStopRinging - Неуспешная попытка дозвона до пользователя с добавочным номером
 *      IncomingCallConnected   - Успешное соединение с пользователем с добавочным номером
 *      IncomingCallCompleted   - Завершение входящего звонка
 *
 *      OutgoingCall            - Начало исходящего звонка с бизнес номера {From} на внешний номер {To} пользователем с добавочным номером {Extension}
 *      OutgoingCallConnected   - Начало разговора при исходящем звонке
 *      OutgoingCallCompleted   - Завершение исходящего звонка.
 *
 *      CallbackCall            - Появление заявки на обратный звонок с бизнес номера {From} на внешний номер {To} // Пока не обрабатываем это событие
 *      CallbackCallRinging     - Начало дозвона до пользователя с указанным добавочным номером {Extension} при обратном звонке
 *      CallbackCallStopRinging - Неуспешная попытка дозвона до пользователя с добавочным номером {Extension} при обратном звонке
 *      CallbackCallConnected   - Пользователь с добавочным номером {Extension} соединился с номером {To} при обратном звонке
 *      CallbackCallCompleted   - Завершение обратного звонка
 * Body           array  Тело звонка
 *      From      string
 *      To        string
 *      Id        string  Идентификатор звонка !!!
 *      Extension int     Добавочный номер для Body.To (если есть)
 * Guid           string  Идентификатор события !!!
 * ApiKey         string  Ключ API
 * Timestamp      string  Время звонка
 *
 * @example
 *
 *   {
 *       "Body": {
 *           "From": "+79119371200",
 *           "To": "+78120347567",
 *           "Id": "7df52a62-9db2-48cd-9ae4-7dec8203d822"
 *       },
 *       "Guid": "00000000-0000-0000-0000-000000000001",
 *       "ApiKey": "8a77c828-31c2-492a-b0c1-224cd94e9c3b",
 *       "Timestamp": "2017-05-25T08:58:10.5140000Z",
 *       "EventType": "IncomingCall",
 *   }
 *
 */
class crmYandextelephonyPluginFrontendCallbackController extends waController
{
    public $plugin_id = "yandextelephony";
    public $event_type;
    public $timestamp;
    public $call_body;
    public $call_id;

    public function execute()
    {
        $json = json_decode(file_get_contents('php://input'),JSON_FORCE_OBJECT); //$_POST;
        $this->dumpLog($json);
        
        $echo = waRequest::server('HTTP_ECHO');
        if (!empty($echo)) {
            $this->dumpLog('Echo: '.$echo);
            wa()->getResponse()->addHeader('Echo', waRequest::server('HTTP_ECHO')); // !!!
            wa()->getResponse()->sendHeaders();
        }

        $api_key = wa()->getSetting('api_key', '', array('crm', $this->plugin_id));
        if ($json['ApiKey'] !== $api_key) {
            $this->dumpLog('Invalid api_key in callback');
            $this->fatal();
        }

        $this->timestamp = $json['Timestamp'];
        $event_type = $json['EventType'];
        $call_body = $json['Body'];
        $call_id = $json['Body']['Id'];

        $available_events = array(
            'IncomingCall', 'IncomingCallRinging','IncomingCallStopRinging','IncomingCallConnected','IncomingCallCompleted',
            'OutgoingCall','OutgoingCallConnected','OutgoingCallCompleted',
            /*'CallbackCall',*/ 'CallbackCallRinging','CallbackCallStopRinging','CallbackCallConnected','CallbackCallCompleted');
        if (!in_array($event_type, $available_events)) {
            $this->dumpLog('Invalid event type in frontend callback');
            $this->fatal();
        }
        $this->event_type = $event_type;

        if (empty($call_body)) {
            $this->dumpLog('Empty Call body in frontend callback');
            $this->fatal();
        }
        $this->call_body = $call_body;

        if (!$call_id) {
            $this->dumpLog('Invalid CallID in frontend callback');
            $this->fatal();
        }
        $this->call_id = $call_id;

        try {
            switch($event_type) {
                case 'IncomingCall':
                case 'IncomingCallRinging':
                case 'OutgoingCallConnected': // Create an outgoing call when the client answers
                case 'CallbackCallConnected': // Create an outgoing call when the client answers
                    $this->handleNewCall();
                    break;
                case 'IncomingCallConnected':
                    $this->handleAnswer();
                    break;
                case 'IncomingCallStopRinging':
                case 'CallbackCallStopRinging':
                    $this->handleDropped();
                    break;
                case 'IncomingStopRinging':
                    $this->handleVoiceMail();
                    break;
                case 'IncomingCallCompleted':
                case 'OutgoingCallCompleted':
                case 'CallbackCallCompleted':
                    $this->handleHangup();
                    break;
            }
        } catch (Exception $e) {
            if (!$e instanceof waException) {
                $e = new waException($e);
            }
            $this->dumpLog($e);
        }
    }

    protected function handleNewCall()
    {
        if (stristr($this->event_type,"Incoming")) {
            $call_data = array(
                'direction'            => 'IN',
                'plugin_user_number'   => $this->call_body['To'],
                'plugin_client_number' => $this->call_body['From'],
            );
        } else {
            $call_data = array(
                'direction'            => 'OUT',
                'plugin_user_number'   => $this->call_body['From'],
                'plugin_client_number' => $this->call_body['To'],
            );
        }

        // Make sure it's not an internal call
        if (false !== strpos($call_data['plugin_user_number'], '*') && false !== strpos($call_data['plugin_client_number'], '*')) {
            return; // Ignore internal calls
        }

        $call_data += array(
            'plugin_id'       => $this->plugin_id,
            'plugin_call_id'  => $this->call_id,
            'create_datetime' => date('Y-m-d H:i:s'),
        );

        $status_id = "PENDING";

        // Create an outgoing call when the client answers (CONNECTED)
        if ($this->event_type == "OutgoingCallConnected" || $this->event_type == "CallbackCallConnected") {
            $status_id = "CONNECTED";
        }

        $call_data['status_id'] = $status_id;

        // Make sure call id is unique
        $existing_call = $this->getCallModel()->getByField(array(
            'plugin_id'          => $this->plugin_id,
            'plugin_call_id'     => $call_data['plugin_call_id'],
            'plugin_user_number' => $call_data['plugin_user_number'],
        ));
        if ($existing_call) {
            // Additional dial-ins sometimes come for existing calls when call
            // is routed to the same extension repeatedly.
            $id = $existing_call['id'];
            $this->getCallModel()->updateById($id, array(
                'status_id'         => $status_id,
                'finish_datetime'   => null,
                'notification_sent' => 0,
            ));
            if ($this->isYandextelephonyDebug()) {
                $this->dumpLog('Existing record updated, id='.$id);
            }
        } else {
            // Insert new call to db
            $id = $this->getCallModel()->insert($call_data);
            if ($this->isYandextelephonyDebug()) {
                $this->dumpLog('New record created, id='.$id);
            }
        }

        // Add client_contact_id to call
        if ($call_data['status_id'] == "CONNECTED") {
            $telephony = wa('crm')->getConfig()->getTelephonyPlugins($this->plugin_id);
            $contact = $telephony->findClients($call_data['plugin_client_number'], 1);
            $this->getCallModel()->setCallClient($id, key($contact));
        }

        $this->getCallModel()->handleCalls(array($id));
        if ($this->isYandextelephonyDebug()) {
            $this->dumpLog('handleCalls() done');
        }
    }

    protected function handleAnswer()
    {
        $user_number = $this->getUserNumber();
        $call = $this->getBestMatchingCall($user_number);
        if (!$call) {
            $this->dumpLog('Received ANSWER frontend callback for unknown CallID');
            return;
        }

        $this->getCallModel()->updateById($call['id'], array(
            'plugin_user_number' => $user_number,
            'status_id'          => 'CONNECTED',
        ));
        $this->deletePendingDuplicates($call);
        $this->getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleHangup()
    {
        $user_number = $this->getUserNumber();
        $call = $this->getBestMatchingCall($user_number);

        if (!$call || $call['plugin_user_number'] != $user_number) {
            if ($this->isYandextelephonyDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        // Do not set the "FINISHED" status if the call was DROPPED or VOICEMAIL.
        // See list of received events:
        // https://api.yandex.mightycall.ru/api/doc/#webhook-уведомления-входящий-звонок
        // https://api.yandex.mightycall.ru/api/doc/#webhook-уведомления-переадресация-на-автоответчик
        if ($call['status_id'] == 'DROPPED') {
            return;
        }

        $call_data = array(
            'finish_datetime' => date('Y-m-d H:i:s'),
            'duration'        => time() - strtotime($call['create_datetime']),
            'status_id'       => 'FINISHED',
        );

        // https://api.yandex.mightycall.ru/api/doc/#webhook-уведомления-переадресация-на-автоответчик
        if ($call['status_id'] == "PENDING" && $call['direction'] == "IN") {
            $call_data['status_id'] = "VOICEMAIL";
        }

        $this->getCallModel()->updateById($call['id'], $call_data);
        $this->getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleDropped()
    {
        $user_number = $this->getUserNumber();
        $call = $this->getBestMatchingCall($user_number);

        if (!$call || $call['plugin_user_number'] != $user_number) {
            if ($this->isYandextelephonyDebug()) {
                $this->dumpLog('Received STOP frontend callback for unknown or deleted call');
            }
            return;
        }

        $call_data = array(
            'finish_datetime' => date('Y-m-d H:i:s'),
            'status_id'       => 'DROPPED',
        );

        $this->getCallModel()->updateById($call['id'], $call_data);
        $this->getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleVoiceMail()
    {
        $user_number = $this->getUserNumber();
        $call = $this->getBestMatchingCall($user_number);

        if (!$call || $call['plugin_user_number'] != $user_number) {
            if ($this->isYandextelephonyDebug()) {
                $this->dumpLog('Received VOICEMAIL frontend callback for unknown or deleted call');
            }
            return;
        }

        $call_data = array(
            'status_id' => 'VOICEMAIL',
        );

        $this->getCallModel()->updateById($call['id'], $call_data);
        $this->getCallModel()->handleCalls(array($call['id']));
    }

    // Deduce user extension number from POST
    protected function getUserNumber()
    {
        if (stristr($this->event_type,"Incoming")) {
            return $this->call_body['To'];
        } else {
            return $this->call_body['From'];
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
            'plugin_id' => $this->plugin_id,
            'plugin_call_id' => $this->call_id,
        ), true);
        if (!$calls) {
            return null;
        }

        $ext_match = null;
        foreach($calls as $call) {
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

    protected function isYandextelephonyDebug()
    {
        return defined('CRM_'.mb_strtoupper($this->plugin_id).'_DEBUG'); // overriden in unit tests
    }
}
