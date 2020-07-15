<?php
/**
 * This is a frontend controller accepting callbacks from Telphin API
 * when new call is initiated or existing call changes status.
 *
 * Telphin documents the following vars via POST:
 *      // see https://ringme-confluence.atlassian.net/wiki/spaces/RAL/pages/23003184/Call+Interactive#CallInteractive-Параметрызапроса
 *
 * EventType           string  Тип события, всегда имеет значение call_interactive
 * CallID              string  Уникальный идентификатор вызова.
 * CallerIDNum         string  Номер вызывающего абонента.
 * CallerIDName        string  Имя вызывающего абонента.
 * CalledDID           string  Внешний вызываемый номер. Присутствует, если доступен.
 * CalledExtension     string  Номер IVR в расширенном формате (например: yyyy*zzz).
 * CalledExtensionID   integer Идентификатор добавочного IVR в системе
 * CallStatus          string  Статус вызова, всегда имеет значение ANSWER
 * CallFlow            string  Поток вызовов, всегда имеет значение IN
 * CallerExtension     string  Имя добавочного номера, осуществляющий вызов IVR. Присутствует только при внутреннем вызове.
 * CallerExtensionID   integer Идентификатор добавочного номера, осуществляющего вызов IVR. Присутствует только при внутреннем вызове.
 * CalledNumber        string  Номер, который набирала вызывающая сторона (может быть внешним номером: 003258422544, номером IVR в расширенном формате: 0003*001 или коротким номером IVR: 001).
 * CallAPIID           string  Идентификатор звонка. Автоматически генерируется и сохраняется на протяжении всего звонка вне зависимости от того, переводится ли он.
 *
 */
class crmTelphinPluginFrontendCallbackController extends waController
{
    protected $initial_ob_level;
    protected $request_sig = null;

    public function execute()
    {
        $not_found = false;
        try {
            $api = $this->getApi();
        } catch (Exception $e) {
            $not_found = true;
        }

        $not_found = $not_found || $api->getCallbackAuthHash() != waRequest::param('auth_hash');
        if ($not_found) {
            if ($this->isTelphinDebug()) {
                $this->dumpLog('Wrong hash');
            }
            $this->fatal();
        }

        $event_type = waRequest::param('event_type', null, 'string');
        if (!in_array($event_type, array('dial-in', 'dial-out', 'answer', 'hangup')) || $event_type != waRequest::post('EventType')) {
            $this->dumpLog('Invalid event type in frontend callback');
            $this->fatal();
        }

        if(!waRequest::post('CallID', null, 'string')) {
            $this->dumpLog('Invalid CallID in frontend callback');
            $this->fatal();
        }

        if ($this->isTelphinDebug()) {
            $this->dumpLog('Received frontend callback');
            register_shutdown_function(array($this, 'shutdownDebugFunction'));
            set_error_handler(array($this, 'debugErrorHandler'));
        }

        $this->initial_ob_level = ob_get_level();
        ob_start();
        try {
            switch($event_type) {
                case 'dial-in':
                case 'dial-out':
                    $this->handleNewCall();
                    break;
                case 'answer':
                    $this->handleAnswer();
                    break;
                case 'hangup':
                    $this->handleHangup();
                    break;
            }
            if ($this->isTelphinDebug()) {
                $this->dumpLog('main routine finished');
            }
        } catch (Exception $e) {
            if (!$e instanceof waException) {
                $e = new waException($e);
            }
            $this->dumpLog($e);
        }

        $unexpected_output = $this->getUnexpectedOutput();
        if ($unexpected_output) {
            $this->dumpLog("Unexpected output occured (1):\n".$unexpected_output);
        }

        // https://ringme-confluence.atlassian.net/wiki/spaces/RAL/pages/23003184/Call+Interactive#CallInteractive-Структураответа
        echo <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<Response></Response>
EOF;
    }

