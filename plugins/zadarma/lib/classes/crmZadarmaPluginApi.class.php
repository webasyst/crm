<?php

/**
 * The Zadarma Telecom class provides a wrapper for several API methods used
 * @see https://zadarma.com/ru/support/api/
 *
 * Original class by <Zadarma>
 * @see https://github.com/zadarma/user-api-v1
 *
 * Quick Start:
 * @code
 *   $api = new crmZadarmaPluginApi();
 *   $api->getPbxUsers();
 */
class crmZadarmaPluginApi
{
    public $o;

    const PLUGIN_ID = "zadarma";
    const API_URL = "https://api.zadarma.com";

    public function __construct()
    {
        $this->o = array(
            'key'    => wa()->getSetting('key', '', array('crm', self::PLUGIN_ID)),
            'secret' => wa()->getSetting('secret', '', array('crm', self::PLUGIN_ID)),
        );

        if (empty($this->o['key'])) {
            throw new waException('Empty api key');
        }
        if (empty($this->o['secret'])) {
            throw new waException('Empty secret sign key');
        }
    }

    public function checkApi()
    {
        $result = json_decode($this->request('/v1/tariff/'), true);
        $status = ifempty($result, 'status', null);
        if ($status !== 'success') {
            return false;
        }
        return true;
    }

    public function getPbxUsers()
    {
        $numbers = array();
        $result = json_decode($this->request('/v1/pbx/internal/'), true);

        if (isset($result['status']) && $result['status'] == 'success') {
            foreach ($result['numbers'] as $number) {
                $numbers[$number] = $number.'@'.$result['pbx_id'];
            }
        }

        return $numbers;
    }

    public function getRecordUrl($plugin_record_id)
    {
        $params = array(
            'call_id' => $plugin_record_id,
        );
        $result = json_decode($this->request('/v1/pbx/record/request/', $params), true);
        if (isset($result['link'])) {
            return $result['link'];
        }
        return null;
    }

    public function initCall($from, $to)
    {
        $params = array(
            'from' => $from,
            'to'   => $to,
        );
        $this->request('/v1/request/callback/', $params);
    }

    public function request($method, $params = array(), $requestType = 'get', $format = 'json', $isAuth = true)
    {
        if (!is_array($params)) {
            throw new waException('Query params must be an array.');
        }
        $type = strtoupper($requestType);
        if (!in_array($type, array('GET', 'POST', 'PUT', 'DELETE'))) {
            $type = 'GET';
        }
        $params['format'] = $format;
        $options = array(
            CURLOPT_URL            => self::API_URL.$method,
            CURLOPT_CUSTOMREQUEST  => $type,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        );
        $ch = curl_init();
        if ($type == 'GET') {
            $options[CURLOPT_URL] = self::API_URL.$method.'?'.$this->httpBuildQuery($params);
        } else {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $this->httpBuildQuery($params);
        }
        if ($isAuth) {
            $options[CURLOPT_HTTPHEADER] = $this->getAuthHeader($method, $params);
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            throw new waException($error);
        }
        return $response;
    }

    private function getAuthHeader($method, $params)
    {
        ksort($params);
        $paramsString = $this->httpBuildQuery($params);
        $signature = base64_encode(hash_hmac('sha1', $method.$paramsString.md5($paramsString), $this->o['secret']));
        return array('Authorization: '.$this->o['key'].':'.$signature);
    }

    private function httpBuildQuery($params = array())
    {
        return http_build_query($params, null, '&', PHP_QUERY_RFC1738);
    }
}