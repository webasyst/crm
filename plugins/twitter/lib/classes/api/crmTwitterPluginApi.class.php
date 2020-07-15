<?php

/**
 * The crmTwitterPluginApi class represents a shell for using the functions
 * of Twitter API within the sources of deal in the CRM app.
 *
 * @see https://developer.twitter.com/en/docs/basics/getting-started
 *
 * Quick Start:
 * @code:
 *      $api = new crmTwitterPluginApi($source->getParams());
 *      $api->getMe();
 */
class crmTwitterPluginApi
{
    const API_URL = 'https://api.twitter.com';
    const API_VERSION = '1.1';

    const OAUTH_VERSION = '1.0';

    const METHOD_MENTIONS = 'mentions';
    const METHOD_MENTIONS_URL = 'statuses/mentions_timeline.json';

    const METHOD_SEND_TWEET = 'send_tweet';
    const METHOD_SEND_TWEET_URL = 'statuses/update.json';

    const METHOD_DIRECT_MESSAGES = 'direct_messages';
    const METHOD_DIRECT_MESSAGES_URL = 'direct_messages/events/list.json';

    const METHOD_SEND_DIRECT_MESSAGE = 'message_create';
    const METHOD_SEND_DIRECT_MESSAGE_URL = 'direct_messages/events/new.json';

    const METHOD_VERIFY_CREDENTIALS = 'verify_credentials';
    const METHOD_VERIFY_CREDENTIALS_URL = 'account/verify_credentials.json';

    const METHOD_GET_USER = 'users/show';
    const METHOD_GET_USER_URL = 'users/show.json';

    protected $username;
    protected $settings;

    protected $method_url;
    protected $request_method;
    protected $request_format;

    protected $oauth;

    /**
     * @var array
     */
    protected $get_fields;

    /**
     * @var array
     */
    protected $post_fields;

    /**
     * crmTwitterPluginApi constructor.
     * @param array $settings (access_token, access_token_secret, consumer_key, consumer_secret)
     * @throws waException
     */
    public function __construct($settings = array())
    {
        $fields = array('consumer_key', 'consumer_secret', 'access_token', 'access_token_secret');
        foreach ($fields as $field) {
            if (empty($settings[$field])) {
                throw new waException($field.' not passed');
            }
        }
        foreach ($settings as $key => $value) {
            $this->settings[$key] = $value;
        }
    }

    /**
     * @return array|SimpleXMLElement|string
     * @throws waException
     */
    public function getMe()
    {
        $this->setMethodUrl(self::METHOD_VERIFY_CREDENTIALS);
        $this->setRequestMethod(waNet::METHOD_GET);
        $this->setRequestFormat(waNet::FORMAT_RAW);
        $this->setGetFields(array());
        return json_decode($this->getNet()->query($this->method_url, $this->getGetFields(), $this->request_method), true);
    }

    /**
     * The method returns the last mention of the account
     * @see https://developer.twitter.com/en/docs/tweets/timelines/api-reference/get-statuses-mentions_timeline
     * @return array|SimpleXMLElement|string
     * @throws waException
     */
    public function getMentions()
    {
        $this->setMethodUrl(self::METHOD_MENTIONS);
        $this->setRequestMethod(waNet::METHOD_GET);
        $this->setRequestFormat(waNet::FORMAT_RAW);
        $this->setGetFields(array('name' => $this->settings['username']));
        return json_decode($this->getNet()->query($this->method_url, $this->getGetFields(), $this->request_method), true);
    }

    /**
     * The method sends a tweet on behalf of the user Twitter API
     * @see https://developer.twitter.com/en/docs/tweets/post-and-engage/api-reference/post-statuses-update
     * @param string $text
     * @param int|null $in_reply_to_status_id
     * @return mixed
     * @throws waException
     */
    public function sendTweet($text, $in_reply_to_status_id = null)
    {
        $post_fields = array(
            'status' => trim($text),
        );
        if ($in_reply_to_status_id) {
            $post_fields['in_reply_to_status_id']        = $in_reply_to_status_id;
        }
        $this->setMethodUrl(self::METHOD_SEND_TWEET);
        $this->setRequestMethod(waNet::METHOD_POST);
        $this->setRequestFormat(waNet::FORMAT_RAW);
        $this->setPostFields($post_fields);
        return json_decode($this->getNet()->query($this->method_url, $this->getPostFields(), $this->request_method), true);
    }