    protected function handleNewCall()
    {
        if (waRequest::param('event_type') == 'dial-in') {
            /*
                  'EventType' => 'dial-in',
                  'EventTime' => '1502985686478202', // divide by 10^6 to get unix timestamp

                  'CallID' => '262077ef325f4868aa49bf22309229f7',
                  'SubCallID' => '85219-e6bd7bb2622b424392a012e28473f91a',
                  'CallAPIID' => '3584709739-262077ef-325f-4868-aa49-bf22309229f7',

                  'CallFlow' => 'in',
                  'CallStatus' => 'CALLING',

                  'CalledDID' => '74959998877', // telphin gateway, client-visible number
                  'CalledNumber' => '2566*102',
                  'CalledExtension' => '2566*102@sipproxy.telphin.ru',
                  'CalledExtensionID' => '85219',

                  'CallerIDName' => '+79161112233', // client number
                  'CallerIDNum' => '+79161112233', // client number
                  'CallerExtensionID' => '96857',
                  'CallerExtension' => '2566*887@sipproxy.telphin.ru',
            */

            $call_data = array(
                'direction' => 'IN',
                'plugin_user_number' => waRequest::post('CalledNumber', null, 'string'),
                'plugin_gateway' => waRequest::post('CalledDID', null, 'string'),
                'plugin_client_number' => waRequest::post('CallerIDNum', null, 'string'),
            );
        } else {
            /*
              'EventType' => 'dial-out',
              'EventTime' => '1502985726958201', // divide by 10^6 to get unix timestamp

              'CallID' => 'b5187f38a9cc43f2ba068bce983a513e',
              'SubCallID' => '85219-b5187f38a9cc43f2ba068bce983a513e',
              'CallAPIID' => '3584709739-b5187f38-a9cc-43f2-ba06-8bce983a513e',

              'CallStatus' => 'CALLING',
              'CallFlow' => 'out',

              'CalledNumber' => '+79161112233', // client number

              'CallerIDNum' => '2566*102',
              'CallerExtensionID' => '85219',
              'CallerExtension' => '2566*102@sipproxy.telphin.ru',
              'CallerIDName' => 'Пукин Васисуалий Карлович',    // name set up in Telphin admin panel for this extension
            */
            $call_data = array(
                'direction' => 'OUT',
                'plugin_user_number' => waRequest::post('CallerIDNum', null, 'string'),
                'plugin_client_number' => waRequest::post('CalledNumber', null, 'string'),
            );
        }

        // Make sure it's not an internal call
        if (false !== strpos($call_data['plugin_user_number'], '*') && false !== strpos($call_data['plugin_client_number'], '*')) {
            return; // Ignore internal calls
        }

        $call_data += array(
            'plugin_id' => 'telphin',
            'plugin_call_id' => waRequest::post('CallID', null, 'string'),
            'create_datetime' => date('Y-m-d H:i:s'),
            'status_id' => 'PENDING',
        );

        // Make sure call id is unique
        $existing_call = self::getCallModel()->getByField(array(
            'plugin_id' => 'telphin',
            'plugin_call_id' => $call_data['plugin_call_id'],
            'plugin_user_number' => $call_data['plugin_user_number'],
        ));
        if ($existing_call) {
            // Additional dial-ins sometimes come for existing calls when call
            // is routed to the same extension repeatedly.
            $id = $existing_call['id'];
            self::getCallModel()->updateById($id, array(
                'status_id' => 'PENDING',
                'finish_datetime' => null,
                'notification_sent' => 0,
            ));
            if ($this->isTelphinDebug()) {
                $this->dumpLog('Existing record updated, id='.$id);
            }
        } else {
            // Insert new call to db
            $id = self::getCallModel()->insert($call_data);
            // Add extension_id and call_api_id to call params
            $extension_id = waRequest::post('CalledExtensionID', null, 'string');
            $call_api_id = waRequest::post('CallAPIID', null, 'string');
            self::getCallParamsModel()->set($id, array('call_api_id' => $call_api_id, 'extension_id' => $extension_id));
            if ($this->isTelphinDebug()) {
                $this->dumpLog('New record created, id='.$id);
            }
        }

        self::getCallModel()->handleCalls(array($id));
    }

