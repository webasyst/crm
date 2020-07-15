<?php

class crmTelphinPluginApi
{
    public $o;

    const API_URL = 'https://apiproxy.telphin.ru/';

    protected $lock_fd = null;
    protected $token = null;

    protected $_callback_url = null;

    public function __construct($options = array())
    {
        $this->o = (array)$options;
        $plugin_app_id = wa()->getSetting('api_app_id', '', array('crm', 'telphin'));
        if (empty($this->o['app_id']) || empty($this->o['app_secret'])) {
            $this->o['app_id'] = $plugin_app_id;
            $this->o['app_secret'] = wa()->getSetting('api_app_secret', '', array('crm', 'telphin'));
        }
        if (empty($this->o['app_id'])) {
            throw new waException('API credentials required in plugin settings');
        }
        if ($plugin_app_id != $this->o['app_id']) {
            $this->o['force_new_token'] = true;
        }
    }

    public function checkApi()
    {
        $token = $this->getApiToken();
        return !empty($token);
    }

    /* see https://ringme-confluence.atlassian.net/wiki/spaces/RAL/pages/17367181/extension#id-Добавочный(/extension/)-GET/client/{client_id}/extension/
            {
              "caller_id_name": "string",
              "client_id": 0,
              "create_date": "string",
              "dial_rule_limit": 0,
              "did_as_transfer_caller_id": "string",
              "domain": "string",
              "extension_group_id": 0,
              "extra_params": "string",
              "from_public_caller_id_number": true,
              "id": 0,
              "label": "string",
              "name": "string",
              "public_caller_id_number": "string",
              "rfc_public_caller_id_number": true,
              "status": "string",
              "type": "string"
            }
     */
    public function getPhoneExtensions()
    {
        return $this->getNet()->query(self::API_URL.'api/ver1.0/client/@me/extension/', array(
            'type'     => 'phone',
            'status'   => 'active',
            'per_page' => 100,
            'page'     => 1,
        ), 'GET');
    }

    public function getHistoryCall($plugin_call_id)
    {
        if (!$plugin_call_id) {
            return null;
        }

        return $this->getNet()->query(self::API_URL.'api/ver1.0/client/@me/call_history/'.$plugin_call_id, array(), 'GET');
    }

    public function getOngoingCalls()
    {
        /*
        array(
          'call_list' => array(
            0 => array(
              'called_number' => '2566*102',
              'record_uuid' => NULL,
              'init_time_gmt' => '2017-08-23 15:57:56,60',
              'call_flow' => 'IN',
              'called_did' => '74953332211',
              'callback_id' => NULL,
              'caller_id_name' => '+79161112233',
              'call_api_id' => '3584709739-45c0c95e-2b8d-4ee4-b3c5-c62e29552a8c',
              'extension_id' => 85219,
              'caller_extension' => array(
                'id' => 96857,
                'type' => 'queue',
                'name' => '2566*887@sipproxy.telphin.ru',
                'client_id' => 9593,
                'extension_group_id' => NULL,
              ),
              'answer_time_gmt' => NULL,
              'called_extension' => array(
                'id' => 85219,
                'type' => 'phone',
                'name' => '2566*102@sipproxy.telphin.ru',
                'client_id' => 9593,
                'extension_group_id' => NULL,
              ),
              'caller_id_number' => '+79161112233',
            ),
          ),
        )*/
        $result = $this->getNet()->query(self::API_URL.'api/ver1.0/client/@me/current_calls/', array(), 'GET');
        return $result['call_list'];
    }

    /**
     * @param string $extension_id - This parameter is stored in table crm_call_params.It comes with the first callback and lives only during the call.
     * @param string $call_api_id - Stored and comes as $extension_id
     * @param string $number - Number (internal PBX or external) to which the call will be transferred
     * @return array|SimpleXMLElement|string
     * @throws waException
     */
    public function redirect($extension_id, $call_api_id, $number)
    {
        return $this->getNet(array('request_format' => 'json', 'expected_http_code' => 200))->query(
            self::API_URL.'api/ver1.0/extension/'.$extension_id.'/current_calls/'.$call_api_id,
            array(
                'action'   => 'transfer',
                'send_dst' => (string)$number,
            ),
            'PUT');
    }

    public function getCallbackAuthHash()
    {
        return md5('crmTelphinPluginApi'.$this->o['app_id'].$this->o['app_secret']);
    }

    public function getCallbackUrl($event_type = '-')
    {
        if ($this->_callback_url === null) {
            $this->_callback_url = wa()->getRouteUrl('crm/frontend/callback', array(
                'auth_hash'  => $this->getCallbackAuthHash(),
                'event_type' => '%EVENT_TYPE%',
                'plugin_id'  => 'telphin',
            ), true);
        }
        return str_replace('%EVENT_TYPE%', $event_type, $this->_callback_url);
    }

    /* see https://ringme-confluence.atlassian.net/wiki/spaces/RAL/pages/20414487/extension+...+event#id-Событиядобавочного(/extension/.../event/)-GET/extension/{extension_id}/event/
        id          integer
        url         'string'
            see https://ringme-confluence.atlassian.net/wiki/spaces/RAL/pages/23003184/Call+Interactive#CallInteractive-Параметрызапроса
        method      'string'
            'GET', 'POST'
        event_type  'string'
            'dial-in'   - incoming call
            'dial-out'  - outgoing call
            'answer'    - call started
            'hangup'    - call ended
    */
    public function getExtEvents($ext_id)
    {
        return $this->getNet()->query(self::API_URL.'api/ver1.0/extension/'.$ext_id.'/event/', array(), 'GET');
    }

