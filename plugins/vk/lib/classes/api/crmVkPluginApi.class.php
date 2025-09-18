<?php

class crmVkPluginApi
{
    protected $options = array();

    protected $access_token;

    protected $url = 'https://api.vk.ru/method/';

    static protected $version = '5.131';

    const ATTACH_TYPE_PHOTO = 'photo';
    const ATTACH_TYPE_DOC = 'doc';

    public function __construct($access_token, $options = array())
    {
        $this->access_token = $access_token;
        $this->options = $options;
    }

    public static function getVersion()
    {
        return self::$version;
    }

    public function getLang()
    {
        if (isset($this->options['lang'])) {
            $lang = $this->options['lang'];
        } else {
            $lang = wa()->getUser()->getLocale();
            $lang = substr($lang, 0, 2);
        }
        return $lang;
    }

    public function setLang($lang)
    {
        $this->options['lang'] = $lang;
    }

    public function query($method, $params = array())
    {
        $params['lang'] = $this->getLang();
        $params['v'] = self::$version;

        $url = $this->buildUrl($method);
        $net = new waNet(
            array(
                'request_format' => 'raw',
                'format' => 'json',
            )
        );

        return $net->query($url, $params, waNet::METHOD_POST);
    }

    public function getUsers($ids, $fields = array())
    {
        $ids = (array)$ids;
        $fields = (array)$fields;

        if (empty($ids)) {
            return array();
        }

        $params = array(
            'user_ids' => join(',', $ids)
        );
        if ($fields) {
            $params['fields'] = join(',', $fields);
        }

        $res = $this->query('users.get', $params);
        if (empty($res) || empty($res['response'])) {
            $this->logFailedResponse('users.get', $params, $res);
            return array();
        }

        return $res['response'];
    }

    public function getUser($id, $fields = array())
    {
        $users = $this->getUsers((array)$id, (array)$fields);
        return $users ? $users[0] : null;
    }

    public function getCities($ids)
    {
        $ids = crmHelper::toIntArray($ids);
        if (!$ids) {
            return array();
        }
        $params = array(
            'city_ids' => join(',', $ids)
        );
        $res = $this->query('database.getCitiesById', $params);
        if (empty($res) || empty($res['response'])) {
            $this->logFailedResponse('database.getCitiesById', $params, $res);
            return array();
        }
        return $res['response'];
    }

    public function getCity($id)
    {
        $cities = $this->getCities((array)$id);
        return $cities ? $cities[0] : null;
    }

    public function getCountries($ids)
    {
        $ids = crmHelper::toIntArray($ids);
        if (!$ids) {
            return array();
        }
        $params = array(
            'country_ids' => join(',', $ids)
        );
        $res = $this->query('database.getCountriesById', $params);
        if (empty($res) || empty($res['response'])) {
            $this->logFailedResponse('database.getCountriesById', $params, $res);
            return array();
        }
        return $res['response'];
    }

    public function getCountry($id)
    {
        $countries = $this->getCountries((array)$id);
        return $countries ? $countries[0] : null;
    }

    public function getGroups($ids, $fields = array())
    {
        $ids = crmHelper::toIntArray($ids);
        if (!$ids) {
            return array();
        }
        $params = array(
            'group_ids' => join(',', $ids)
        );
        if ($fields) {
            $params['fields'] = join(',', $fields);
        }
        $res = $this->query('groups.getById', $params);
        if (empty($res) || empty($res['response'])) {
            $this->logFailedResponse('groups.getById', $params, $res);
            return array();
        }
        return $res['response'];
    }

    /**
     * @param $id
     * @param array $fields
     * @return array|null
     */
    public function getGroup($id, $fields = array())
    {
        $groups = $this->getGroups((array)$id, (array)$fields);
        return $groups ? $groups[0] : null;
    }

    public function getMessages($ids, $fields = array())
    {
        $ids = crmHelper::toIntArray($ids);
        if (!$ids) {
            return array();
        }
        $params = array(
            'message_ids' => join(',', $ids)
        );
        if ($fields) {
            $params['fields'] = join(',', $fields);
        }
        $res = $this->query('messages.getById', $params);
        if (empty($res) || empty($res['response'])) {
            $this->logFailedResponse('messages.getById', $params, $res);
            return array();
        }
        return (array)ifset($res['response']['items']);
    }

