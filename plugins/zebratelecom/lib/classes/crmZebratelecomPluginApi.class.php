<?php

/**
 * The Zebra Telecom class provides a wrapper for several API methods used
 * @see https://www.zebratelecom.ru/help/api/
 * @see https://www.zebratelecom.ru/help/api/api-zebratelecom.pdf
 *
 * Quick Start:
 * @code
 *   $api = new crmZebratelecomPluginApi();
 *   $api->getPbxNumbers();
 */
class crmZebratelecomPluginApi
{
    public $o;
    protected $token = null;
    protected $account_id = null;

    const PLUGIN_ID = "zebratelecom";
    const API_URL = "https://api.zebratelecom.ru/v1/kazoos/";

    public function __construct()
    {
        $this->o = array(
            'login'      => wa()->getSetting('login', '', array('crm', self::PLUGIN_ID)),
            'password'   => wa()->getSetting('password', '', array('crm', self::PLUGIN_ID)),
            'sip_server' => wa()->getSetting('sip_server', '', array('crm', self::PLUGIN_ID)),
        );

        if (empty($this->o['login'])) {
            throw new waException(_wd('crm_zebratelecom', 'Empty login'));
        }
        if (empty($this->o['password'])) {
            throw new waException(_wd('crm_zebratelecom', 'Empty password'));
        }
        if (empty($this->o['sip_server'])) {
            throw new waException(_wd('crm_zebratelecom', 'Empty sip server (realm)'));
        }
    }

    /**
     * The method returns the list and data of employees of the virtual PBX.
     * @return array
     * @throws waException
     */
    public function getPbxNumbers()
    {
        $data = $phones = array();

        $result = $this->getNet()->query(self::API_URL.'accounts/'.$this->account_id.'/devices', array(), 'GET');
        $users = $result['data'];
        foreach ($users as $user) {
            $result = $this->getNet()->query(self::API_URL.'accounts/'.$this->account_id.'/devices/'.$user['id'], array(), 'GET');
            $data[] = $result['data'];
        }

        if (empty($data)) {
            return $phones;
        }

        foreach ($data as $user) {
            // if external number
            if (isset($user['call_forward'])) {
                $phones[] = array('name' => str_replace("+", "", $user['call_forward']['number']), 'id' => $user['id']);
            // or sip number
            } else {
                $phones[] = array('name' => $user['sip']['username'].'@'.$this->o['sip_server'], 'id' => $user['id']);
            }
        }
        return $phones;
    }

    public function getRecordUrl($created_to,$bridge_id)
    {
        // https://api.zebratelecom.ru/v1/kazoos/accounts/{account_id}/cdrs?created_from=63653115600&created_to=63655793999&filter_bridge_id={bridge_id}
        $result = $this->getNet()->query(self::API_URL.'accounts/'.$this->account_id.'/cdrs?created_from=0&created_to='.$created_to.'&filter_bridge_id='.$bridge_id, array(), 'GET');
        try {
            $data = $result['data'][0];
            $record_url = $data['REC_LINK'].$data['REC_FILE'];
            return $record_url;
        } catch (waException $e) {
            return null;
        }
    }

    public function initCall($from, $to)
    {
        // https://api.zebratelecom.ru/v1/kazoos/accounts/{account_id}/devices/{device_id}/quickcall/79165556677
        $this->getNet()->query(self::API_URL.'accounts/'.$this->account_id.'/devices/'.$from.'/quickcall/'.$to, array(), 'GET');
    }

