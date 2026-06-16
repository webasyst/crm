<?php

/**
 * The Telegram PHP class provides a wrapper for commonly used Telegram Bot API functions.
 *
 * @see https://core.telegram.org/bots/api
 *
 * Quick Start:
 * @code
 *   $api = new crmTelegramPluginApi($access_token, crmTelegramPluginApi::netOptionsFromParams($params));
 *   $api->getMe();
 */

class crmTelegramPluginApi
{
    protected $access_token;
    protected $options;

    const API_URL = "https://api.telegram.org/bot";

    const ACTION_TYPING            = "typing";
    const ACTION_UPLOAD_PHOTO      = "upload_photo";
    const ACTION_RECORD_VIDEO      = "record_video";
    const ACTION_UPLOAD_VIDEO      = "upload_video";
    const ACTION_RECORD_AUDIO      = "record_audio";
    const ACTION_UPLOAD_AUDIO      = "upload_audio";
    const ACTION_UPLOAD_DOCUMENT   = "upload_document";
    const ACTION_FIND_LOCATION     = "find_location";
    const ACTION_RECORD_VIDEO_NOTE = "record_video_note";
    const ACTION_UPLOAD_VIDEO_NOTE = "upload_video_note";

    const ALLOWED_UPDATES_MESSAGE = "message";
    const ALLOWED_UPDATES_EDITED  = "edited_message";

    const WEBHOOK_MAX_CONNECTIONS = 40;

    public function __construct($access_token, $options = array())
    {
        $this->access_token = $access_token;
        $this->options = $options;
    }

    /** @internal string param api_proxy_type */
    const PROXY_TYPE_NONE = 'none';
    const PROXY_TYPE_HTTP = 'http';
    const PROXY_TYPE_SOCKS5 = 'socks5';

    /**
     * Build waNet proxy options from source params (or submitted form data).
     * api_proxy_type "none" (or missing with empty host) means no proxy.
     * Missing api_proxy_type with host set is treated as SOCKS5 (legacy sources).
     *
     * @param array $params
     * @return array
     */
    public static function netOptionsFromParams(array $params)
    {
        $host = isset($params['api_proxy_host']) ? trim((string) $params['api_proxy_host']) : '';
        $type_key = isset($params['api_proxy_type']) ? trim((string) $params['api_proxy_type']) : '';
        if ($type_key === '' && $host !== '') {
            $type_key = self::PROXY_TYPE_SOCKS5;
        }
        if ($type_key === '' || $type_key === self::PROXY_TYPE_NONE) {
            return array();
        }
        if ($host === '') {
            return array();
        }
        $port = isset($params['api_proxy_port']) ? (int) $params['api_proxy_port'] : 0;
        if ($port < 1 || $port > 65535) {
            return array();
        }
        $curl_proxy_type = null;
        if ($type_key === self::PROXY_TYPE_HTTP) {
            $curl_proxy_type = defined('CURLPROXY_HTTP') ? CURLPROXY_HTTP : 0;
        } elseif ($type_key === self::PROXY_TYPE_SOCKS5) {
            $curl_proxy_type = defined('CURLPROXY_SOCKS5') ? CURLPROXY_SOCKS5 : 7;
        }
        if ($curl_proxy_type === null) {
            return array();
        }
        $net = array(
            'proxy_host' => $host,
            'proxy_port' => $port,
            'proxy_type' => $curl_proxy_type,
        );
        $user = isset($params['api_proxy_user']) ? trim((string) $params['api_proxy_user']) : '';
        if ($user !== '') {
            $net['proxy_user'] = $user;
            $net['proxy_password'] = isset($params['api_proxy_password']) ? (string) $params['api_proxy_password'] : '';
        }
        return $net;
    }

