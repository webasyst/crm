<?php

/**
 * MAX messenger API wrapper.
 */
class crmMaxPluginApi
{
    const API_URL = 'https://platform-api.max.ru';

    /**
     * @var string
     */
    protected $token;

    protected $source_id;

    /**
     * @var array
     */
    protected $last_error;

    /**
     * @var array
     */
    protected $last_response;

    /**
     * Constructor
     *
     * @param string $token
     */
    public function __construct($token, $source_id = null)
    {
        $this->token = $token;
        $this->source_id = $source_id;
    }

    /**
     * Get the authorization token
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Make request to MAX API
     *
     * @param string $method HTTP method (GET, POST, PATCH, DELETE)
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return array|false
     */
    protected function request($method, $endpoint, $params = [])
    {
        $url = self::API_URL . $endpoint;
        //waLog::dump([$method, $endpoint, $params], 'crm/max.log');
        $headers = array(
            'Authorization: ' . $this->token,
            'Content-Type: application/json',
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

        switch (strtoupper($method)) {
            case 'GET':
                if (!empty($params)) {
                    $url .= '?' . http_build_query($params);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                break;
            case 'DELETE':
                if (!empty($params)) {
                    $url .= '?' . http_build_query($params);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->last_error = null;
        $this->last_response = null;

        if ($response === false) {
            $err_no = curl_errno($ch);
            $this->last_error = array(
                'error' => 'curl_error',
                'message' => curl_error($ch),
                'code' => $err_no,
            );
            if ($err_no != 28 || $endpoint != '/updates') {
                waLog::log('MAX API Error: ' . curl_error($ch) . ' (' . $endpoint . ', ' . $err_no . ')', 'crm/max.log');
            }
            curl_close($ch);

            return false;
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if ($http_code >= 400) {
            $error_msg = ifset($data['message'], _w('Unknown error.'));
            $error_code = ifset($data['code'], $http_code);

            $this->last_error = array(
                'error' => 'http_error',
                'message' => $error_msg,
                'code' => $error_code,
                'http_code' => $http_code,
            );
            if (waSystemConfig::isDebug()) {
                waLog::log(sprintf('MAX API HTTP Error (method: %s, endpount: %s) %d: %s', $method, $endpoint, $http_code, $error_msg), 'crm/max.log');
                waLog::dump([
                    'request' => $params,
                    'response' => $data,
                ], 'crm/max.log');
            }
            return false;
        }

        $this->last_response = $data;
        return $data;
    }

    /**
     * Get bot info
     *
     * @return array|false
     */
    public function getMe()
    {
        return $this->request('GET', '/me');
    }

    /**
     * Get updates via long polling
     *
     * @param array $params Optional parameters
     * @return array|false
     */
    public function getUpdates($params = array())
    {
        $default_params = [
            'limit' => 100,
            'timeout' => 25,
            'types' => 'message_created,message_edited,message_removed,message_callback,bot_started'
        ];
        $params = array_merge($default_params, $params);
        return $this->request('GET', '/updates', $params);
    }

    /**
     * Send text message
     *
     * @param int|string $chat_id Chat ID or user ID
     * @param string $text Message text
     * @param array $options Optional parameters
     * @return array|false
     */
    public function sendMessage($max_user_id, $text, $attachments = null, $params = [])
    {
        $url = '/messages?user_id=' . $max_user_id;
        if (!empty($params['inline_keyboard'])) {
            $attachments = $attachments ?: [];
            $attachments[] = [
                'type' => 'inline_keyboard',
                'payload' => [
                    'buttons' => $params['inline_keyboard']
                ]
            ];
            unset($params['inline_keyboard']);
        }
        $params = array_merge([
            'text' => $text,
            'attachments' => $attachments
        ], $params);

        return $this->request('POST', $url, $params);
    }

    /**
     * Send action (typing, upload, etc.)
     *
     * @param int|string $chat_id Chat ID
     * @param string $action Action type
     * @return array|false
     */
    public function sendChatAction($chat_id, $action = 'typing_on')
    {
        if (empty($chat_id)) {
            return false;
        }
        return $this->request('POST', '/chats/' . $chat_id . '/actions', array(
            'action' => $action,
        ));
    }

    /**
     * Get chat info
     *
     * @param int|string $chat_id Chat ID
     * @return array|false
     */
    public function getChat($chat_id)
    {
        return $this->request('GET', '/chats/' . $chat_id);
    }

    /**
     * Get chat members
     *
     * @param int|string $chat_id Chat ID
     * @param array $params Optional parameters
     * @return array|false
     */
    public function getChatMembers($chat_id, $params = array())
    {
        return $this->request('GET', '/chats/' . $chat_id . '/members', $params);
    }

    /**
     * Get message
     *
     * @param string $message_id Message ID
     * @return array|false
     */
    public function getMessage($message_id)
    {
        return $this->request('GET', '/messages/' . $message_id);
    }

    /**
     * Edit message
     *
     * @param string $message_id Message ID
     * @param array $params Edit parameters
     * @return array|false
     */
    public function editMessage($message_id, $params)
    {
        return $this->request('PATCH', '/messages/' . $message_id, $params);
    }

    /**
     * Delete message
     *
     * @param string $message_id Message ID
     * @return array|false
     */
    public function deleteMessage($message_id)
    {
        return $this->request('DELETE', '/messages/' . $message_id);
    }

    /**
     * Set webhook for receiving updates
     *
     * @param string $url Webhook URL
     * @param array $params Optional parameters
     * @return array|false
     */
    public function setWebhook($url = null, $params = [])
    {
        $url = empty($url) ? $this->getWebhookUrl() : $url;
        if (empty($url)) {
            return [
                'ok' => false,
                'description' => _wd('crm_max', 'A CRM site rule is required.'),
            ];
        }

        $params = array_merge([ 'url' => $url ], $params);

        return $this->request('POST', '/subscriptions', $params);
    }

    /**
     * Remove webhook
     *
     * @param string $url Webhook URL
     * @return array|false
     */
    public function removeWebhook($url = null)
    {
        if (!empty($url)) {
            $res = $this->request('DELETE', '/subscriptions', [ 'url' => $url ]);
            return ifset($res['success'], false);
        }

        $urls = $this->getWebhookUrl(true);
        if (empty($urls)) {
            return false;
        }

        $errors = [];
        foreach ($urls as $url) {
            $res = $this->request('DELETE', '/subscriptions', [ 'url' => $url ]);
            if (!ifset($res['success'], false)) {
                $errors[$url] = ifset($res['message'], ifset($this->last_error['message'], _w('Unknown error.')));
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Get current webhook subscriptions
     *
     * @return array|false
     */
    public function getSubscriptions()
    {
        return $this->request('GET', '/subscriptions');
    }

    public function uploadFile($file_path)
    {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $type = $this->getFileTypeByExt($ext);
        $upload_url_response = $this->request('POST', '/uploads?type=' . $type, []);
        if (empty($upload_url_response) || empty($upload_url_response['url'])) {
            waLog::log('MAX API get file upload url error', 'crm/max.log');
            waLog::dump($upload_url_response, 'crm/max.log');
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $upload_url_response['url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile(realpath($file_path)),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->last_error = null;
        $this->last_response = null;

        if ($response === false) {
            $this->last_error = array(
                'error' => 'curl_error',
                'message' => curl_error($ch),
                'code' => curl_errno($ch),
            );
            waLog::log('MAX API file upload error: ' . curl_error($ch), 'crm/max.log');
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        $payload = json_decode($response, true);

        if ($http_code >= 400) {
            $error_msg = ifset($payload['message'], sprintf(_wd('crm_max', 'Failed to upload the file to MAX API. HTTP error code: %d.'), $http_code));
            $error_code = ifset($payload['code'], $http_code);

            $this->last_error = array(
                'error' => 'http_error',
                'message' => $error_msg,
                'code' => $error_code,
                'http_code' => $http_code,
            );

            waLog::log('MAX API file upload error: ' . $http_code, 'crm/max.log');
            waLog::dump($payload, 'crm/max.log');
            return false;
        }

        if (empty($payload['token']) && !empty($upload_url_response['token'])) {
            $payload = [ 'token' => $upload_url_response['token'] ];
        }

        if (empty($payload)) {
            waLog::log('MAX API file upload error: ' . $http_code, 'crm/max.log');
            waLog::log($response, 'crm/max.log');
            return false;
        }

        return [
            'type' => $type,
            'payload' => $payload,
        ];
    }

    public function getActionByFilePath($file_path)
    {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $type = $this->getFileTypeByExt($ext);
        switch ($type) {
            case 'audio':
                return 'sending_audio';
            case 'video':
                return 'sending_video';
            case 'image':
                return 'sending_photo';
            default:
                return 'sending_file';
        }
    }

    public function getFileTypeByPath($file_path)
    {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        return $this->getFileTypeByExt($ext);
    }

    public function getFileTypeByExt($ext)
    {
        switch (strtolower($ext)) {
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
                return 'image';
            case 'mp4':
            case 'avi':
            case 'wmv':
            case 'mov':
            case 'mkv':
            case '3gp':
            case 'webm':
                return 'video';
            case 'mp3':
            case 'aac':
            case 'ogg':
            //case 'flac': // remove FLAC as not supported by MAX as audio
            case 'm4a':
            //case 'wav': // remove WAV as not supported by MAX as audio
            //case 'amr': // remove AMR as not supported by MAX as audio
                return 'audio';
            default:
                return 'file';
        }
    }

    /**
     * Send callback answer (for inline keyboards)
     *
     * @param string $callback_id Callback ID
     * @param string|null $text Notification text
     * @param bool $show_alert Show as alert
     * @return array|false
     */
    public function answerCallback($callback_id, $text = null, $show_alert = false)
    {
        $params = array(
            'callback_id' => $callback_id,
        );
        if ($text !== null) {
            $params['text'] = $text;
            $params['show_alert'] = $show_alert;
        }
        return $this->request('POST', '/callbacks', $params);
    }

    /**
     * Get last error
     *
     * @return array|null
     */
    public function getLastError()
    {
        return $this->last_error;
    }

    /**
     * Get last response
     *
     * @return array|null
     */
    public function getLastResponse()
    {
        return $this->last_response;
    }

    /**
     * Check if token is valid
     *
     * @return bool
     */
    public function isTokenValid()
    {
        $me = $this->getMe();
        return !empty($me) && !isset($me['error']);
    }

    public function getWebhookUrl($all_available = false)
    {
        if (!$all_available) {
            $url = wa()->getRouteUrl('crm', [
                'plugin' => 'max',
                'module' => 'frontend',
                'action' => 'callback',
                'source_id' => $this->source_id,
            ], true);

            return $url;
        }

        $domains = array_keys(wa()->getRouting()->getByApp('crm'));
        return array_map(function ($domain) {
            return wa()->getRouteUrl('crm', [
                'plugin' => 'max',
                'module' => 'frontend',
                'action' => 'callback',
                'source_id' => $this->source_id,
            ], true, $domain);
        }, $domains);
    }
}
