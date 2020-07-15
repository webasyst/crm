<?php

class crmFbPluginApi
{
    const API_URL = "https://graph.facebook.com";
    const API_VERSION = "v3.0";

    protected $marker_token;

    /** @var array */
    protected $options;

    /**
     * crmFbPluginApi constructor.
     * @param $marker_token
     * @param array $options
     *  - bool $options['human_agent_tag'] - If TRUE then messages will send with tag 'HUMAN_AGENT' so message could be sent in 7 days window (insteadof 24 hours)
     *      For more information see:
     *          https://developers.facebook.com/docs/messenger-platform/send-messages/message-tags
     *          https://www.facebook.com/help/contact/?id=2616212338594331
     */
    public function __construct($marker_token, $options = array())
    {
        $this->marker_token = (string)$marker_token;

        $options = is_array($options) ? $options : [];
        $this->options = $options;
    }

    /**
     * @param $user_id
     * @return array
     */
    public function getUser($user_id)
    {
        $query_params = array(
            'access_token' => $this->marker_token,
        );
        $request_url = $this->getApiUrl();
        $request_url .= $user_id;
        $request_url .= '?'. http_build_query($query_params);
        try {
            $response = $this->getNet()->query($request_url);
            return json_decode($response, true);
        } catch (waException $e) {
            return array();
        }
    }

    /**
     * Send message
     *
     * If in __construct option 'human_agent_tag' = TRUE is passed then will send tagged message with tag HUMAN_AGENT (with 7 day window)
     * otherwise simple 'RESPONSE' type message (with 24 hours window)
     *
     * @param $recipient_id
     *
     * @param array $params
     *      - string $params['text'] - text of message
     *
     *      - array $params['attachment'] [optional] - attachment of message
     *          - scalar $params['attachment']['type']
     *          - scalar $params['attachment']['id']
     *
     *      - bool $params['human_agent_tag'] [optional] - you can also redeclare for this one call similar constructor option
     *
     * @return array
     * @see https://developers.facebook.com/docs/messenger-platform/send-messages/
     */
    public function sendMessage($recipient_id, $params = array())
    {
        $params = is_array($params) ? $params : [];

        $query_params = array(
            'access_token' => $this->marker_token,
        );
        $request_url = $this->getApiUrl();
        $request_url .= 'me/messages';
        $request_url .= '?'.http_build_query($query_params);

        $is_human_agent_tagged = false;
        if (array_key_exists('human_agent_tag', $params)) {
            $is_human_agent_tagged = (bool)$params['human_agent_tag'];
        } elseif (array_key_exists('human_agent_tag', $this->options)) {
            $is_human_agent_tagged = $this->options['human_agent_tag'];
        }

        $content = array(
            'messaging_type' => 'RESPONSE',
            'recipient'      => array(
                'id' => $recipient_id,
            ),
        );

        if ($is_human_agent_tagged) {
            $content['messaging_type'] = 'MESSAGE_TAG';
            $content['tag'] = 'HUMAN_AGENT';
        }

        if (!empty($params['attachment'])) {
            $content['message']['attachment'] = array(
                'type'    => $params['attachment']['type'],
                'payload' => array(
                    'attachment_id' => $params['attachment']['id'],
                ),
            );
        } elseif (!empty($params['text'])) {
            $content['message']['text'] = $params['text'];
        }

        try {
            $response = $this->getNet()->query($request_url, $content, waNet::METHOD_POST);
            return json_decode($response, true);
        } catch (waException $e) {
            return json_decode($e->getMessage(), true);
        }
    }

    /**
     * @see https://developers.facebook.com/docs/messenger-platform/reference/attachment-upload-api/
     * @param CURLFile $file
     * @return mixed
     */
    public function sendAttachment($file)
    {
        $query_params = array(
            'access_token' => $this->marker_token,
        );
        $request_url = $this->getApiUrl();
        $request_url .= 'me/message_attachments';
        $request_url .= '?'. http_build_query($query_params);

        if ($file instanceof CURLFile == false) {
            $file = new CURLFile(realpath($file));
        }
        $message_fields = array(
            'attachment' => array(
                'type'    => $this->getTypeByMimetype($file->getMimeType()),
                'payload' => array(
                    'is_reusable' => true,
                ),
            ),
        );
        $post_fields = array(
            'message'  => json_encode($message_fields),
            'filedata' => $file,
        );

        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));
        curl_setopt($curl_handle, CURLOPT_URL, $request_url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $post_fields);
        return json_decode(curl_exec($curl_handle), true);
        curl_close($curl_handle);
    }

    protected function getApiUrl()
    {
        return self::API_URL .'/'. self::API_VERSION .'/';
    }

    protected function getNet($opts = array())
    {
        $opts['request_format'] = waNet::FORMAT_JSON;

        $custom_headers = array();

        return new waNet($opts, $custom_headers);
    }

    protected function getTypeByMimetype($mimetype) {
        $type = explode('/', $mimetype);
        if (!in_array($type[0], array('image', 'video', 'audio', 'template'))) {
            return 'file';
        }
        return $type[0];
    }
}
