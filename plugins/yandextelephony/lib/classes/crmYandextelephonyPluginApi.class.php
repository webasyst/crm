<?php

/**
 * The Yandex.Telephony class provides a wrapper for commonly used Yandex Mightycall API
 *
 * !!!
 * For each {api_key}, there is an individual restriction on calling API methods,
 * which is 2500 calls per day. When the limit value is reached,
 * access to the API is limited until the next day.
 * !!!
 *
 * @see https://api.yandex.mightycall.ru/api/doc
 * Token to access the HTTP API
 * Quick Start:
 * @code
 *   $api = new crmYandextelephonyPluginApi();
 *   $api->getPhoneNumbers();
 */
class crmYandextelephonyPluginApi
{
    public $o;

    const PLUGIN_ID = "yandextelephony";
    const API_URL = "https://api.yandex.mightycall.ru/api/v2/";

    public function __construct()
    {
        $this->o = array(
            'api_key'          => wa()->getSetting('api_key', '', array('crm', self::PLUGIN_ID)),
            'user_key'         => wa()->getSetting('user_key', '', array('crm', self::PLUGIN_ID)),
            'api_token'        => wa()->getSetting('api_token', '', array('crm', self::PLUGIN_ID)),
            'api_token_expire' => wa()->getSetting('api_token_expire', '', array('crm', self::PLUGIN_ID)),
        );

        if (empty($this->o['api_key'])) {
            throw new waException('Empty api key');
        }
        if (empty($this->o['user_key'])) {
            throw new waException('Empty user key');
        }
    }

    public function checkApi()
    {
        $token = $this->getApiToken();
        return !empty($token);
    }

    public function getPhoneNumbers()
    {
        $result = json_decode($this->getNet()->query(self::API_URL.'phonenumbers', array(), 'GET'), JSON_FORCE_OBJECT);
        $numbers = array();
        if (!empty($result['data']['phoneNumbers'])) {
            $numbers = $result['data']['phoneNumbers'];
        }
        return $numbers;
    }

    protected function getApiToken()
    {
        if (empty($this->o['api_token']) || (!empty($this->o['api_token']) && time() > $this->o['api_token_expire'])) {
            // Request new token via API
            return $this->generateNewToken();
        }
        return $this->o['api_token'];
    }

    protected function generateNewToken()
    {
        if (empty($this->o['api_key'])) {
            throw new waException('Empty api key');
        }
        if (empty($this->o['user_key'])) {
            throw new waException('Empty user key');
        }

        $result = json_decode($this->getNet(array('no_token' => true))->query(self::API_URL.'auth/token', array(
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->o['api_key'],
            'client_secret' => $this->o['user_key'],
        ), 'POST'), JSON_FORCE_OBJECT);

        if (isset($result['error'])) {
            throw new waException($result['error']);
        }

        $this->o['api_token'] = $result['access_token'];

        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set(array('crm', self::PLUGIN_ID), 'api_token', $result['access_token']);
        $app_settings_model->set(array('crm', self::PLUGIN_ID), 'api_token_expire', time() + $result['expires_in']);

        return $this->o['api_token'];
    }

    protected function getNet($opts=array())
    {
        if (empty($this->o['api_key'])) {
            throw new waException('Empty api key');
        }

        $params = array();

        if (empty($opts['no_token'])) {
            $params['Authorization'] = 'bearer '.$this->getApiToken();
        }
        unset($opts['no_token']);

        return new waNet($opts + array(
                'Content-Type' => 'application/json',
                'x-api-key'    => $this->o['api_key'],
            ), $params);
    }
}