    protected function handleAnswer()
    {
        /*
          'EventType' => 'answer',
          'EventTime' => '1502985738818201', // divide by 10^6 to get unix timestamp

          'CallID' => 'b5187f38a9cc43f2ba068bce983a513e',
          'SubCallID' => '85219-b5187f38a9cc43f2ba068bce983a513e',
          'CallAPIID' => '3584709739-b5187f38-a9cc-43f2-ba06-8bce983a513e',

          'CallStatus' => 'ANSWER',
          'CallFlow' => 'out',

          'CalledNumber' => '+79161112233', // client number

          'CallerIDNum' => '2566*102',
          'CallerExtensionID' => '85219',
          'CallerExtension' => '2566*102@sipproxy.telphin.ru',
          'CallerIDName' => 'Пукин Васисуалий Карлович',    // name set up in Telphin admin panel for this extension
        */

        $user_number = self::getUserNumber();
        $call = self::getBestMatchingCall($user_number);
        if (!$call) {
            $this->dumpLog('Received ANSWER frontend callback for unknown CallID');
            return;
        }

        self::getCallModel()->updateById($call['id'], array(
            'plugin_user_number' => $user_number,
            'status_id' => 'CONNECTED',
        ));
        self::deletePendingDuplicates($call);
        self::getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleHangup()
    {
        /*
          'EventType' => 'hangup',
          'EventTime' => '1502985750238219', // divide by 10^6 to get unix timestamp

          'CallID' => 'b5187f38a9cc43f2ba068bce983a513e',
          'SubCallID' => '85219-b5187f38a9cc43f2ba068bce983a513e',
          'CallAPIID' => '3584709739-b5187f38-a9cc-43f2-ba06-8bce983a513e',

          'RecID' => '10500-100bbbbcccc99aaff44cccc777ddd444', // record_uuid if call recording is enabled (does not always come for some reason)
          'Duration' => '11420018', // microseconds
          'CallStatus' => 'ANSWER',
          'CallFlow' => 'out',

          'CalledNumber' => '+79161112233', // client number

          'CallerIDNum' => '2566*102',
          'CallerExtensionID' => '85219',
          'CallerExtension' => '2566*102@sipproxy.telphin.ru',
          'CallerIDName' => 'Пукин Васисуалий Карлович',    // name set up in Telphin admin panel for this extension
        */


        $user_number = self::getUserNumber();
        $call = self::getBestMatchingCall($user_number);

        if (!$call || $call['plugin_user_number'] != $user_number) {
            if ($this->isTelphinDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        $call_data = array(
            'plugin_record_id' => waRequest::post('RecID', null, 'string'),
            'duration' => round(waRequest::post('Duration', 0, 'int') / 1000000),
            'finish_datetime' => date('Y-m-d H:i:s'),
        );

        $call_data['status_id'] = $call_data['duration'] > 0 ? 'FINISHED' : 'DROPPED';

        // Get record id via API if did not come in POST parameters.
        // (In reality it never comes in POST despite what API documentation says.)
        if (empty($call_data['plugin_record_id'])) {
            try {
                $api = $this->getApi();
                $telphin_call = $api->getHistoryCall($call['plugin_call_id']);
                foreach(ifset($telphin_call['cdr'], array()) as $subcall) {
                    if (!empty($subcall['record_uuid'])) {
                        $call_data['plugin_record_id'] = $subcall['record_uuid'];
                        break;
                    }
                }
                if (empty($call_data['plugin_record_id']) && $this->isTelphinDebug()) {
                    $this->dumpLog('No call record found for call_id='.$call['id']);
                }
            } catch (Exception $e) {
                if (!$e instanceof waException) {
                    $e = new waException($e);
                }
                $this->dumpLog($e);
            }
        }

        self::getCallModel()->updateById($call['id'], $call_data);
        self::getCallModel()->handleCalls(array($call['id']));
        self::getCallParamsModel()->delete($call['id']);
        if ($call_data['status_id'] === 'DROPPED') {
            self::getCallParamsModel()->set($call['id'], array(
                'need_cleanup' => 1,
            ));
        }
    }

    // Deduce user extension number from POST
    protected static function getUserNumber()
    {
        if ('out' == waRequest::post('CallFlow', null, 'string')) {
            return waRequest::post('CallerIDNum', null, 'string');
        } else {
            return waRequest::post('CalledNumber', null, 'string');
        }
    }

    // When a single client call is routed to several user extension numbers in parallel,
    // there are several records with the same `crm_call.plugin_call_id` in DB.
    // This helper function selects the one matching user extension.
    protected static function getBestMatchingCall($user_number)
    {
        $calls = self::getCallModel()->getByField(array(
            'plugin_id' => 'telphin',
            'plugin_call_id' => waRequest::post('CallID', null, 'string'),
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

    // Single client's call can be routed to several user ext numbers.
    // We keep several records with the same `crm_call.plugin_call_id`,
    // while the call is pending.
    // As soon as the call is answered, we delete duplicates.
    // (In case call is never answered, we handle it in crmTelphinPlugin->deleteDroppedDuplicates()
    protected static function deletePendingDuplicates($call)
    {
        $duplicate_call_ids = array_keys(self::getCallModel()->query(
            "SELECT id FROM crm_call
             WHERE plugin_id = 'telphin'
                AND plugin_call_id = ?
                AND status_id IN ('PENDING', 'DROPPED')
                AND id <> ?",
            $call['plugin_call_id'],
            $call['id']
        )->fetchAll('id'));

        self::getCallModel()->deleteById($duplicate_call_ids);
        self::getCallParamsModel()->deleteByField(array(
            'call_id' => $duplicate_call_ids,
        ));
    }

    protected function dumpLog($message)
    {
        if ($message instanceof waException) {
            $message = $message->getMessage().' ('.$message->getCode().")\n".$message->getFullTraceAsString();
        }
        if ($this->request_sig === null) {
            $this->request_sig = waRequest::param('event_type', null, 'string');
            $this->request_sig .= ' '.uniqid('', true);
        }
        waLog::dump((string)$message, $this->request_sig, waRequest::post(), 'crm/plugins/telphin.log');
    }

    public function debugErrorHandler()
    {
        $this->dumpLog('Error handler activated: '.wa_dump_helper(func_get_args()));
        return false;
    }

    public function shutdownDebugFunction()
    {
        $unexpected_output = $this->getUnexpectedOutput();
        if ($unexpected_output) {
            $this->dumpLog("Unexpected output occured (2):\n".$unexpected_output);
        }

        $error = error_get_last();
        if ($error && $error["type"] == E_ERROR) {
            $this->dumpLog('Last error in shutdown handler: '.wa_dump_helper($error));
        }
    }

    protected function getUnexpectedOutput()
    {
        $unexpected_output = '';
        while (ob_get_level() > $this->initial_ob_level) {
            if ($unexpected_output) {
                $unexpected_output .= "\n\n\n";
            }
            $unexpected_output .= ob_get_contents();
            ob_end_clean();
        }
        return $unexpected_output;
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

    protected function getApi()
    {
        return new crmTelphinPluginApi(); // overriden in unit tests
    }

    protected function wait($sec)
    {
        sleep($sec); // overriden in unit tests
    }

    protected function fatal()
    {
        exit; // overriden in unit tests
    }

    protected function isTelphinDebug()
    {
        return defined('CRM_TELPHIN_DEBUG'); // overriden in unit tests
    }
}