    /**
     * The method returns a list of webhooks.
     * In all there are three web chores:
     *  - CHANNEL_CREATE (new call)
     *  - CHANNEL_ANSWER (client picked up the phone)
     *  - CHANNEL_DESTROY (call completed)
     * @param bool $new
     * @return array|SimpleXMLElement|string
     * @throws waException
     */
    public function getWebHooks($new = false)
    {
        try {
            $result = $this->getNet()->query(self::API_URL . 'accounts/' . $this->account_id . '/webhooks', array(), 'GET');
        } catch (waNetException $e) {
            $message = $e->getMessage();
            try {
                $decoded_msg = waUtils::jsonDecode($message, true);
            } catch (waException $exception) {}
            if (isset($decoded_msg['error_message'])) {
                if ($decoded_msg['error_message'] == 'Incorrect password') {
                    throw new waException(_wd('crm_zebratelecom', 'Incorrect password'));
                } elseif ($decoded_msg['error_message'] == 'invalid_credentials') {
                    throw new waException(_wd('crm_zebratelecom', 'Invalid credentials'));
                }
            } else {
                throw new waException($message);
            }
        }
        $data = $result['data'];

        if (empty($data)) {
            $this->setWebHooks();
            return $this->getWebHooks(true);
        }

        if (!$new) {
            // Delete old webhooks
            foreach ($data as $wh) {
                $this->delWebHook($wh['id']);
            }
            return $this->getWebHooks(true);
        }

        return $data;
    }

    /**
     * The method creates three necessary webhooks.
     * @throws waException
     */
    protected function setWebHooks()
    {
        $this->createWebHook('channel_create', 'New calls');
        $this->createWebHook('channel_answer', 'Picked up the phone');
        $this->createWebHook('channel_destroy', 'Call completed');
    }

    /**
     * The method creates a new webhook.
     * @param string $hook - channel_create, channel_answer, channel_destroy
     * @param string $name - New calls, Picked up the phone, Call completed
     * @return array|SimpleXMLElement|string
     * @throws waException
     */
    protected function createWebHook($hook, $name)
    {
        $net_options = array(
            'expected_http_code' => 201,
        );

        $uri = rtrim(wa()->getRouteUrl('crm', array(
            'plugin' => self::PLUGIN_ID,
            'module' => 'frontend',
            'action' => 'callback',
        ), true), '/');
        $uri = str_replace("https://", "http://", $uri); // Zebra one love!

        $data = array(
            'data' => array(
                'name'      => $name,
                'uri'       => $uri,
                'http_verb' => 'post',
                'hook'      => $hook,
                'retries'   => 1,
            ),
        );

        $this->getNet($net_options)->query(self::API_URL.'accounts/'.$this->account_id.'/webhooks', json_encode($data), 'PUT');
    }

    protected function delWebHook($webhook_id)
    {
        $this->getNet()->query(self::API_URL.'accounts/'.$this->account_id.'/webhooks/'.$webhook_id, array(), 'DELETE');
    }

    protected function getApiToken()
    {
        if ($this->token) {
            return $this->token;
        }

        // Data for requesting a new token
        $data = array(
            'data' => array(
                'login'    => $this->o['login'],
                'password' => $this->o['password'],
                'realm'    => $this->o['sip_server'],
            ),
        );

        // Request new token via API
        $result = $this->getNet(array('no_token' => true))->query(self::API_URL.'user_auth', json_encode($data), 'PUT');

        if (!$result && !isset($result['data']['auth_token']) && !isset($result['data']['account_id'])) {
            throw new waException(_wd('crm_zebratelecom', 'Auth error!'));
        }

        $this->token = $result['data']['auth_token'];
        $this->account_id = $result['data']['account_id'];
        // Cache the token in app settings for everybody to use
        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set(array('crm', self::PLUGIN_ID), 'auth_token', $this->token);
        $app_settings_model->set(array('crm', self::PLUGIN_ID), 'account_id', $this->account_id);

        return $this->token;
    }

    protected function getNet($opts = array())
    {
        $params = array();
        if (empty($opts['no_token'])) {
            $params['X-Auth-Token'] = $this->getApiToken();
        }

        unset($opts['no_token']);

        return new waNet($opts + array(
                'request_format' => 'raw',
                'format'         => 'json',
            ), $params);
    }
}