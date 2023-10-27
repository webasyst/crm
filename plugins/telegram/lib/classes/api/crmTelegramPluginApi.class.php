<?php

/**
 * The Telegram PHP class provides a wrapper for commonly used Telegram Bot API functions.
 *
 * @see https://core.telegram.org/bots/api
 *
 * Quick Start:
 * @code
 *   $api = new crmTelegramPluginApi($access_token);
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

    public function __construct($access_token, $options = array())
    {
        $this->access_token = $access_token;
        $this->options = $options;
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

    /**
     * @param $method
     * @param array $params
     * @return array
     */
    protected function request($method, $params = array()) {
        $token = $this->access_token;

        $url = self::API_URL.$token.'/'.$method;
        if (!empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        $curl_handle = curl_init();
        if (isset($params['content_type'])) {
            curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array(
                "Content-Type:".$params['content_type'],
            ));
        }
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        return (array)json_decode(curl_exec($curl_handle), true);
        curl_close($curl_handle);
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
        return json_decode(curl_exec($curl_handle), true);
        curl_close($curl_handle);
    }
}