    /**
     * @param $id
     * @param array $fields
     * @return array|null
     */
    public function getMessage($id, $fields = array())
    {
        $messages = $this->getMessages((array)$id, (array)$fields);
        return $messages ? $messages[0] : null;
    }

    /**
     * @param array $params
     * https://vk.ru/dev/messages.markAsRead
     * @return bool
     */
    public function markAsRead($params)
    {
        if (!empty($params['message_ids'])) {
            sort($params['message_ids'], SORT_NUMERIC);
            $start_message_id = reset($params['message_ids']);
            $params['start_message_id'] = $start_message_id;
            unset($params['message_ids']);
        }
        $res = $this->query('messages.markAsRead', $params);
        if (empty($res) || empty($res['response'])) {
            $this->logFailedResponse('messages.markAsRead', $params, $res);
            return false;
        }
        return !!ifset($res['response']);
    }

    /**
     * Send message
     * @param int $user_id
     * @param string $message
     * @param array $params
     * @return int ID of message, ID == 0 means failure
     */
    public function sendMessage($user_id, $message, $params = array())
    {
        if (is_numeric($user_id)) {
            $params['user_id'] = $user_id;
        } else {
            $params['domain'] = $user_id;
        }

        // Normalize line endings
        $message = str_replace("\r\n", "\n", $message);
        $message = str_replace("\r", "\n", $message);

        $params['message'] = $message;
        $params['random_id'] = 0;
        $params['payload'] = strval(time());

        $res = $this->query('messages.send', $params);
        if (empty($res) || empty($res['response'])) {
            $this->logFailedResponse('messages.send', $params, $res);
            return 0;
        }
        return $res['response'];
    }

    /**
     * Send message with attachments
     * @param int $user_id
     * @param string $message
     * @param array $attachments array of map 'type' => attachments, that have been received from attach* methods
     * @param array $params
     * @return int ID of message, ID == 0 means failure
     */
    public function sendMessageWithAttachments($user_id, $message, $attachments = array(), $params = array())
    {
        if ($attachments && is_array($attachments)) {
            $params['attachment'] = array();
            foreach ($attachments as $type => $_attachments) {
                foreach ($_attachments as $attachment) {
                    $attachment_id = "{$type}{$attachment['owner_id']}_{$attachment['id']}";
                    $params['attachment'][] = $attachment_id;
                }
            }
            $params['attachment'] = join(',', $params['attachment']);
        }
        return $this->sendMessage($user_id, $message, $params);
    }

    public function attachFile($peer_id, $file_path, $attach_type)
    {
        if (!file_exists($file_path)) {
            return false;
        }

        if ($attach_type !== self::ATTACH_TYPE_DOC && $attach_type !== self::ATTACH_TYPE_PHOTO) {
            throw new crmVkPluginException("Unknown attach type");
        }

        $upload_url = $this->getMessagesUploadServer($peer_id, $attach_type);
        if (!$upload_url) {
            return false;
        }

        $res = $this->uploadFile($upload_url, $file_path, $attach_type);
        if (!$res) {
            return false;
        }

        $res = $this->saveFile($res, $attach_type);
        if (!$res) {
            return false;
        }

        return $res;
    }

    protected function saveFile($file_info, $type)
    {
        $result = false;
        if ($type == self::ATTACH_TYPE_PHOTO) {
            $res = $this->query('photos.saveMessagesPhoto', $file_info);
            if (empty($res) || (empty($res['response'][0]))) {
                $this->logFailedResponse('photos.saveMessagesPhoto', [], $res);
            } else {
                $result = $res['response'][0];
            }
        } elseif ($type == self::ATTACH_TYPE_DOC) {
            $res = $this->query('docs.save', $file_info);
            if (empty($res) || empty($res['response']['doc'])) {
                $this->logFailedResponse('docs.save', [], $res);
            } else {
                $result = $res['response']['doc'];
            }
        } else {
            throw new crmVkPluginException("Unknown attach type");
        }

        return $result;
    }