    /**
     * The method returns the last direct messages of the account
     * @see https://developer.twitter.com/en/docs/direct-messages/sending-and-receiving/api-reference/list-events
     * @return array|SimpleXMLElement|string
     * @throws waException
     */
    public function getDirectMessages()
    {
        $this->setMethodUrl(self::METHOD_DIRECT_MESSAGES);
        $this->setRequestMethod(waNet::METHOD_GET);
        $this->setRequestFormat(waNet::FORMAT_RAW);
        $this->setGetFields(array('count' => 50));
        return json_decode($this->getNet()->query($this->method_url, $this->getGetFields(), $this->request_method), true);
    }

    /**
     * The method sends a private message on behalf of the user Twitter API
     * @see https://developer.twitter.com/en/docs/direct-messages/sending-and-receiving/api-reference/new-event
     * @param int $recipient_id
     * @param string $text // TODO: Learn the limit on the number of characters in a message
     * @return array|SimpleXMLElement|string
     * @throws waException
     */
    public function sendDirectMessasge($recipient_id, $text)
    {
        $post_fields = array(
            'event' => array(
                'type' => 'message_create',
                'message_create' => array(
                    'target' => array(
                        'recipient_id' => $recipient_id,
                    ),
                    'message_data' => array(
                        'text' => $text,
                    ),
                ),
            ),
        );
        $this->setMethodUrl(self::METHOD_SEND_DIRECT_MESSAGE);
        $this->setRequestMethod(waNet::METHOD_POST);
        $this->setRequestFormat(waNet::FORMAT_JSON); // !!!
        $this->setPostFields($post_fields);
        return json_decode($this->getNet()->query($this->method_url, $this->getPostFields(), $this->request_method), true);
    }

    public function getUser($data)
    {
        $this->setMethodUrl(self::METHOD_GET_USER);
        $this->setRequestMethod(waNet::METHOD_GET);
        $this->setRequestFormat(waNet::FORMAT_RAW);
        $this->setGetFields($data);
        return json_decode($this->getNet()->query($this->method_url, $this->getGetFields(), $this->request_method), true);
    }

    /**
     * @param array $fields
     * @throws waException
     */
    protected function setGetFields(array $fields)
    {
        if ($this->getPostfields()) {
            throw new waException('You can only choose get or post fields.');
        }
        $this->get_fields = $fields;
    }

    /**
     * @param array $fields
     * @throws waException
     */
    protected function setPostFields(array $fields)
    {
        if ($this->getGetfields()) {
            throw new waException('You can only choose get or post fields.');
        }

        foreach ($fields as $key => &$value) {
            if (is_bool($value)) {
                $value = ($value === true) ? 'true' : 'false';
            }
        }

        $this->post_fields = $fields;
        // rebuild OAuth
        if (isset($this->oauth['oauth_signature'])) {
            $this->buildOAuth();
        }
    }

    /**
     * @return array $this->get_fields
     */
    protected function getGetFields()
    {
        return (array)$this->get_fields;
    }

    /**
     * @return array $this->post_fields
     */
    protected function getPostFields()
    {
        return (array)$this->post_fields;
    }