    /**
     * @return array waNet constructor options merged from $this->options (proxy keys only)
     */
    protected function getWaNetOptions()
    {
        $out = array();
        if (empty($this->options['proxy_host']) || !strlen((string) $this->options['proxy_host'])) {
            return $out;
        }
        $out['proxy_host'] = $this->options['proxy_host'];
        if (isset($this->options['proxy_port'])) {
            $out['proxy_port'] = $this->options['proxy_port'];
        }
        if (array_key_exists('proxy_type', $this->options) && $this->options['proxy_type'] !== null) {
            $out['proxy_type'] = $this->options['proxy_type'];
        }
        if (!empty($this->options['proxy_user']) && strlen((string) $this->options['proxy_user'])) {
            $out['proxy_user'] = $this->options['proxy_user'];
            $out['proxy_password'] = array_key_exists('proxy_password', $this->options)
                ? $this->options['proxy_password'] : '';
        }
        return $out;
    }

    /**
     * @param resource $ch curl handle
     */
    protected function applyCurlProxyOptions($ch)
    {
        if (empty($this->options['proxy_host']) || !strlen((string) $this->options['proxy_host'])) {
            return;
        }
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
        $proxy_type = array_key_exists('proxy_type', $this->options) && $this->options['proxy_type'] !== null
            ? $this->options['proxy_type']
            : (defined('CURLPROXY_HTTP') ? CURLPROXY_HTTP : 0);
        curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy_type);
        if (!empty($this->options['proxy_port'])) {
            curl_setopt($ch, CURLOPT_PROXY, sprintf('%s:%s', $this->options['proxy_host'], $this->options['proxy_port']));
        } else {
            curl_setopt($ch, CURLOPT_PROXY, $this->options['proxy_host']);
        }
        if (!empty($this->options['proxy_user']) && strlen((string) $this->options['proxy_user'])) {
            $pwd = isset($this->options['proxy_password']) ? $this->options['proxy_password'] : '';
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, sprintf('%s:%s', $this->options['proxy_user'], $pwd));
        }
    }

    /**
     * Use this method to send text messages.
     * @see https://core.telegram.org/bots/api#sendmessage
     *
     * @param array $params (chat_id, text, parse_mode, disable_web_page_preview, disable_notification, reply_to_message_id, reply_markup)
     * @return array
     */
    public function sendMessage($params) {
        $default_params = array(
            'parse_mode' => 'HTML',
        );
        $params = array_merge($default_params, $params);
        return $this->request('sendMessage', $params);
    }

    /**
     * Use this method to send photo.
     * @see https://core.telegram.org/bots/api#sendphoto
     *
     * @param $photo
     * @param array $params (chat_id, photo, caption, disable_notification, reply_to_message_id, reply_markup)
     * @return array
     */
    public function sendPhoto($photo, $params) {
        $default_params = array(
            'parse_mode'   => 'HTML',
        );
        $params = array_merge($default_params, $params);
        return $this->multipartRequest('sendPhoto', $params, $photo, 'photo');
    }

    /**
     * Use this method to send document.
     * @see https://core.telegram.org/bots/api#senddocument
     *
     * @param $document
     * @param array $params (chat_id, document, caption, disable_notification, reply_to_message_id, reply_markup)
     * @return array
     */
    public function sendDocument($document, $params) {
        $default_params = array(
            'parse_mode'   => 'HTML',
        );
        $params = array_merge($default_params, $params);
        return $this->multipartRequest('sendDocument', $params, $document, 'document');
    }

    /**
     * Report something happening on the side of the bot
     * @see https://core.telegram.org/bots/api#sendchataction
     *
     * @param $chat_id
     * @param string $action
     */
    public function sendChatAction($chat_id, $action = self::ACTION_TYPING) {
        $params = array(
            'chat_id' => $chat_id,
            'action'  => $action
        );
        $this->request('sendChatAction', $params);
    }

    /**
     * A simple method for testing your bot's auth token. Requires no parameters.
     * @see https://core.telegram.org/bots/api#getme
     *
     * @return array - Returns basic information about the bot or an error message
     */
    public function getMe()
    {
        return $this->request('getMe');
    }

    /**
     * Method to receive incoming updates
     * @see https://core.telegram.org/bots/api#getupdates
     * @param int $offset
     * @param int $limit
     * @param string $allowed_updates
     * @return array
     */
    public function getUpdates($offset = null, $limit = 100, $allowed_updates = self::ALLOWED_UPDATES_MESSAGE)
    {
        $params = array(
            'offset'          => $offset,
            'limit'           => $limit,
            'allowed_updates' => $allowed_updates,
        );
        return $this->request('getUpdates', $params);
    }

    /**
     * Method to get up to date information about the chat
     * (current name of the user for one-on-one conversations,
     * current username of a user, group or channel, etc.).
     * @see https://core.telegram.org/bots/api#getchat
     * @param int $chat_id
     * @return array
     */
    public function getChat($chat_id)
    {
        $params = array(
            'chat_id' => $chat_id,
        );
        return $this->request('getChat', $params);
    }

    /**
     * Method to get a list of profile pictures for a user.
     * @see https://core.telegram.org/bots/api#getuserprofilephotos
     * @param int $user_id
     * @return array
     */
    public function getUserProfilePhotos($user_id) {
        $params = array(
            'user_id' => $user_id,
            'limit'   => 1,
        );
        return $this->request('getUserProfilePhotos', $params);
    }

    /**
     * Method to get basic info about a file and prepare it for downloading.
     * For the moment, bots can download files of up to 20MB in size. On success, a File object is returned.
     * The file can then be downloaded via the link
     * https://api.telegram.org/file/bot<token>/<file_path>,
     * where <file_path> is taken from the response.
     * It is guaranteed that the link will be valid for at least 1 hour.
     *
     * @see https://core.telegram.org/bots/api#getfile
     * @param string $file_id
     * @return mixed
     */
    public function getFile($file_id)
    {
        $params = array(
            'file_id' => $file_id,
        );
        return $this->request('getFile', $params);
    }

    public function setWebhook($source_id, $secret_token)
    {
        $url = wa()->getRouteUrl('crm', [
            'plugin' => 'telegram',
            'module' => 'frontend',
            'action' => 'callback',
            'source_id' => $source_id,
        ], true);
        if (empty($url)) {
            return [
                'ok' => false,
                'description' => _wd('crm_telegram', 'A CRM settlement is required.'),
            ];
        }
        $params = [
            'url' => $url,
            'max_connections' => self::WEBHOOK_MAX_CONNECTIONS,
            'allowed_updates' => self::ALLOWED_UPDATES_MESSAGE,
            'secret_token' => $secret_token,
        ];
        return $this->request('setWebhook', $params);
    }

    public function deleteWebhook()
    {
        return $this->request('deleteWebhook');
    }

    /**
     * @param $method
     * @param array $params
     * @return array
     */
    protected function request($method, $params = array()) {
        $url = self::API_URL . $this->access_token . '/' . $method;

        $net_options = array_merge([
            'timeout' => 20,
            'format' => waNet::FORMAT_JSON,
            'request_format' => waNet::FORMAT_JSON,
            'expected_http_code' => null
        ], $this->getWaNetOptions());
        $net = new waNet($net_options);

        try {
            return $net->query($url, $params, waNet::METHOD_POST);
        } catch (Exception $e) {
            return [
                'ok' => false,
                'error_code' => $e->getCode(),
                'description' => $e->getMessage()
            ];
        }
    }

    protected function multipartRequest($method, $params, $file, $type) {
        $token = $this->access_token;
        $url = self::API_URL.$token.'/'.$method;
        if (!empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        if ($file instanceof CURLFile == false) {
            $file = new CURLFile(realpath($file));
        }

        $post_fields = array(
            'chat_id' => ifset($params, 'chat_id', null),
            $type => $file,
        );

        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 20);
        $this->applyCurlProxyOptions($curl_handle);
        $raw = curl_exec($curl_handle);
        curl_close($curl_handle);
        return json_decode($raw, true);
    }
}