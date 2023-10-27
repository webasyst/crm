<?php

/**
 * The Mango Office (Telecom) class provides a wrapper for several API methods used
 * @see https://www.mango-office.ru/upload/api/MangoOffice_VPBX_API_v1.8.pdf
 *
 * Quick Start:
 * @code
 *   $api = new crmMangoPluginApi();
 *   $api->getPbxUsers();
 */
class crmMangoPluginApi
{
    public $o;

    const PLUGIN_ID = "mango";
    const API_URL = "https://app.mango-office.ru/vpbx/";

    public function __construct()
    {
        $this->o = array(
            'api_key'          => wa()->getSetting('api_key', '', array('crm', self::PLUGIN_ID)),
            'sign_key'         => wa()->getSetting('sign_key', '', array('crm', self::PLUGIN_ID)),
        );

        if (empty($this->o['api_key'])) {
            throw new waException('Empty api key');
        }
        if (empty($this->o['sign_key'])) {
            throw new waException('Empty sign key');
        }
    }

    /**
     * @param array $call
     * @param string $number
     * @return array
     */
    public function redirect($call, $number)
    {
        $params = array(
            'call_id'   => $call['plugin_call_id'],
            'method'    => 'blind',
            'to_number' => $number,
            'initiator' => $call['plugin_user_number'],
        );
        return $this->request("commands/transfer", $params);
    }

    public function initCall($from, $to, $call)
    {
        $params = array(
            'command_id' => $call['id'],
            'from'       => array('extension' => $from),
            'to_number'  => $to,
        );
        return $this->request("commands/callback", $params);
    }

    /**
     * The method returns the list and data of employees of the virtual PBX.
     * @return array
     */
    public function getPbxUsers()
    {
        return $this->request("config/users/request");
    }

    /**
     * The method will return a link to the conversation record, which can only be used once.
     * @param string $record_id
     * @return null|string
     */
    public function getRecordUrl($record_id)
    {
        $api_key = $this->o['api_key'];
        $url = self::API_URL.'queries/recording/post';

        $data = array('recording_id' => $record_id, 'action' => "play");

        $json = json_encode($data);
        $sign = $this->getSign($data);
        $postdata = array(
            'vpbx_api_key' => $api_key,
            'sign'         => $sign,
            'json'         => $json
        );
        $post = http_build_query($postdata);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        $res = curl_exec($curl);
        curl_close($curl);
        preg_match_all('/^Location:(.*)$/mi', $res, $matches);

        if (!empty($matches[1][0])) {
            return trim($matches[1][0]);
        }
        return null;
    }

    /**
     * Request to Mango API
     * @param string $method
     * @param array $data
     * @return array
     */
    public function request($method,$data = array())
    {
        $api_key = $this->o['api_key'];
        $sign_salt = $this->o['sign_key'];
        $url = self::API_URL . $method;

        $json = json_encode($data);
        $sign = hash('sha256', $api_key . $json . $sign_salt);
        $postdata = array(
            'vpbx_api_key' => $api_key,
            'sign'         => $sign,
            'json'         => $json
        );
        $post = http_build_query($postdata);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        return json_decode(curl_exec($ch), true);
        curl_close($ch);
    }

    /**
     * The method generates a signature that is required to use API methods.
     * @param array $data
     * @return string
     */
    protected function getSign($data = array())
    {
        $api_key = $this->o['api_key'];
        $sign_key = $this->o['sign_key'];
        $json = json_encode($data);
        return hash('sha256', $api_key . $json . $sign_key);
    }
}