    /**
     * @param string $method
     * @return $this
     */
    protected function setMethodUrl($method)
    {
        switch ($method) {
            case self::METHOD_VERIFY_CREDENTIALS:
                $method_path = self::METHOD_VERIFY_CREDENTIALS_URL;
                break;
            case self::METHOD_MENTIONS:
                $method_path = self::METHOD_MENTIONS_URL;
                break;
            case self::METHOD_SEND_TWEET:
                $method_path = self::METHOD_SEND_TWEET_URL;
                break;
            case self::METHOD_DIRECT_MESSAGES:
                $method_path = self::METHOD_DIRECT_MESSAGES_URL;
                break;
            case self::METHOD_SEND_DIRECT_MESSAGE:
                $method_path = self::METHOD_SEND_DIRECT_MESSAGE_URL;
                break;
            case self::METHOD_GET_USER:
                $method_path = self::METHOD_GET_USER_URL;
                break;
            default:
                $method_path = '';
        }

        $url = self::API_URL.'/'.self::API_VERSION.'/'.$method_path;
        $this->method_url = $url;
        return $this;
    }

    /**
     * @param $request_method
     * @return $this
     * @throws waException
     */
    protected function setRequestMethod($request_method)
    {
        $request_method = strtoupper($request_method);
        $available_methods = array('POST', 'GET', 'PUT', 'DELETE');

        if (!in_array($request_method, $available_methods)) {
            throw new waException('Request method must be either POST, GET or PUT or DELETE');
        }

        $this->request_method = $request_method;
        return $this;
    }

    protected function setRequestFormat($request_format)
    {
        $this->request_format = $request_format;
    }

    /**
     * Build the Oauth object using params set in construct and additionals
     * passed to this method.
     * @see https://developer.twitter.com/en/docs/basics/authentication/overview/using-oauth
     */
    protected function buildOAuth()
    {
        $oauth = array(
            'oauth_consumer_key'     => $this->settings['consumer_key'],
            'oauth_nonce'            => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_token'            => $this->settings['access_token'],
            'oauth_timestamp'        => time(),
            'oauth_version'          => self::OAUTH_VERSION,
        );

        $get_fields = $this->getGetfields();
        if (!empty($get_fields)) {
            foreach ($get_fields as $key => $value) {
                $oauth[$key] = $value;
            }
        }

        // JSON body is not included in the generation of the OAuth signature
        // https://developer.twitter.com/en/docs/direct-messages/sending-and-receiving/guides/direct-message-migration
        if ($this->request_format !== waNet::FORMAT_JSON) {
            $post_fields = $this->getPostfields();
            if (!empty($post_fields)) {
                foreach ($post_fields as $key => $value) {
                    $oauth[$key] = $value;
                }
            }
        }

        $base_info = $this->buildBaseString($oauth);
        $composite_key = rawurlencode($this->settings['consumer_secret']).'&'.rawurlencode($this->settings['access_token_secret']);
        $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
        $oauth['oauth_signature'] = $oauth_signature;
        $this->oauth = $oauth;
        return $this;
    }

    /**
     * Private method to generate the base string used by cURL
     * @param array $params
     * @return string Built base string
     */
    private function buildBaseString($params)
    {
        $return = array();
        ksort($params);
        foreach($params as $key => $value)
        {
            $return[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        return $this->request_method."&".rawurlencode($this->method_url).'&'.rawurlencode(implode('&', $return));
    }

    /**
     * Private method to generate authorization header used by cURL
     * @return string $return Header used by cURL for request
     */
    private function buildAuthorizationHeader()
    {
        $this->buildOAuth();
        $oauth = $this->oauth;

        $return = 'OAuth ';
        $values = array();
        foreach ($oauth as $key => $value) {
            if (in_array($key, array(
                    'oauth_consumer_key',
                    'oauth_nonce',
                    'oauth_signature',
                    'oauth_signature_method',
                    'oauth_timestamp',
                    'oauth_token',
                    'oauth_version'
                )
            )
            ) {
                $values[] = $key.'="'.rawurlencode($value).'"';
            }
        }
        $return .= implode(', ', $values);
        return $return;
    }

    protected function getNet($opts = array())
    {
        $opts['expected_http_code'] = null;
        $custom_headers = array(
            'Authorization' => $this->buildAuthorizationHeader(),
        );

        if ($this->request_format == waNet::FORMAT_JSON) {
            $opts['request_format'] = $this->request_format;
        }

        return new waNet($opts, $custom_headers);
    }
}