    public function deleteExtEvent($ext_id, $evt_id)
    {
        return $this->getNet(array(
            'expected_http_code' => 204,
        ))->query(self::API_URL.'api/ver1.0/extension/'.$ext_id.'/event/'.$evt_id, array(), 'DELETE');
    }

    public function createExtEvent($ext_id, $evt_type)
    {
        return $this->getNet(array(
            'request_format'     => 'json',
            'expected_http_code' => 201,
        ))->query(self::API_URL.'api/ver1.0/extension/'.$ext_id.'/event/', array(
            'url'        => $this->getCallbackUrl($evt_type),
            'event_type' => $evt_type,
            'method'     => 'POST',
        ), 'POST');
    }

    public function getRecordUrl($record_id)
    {
        try {
            $result = $this->getNet()->query(self::API_URL.'api/ver1.0/client/@me/record/'.$record_id.'/storage_url/', array(), 'GET');
        } catch (Exception $e) {
            if ($e->getCode() == 404) {
                return null;
            }
            throw $e;
        }
        if ($result && !empty($result['record_url'])) {
            return $result['record_url'];
        }
        return null;
    }

    public function isCallbackUrl($url)
    {
        $hash = $this->getCallbackAuthHash();
        list($url_prefix, $_) = explode($hash, $this->getCallbackUrl(), 2);
        $current_url = $url_prefix.$hash;

        if (false !== strpos($url, $current_url)) {
            return 'current';
        }

        $url_prefix = str_replace(array(
            'http://',
            'https://',
        ), '', $url_prefix);

        if (false !== strpos($url, $url_prefix)) {
            return 'old';
        }

        return false;
    }

    public function initCall($from, $to, $extension_id, $call)
    {
        $res =  $this->getNet(array(
            'request_format'     => 'json',
            'expected_http_code' => 201,
        ))->query(self::API_URL.'api/ver1.0/extension/'.$extension_id.'/callback/', array(
            'src_num' => array($from),
            'dst_num' => $to,
        ), 'POST');

        $cm = new crmCallModel();
        if (isset($res['call_api_id']) && isset($res['call_id'])) {
            $cm->updateById($call['id'], array('status_id' => 'PENDING', 'plugin_call_id' => $res['call_id']));
        } else {
            $cm->updateById($call['id'], array('status_id' => 'DROPPED', 'duration' => null));
        }
    }

    protected function getApiToken()
    {
        if ($this->token) {
            return $this->token;
        }

        // Is there a non-expired existing token?
        if (empty($this->o['force_new_token'])) {
            $token = wa()->getSetting('api_token', '', array('crm', 'telphin'));
            $token_expire = wa()->getSetting('api_token_expire', '', array('crm', 'telphin'));
            if ($token && $token_expire && $token_expire > time()) {
                $this->token = $token;
                return $this->token;
            }
        }

        // Request new token via API
        $result = $this->getNet(array('no_token' => true))->query(self::API_URL.'oauth/token', array(
            'client_id'     => $this->o['app_id'],
            'client_secret' => $this->o['app_secret'],
            'grant_type'    => 'client_credentials',
        ), 'POST');

        // Cache the token in app settings for everybody to use
        $this->token = $result['access_token'];
        if (empty($this->o['force_new_token'])) {
            $app_settings_model = new waAppSettingsModel();
            $app_settings_model->set(array('crm', 'telphin'), 'api_token', $this->token);
            $app_settings_model->set(array('crm', 'telphin'), 'api_token_expire', time() + $result['expires_in']);
        }

        return $this->token;
    }

    protected function getNet($opts = array())
    {
        $params = array();
        if (empty($opts['no_token'])) {
            $params['Authorization'] = 'Bearer '.$this->getApiToken();
        }

        unset($opts['no_token']);

        return new waNet($opts + array(
                'request_format' => 'raw',
                'format'         => 'json',
            ), $params);
    }

    public static function clearCache()
    {
        waFiles::delete(waSystem::getInstance()->getCachePath('cache/telphin', 'crm'), true);
    }

    protected function getCache($cache_type, $cache_params = null)
    {
        $cache_key = 'telphin/'.$cache_type;
        if ($cache_params) {
            $cache_key .= '_';
            $cache_params = json_encode($cache_params);
            if (function_exists('hash')) {
                $cache_key .= hash("crc32b", $cache_params);
            } else {
                $cache_key .= str_pad(dechex(crc32($cache_params)), 8, '0', STR_PAD_LEFT);
            }
        }
        return new waVarExportCache($cache_key, 3600, 'crm');
    }

    protected function lock()
    {
        $filename = wa()->getDataPath('telphin/api.lock', false, 'crm');
        waFiles::create($filename);
        @touch($filename);
        @chmod($filename, 0666);
        $this->lock_fd = @fopen($filename, "r+");
        if (!$this->lock_fd || !flock($this->lock_fd, LOCK_EX)) {
            $this->lock_fd && fclose($this->lock_fd);
            $this->lock_fd = null;
            return false;
        }
        return true;
    }

    protected function unlock()
    {
        if ($this->lock_fd) {
            flock($this->lock_fd, LOCK_UN);
            fclose($this->lock_fd);
            $this->lock_fd = null;
        }
        return true;
    }
}