    protected function uploadFile($upload_url, $file_path, $type)
    {
        $ch = curl_init($upload_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $param_key = null;
        if ($type == self::ATTACH_TYPE_PHOTO) {
            $param_key = 'photo';
        } elseif ($type == self::ATTACH_TYPE_DOC) {
            $param_key = 'file';
        } else {
            throw new crmVkPluginException("Unknown attach type");
        }

        $this->curlCustomPostFields($ch, array(), array($param_key => $file_path));

        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (empty($res) || empty($res[$param_key])) {
            $this->logFailedResponse($upload_url, [$param_key => $file_path], $res);
            return false;
        }

        return $res;
    }

    /**
     *
     * @see http://php.net/manual/en/class.curlfile.php
     * (curl_custom_postfields)
     *
     * For safe multipart POST request for PHP5.3 ~ PHP 5.4.
     *
     * @param resource $ch cURL resource
     * @param array $assoc "name => value"
     * @param array $files "name => path"
     * @return bool
     */
    protected function curlCustomPostFields($ch, array $assoc = array(), array $files = array()) {

        // invalid characters for "name" and "filename"
        static $disallow = array("\0", "\"", "\r", "\n");

        // build normal parameters
        foreach ($assoc as $k => $v) {
            $k = str_replace($disallow, "_", $k);
            $body[] = implode("\r\n", array(
                "Content-Disposition: form-data; name=\"{$k}\"",
                "",
                filter_var($v),
            ));
        }

        // build file parameters
        foreach ($files as $k => $v) {
            $v = realpath(filter_var($v));
            if (
                false === $v
                || !is_file($v)
                || !is_readable($v)
            ) {
                continue; // or return false, throw new InvalidArgumentException
            }

            $data = file_get_contents($v);
            
            $parts = explode(DIRECTORY_SEPARATOR, $v);
            $v = end($parts);
            $k = str_replace($disallow, "_", $k);
            $v = str_replace($disallow, "_", $v);
            $body[] = implode("\r\n", array(
                "Content-Disposition: form-data; name=\"{$k}\"; filename=\"{$v}\"",
                "Content-Type: application/octet-stream",
                "",
                $data,
            ));
        }

        // generate safe boundary
        do {
            $boundary = "---------------------" . md5(mt_rand() . microtime());
        } while (preg_grep("/{$boundary}/", $body));

        // add boundary for each parameters
        array_walk($body, function (&$part) use ($boundary) {
            $part = "--{$boundary}\r\n{$part}";
        });

        // add final boundary
        $body[] = "--{$boundary}--";
        $body[] = "";

        // set options
        return @curl_setopt_array($ch, array(
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => implode("\r\n", $body),
            CURLOPT_HTTPHEADER => array(
                "Expect: 100-continue",
                "Content-Type: multipart/form-data; boundary={$boundary}", // change Content-Type
            ),
        ));
    }

    protected function getMessagesUploadServer($peer_id, $type)
    {
        if ($type === self::ATTACH_TYPE_PHOTO) {
            $url = 'photos.getMessagesUploadServer';
        } elseif ($type === self::ATTACH_TYPE_DOC) {
            $url = 'docs.getMessagesUploadServer';
        } else {
            throw new crmVkPluginException("Unknown attach file");
        }

        $params = ['peer_id' => $peer_id];
        $res = $this->query($url, $params);

        if (empty($res) || empty($res['response'])) {
            $this->logFailedResponse($url, $params, $res);
            return false;
        }
        return $res['response']['upload_url'];
    }
    
    public function getVideos($ids)
    {
        $ids = crmHelper::toIntArray($ids);
        if (!$ids) {
            return array();
        }
        $params = array(
            'videos' => join(',', $ids)
        );
        $url = 'video.get';
        $res = $this->query($url, $params);
        if (empty($res) || empty($res['response'])) {
            $this->logFailedResponse($url, $params, $res);
            return array();
        }
        return (array)ifset($res['response']['items']);
    }

    protected function buildUrl($method, $params = array())
    {
        $params['access_token'] = $this->access_token;
        $url = $this->url . $method . '?' . http_build_query($params);
        return $url;
    }

    protected function logFailedResponse($url, $params, $response)
    {
        waLog::dump([ 
            'url' => $url,
            'params' => $params,
            'response' => $response,
        ], 'crm/plugins/vk/api/failed_responses.log');
    }
}
