<?php

class crmWhatsappPluginDownloader
{
    /* crmWhatsappPluginApi */
    protected $api;

    protected $access_token;

    protected $media_proxy;

    const MEDIA_URL = 'https://lookaside.fbsbx.com/whatsapp_business/attachments/';

    const MIME_TYPES = [
        'text/plain' => [ 'ext' => 'txt', 'media' => 'document' ], 
        'application/pdf' => [ 'ext' => 'pdf', 'media' => 'document'], 
        'application/vnd.ms-powerpoint' => [ 'ext' => 'ppt', 'media' => 'document'], 
        'application/msword' => [ 'ext' => 'doc', 'media' => 'document'], 
        'application/vnd.ms-excel' => [ 'ext' => 'xls', 'media' => 'document'], 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [ 'ext' => 'docx', 'media' => 'document'], 
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => [ 'ext' => 'pptx', 'media' => 'document'], 
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [ 'ext' => 'xlsx', 'media' => 'document'],
        'image/jpeg' => [ 'ext' => 'jpg', 'media' => 'image'],
        'image/png' => [ 'ext' => 'png', 'media' => 'image'],
        'image/webp' => [ 'ext' => 'webp', 'media' => 'sticker'],
        'video/mp4' => [ 'ext' => 'mp4', 'media' => 'video'],
        'video/3gp' => [ 'ext' => '3gp', 'media' => 'video'],
        'audio/mpeg' => [ 'ext' => 'mp3', 'media' => 'audio'],
        'audio/aac' => [ 'ext' => 'aac', 'media' => 'audio'],
        'audio/ogg' => [ 'ext' => 'ogg', 'media' => 'audio'],
        'audio/mp4' => [ 'ext' => 'mp4', 'media' => 'audio'],
        'audio/amr' => [ 'ext' => 'amr', 'media' => 'audio'],
    ];

    public function __construct($access_token, $phone_number_id, $options = [])
    {
        $this->access_token = $access_token;
        $this->api = ifset($options, 'api', null) ?: new crmWhatsappPluginApi($access_token, $phone_number_id, null, $options);
        $this->media_proxy = ifset($options, 'media_proxy', null);
    }

    public static function factory($source, $options = [])
    {
        $access_token = $source->getParam('access_token');
        $phone_number_id = $source->getParam('phone_number_id');
        $options = ['api' => crmWhatsappPluginApi::factory($source, $options)];
        $media_proxy = $source->getParam('media_proxy');
        if (!empty($media_proxy)) {
            $options['media_proxy'] = $media_proxy;
        }
        return new self($access_token, $phone_number_id, $options);
    }

    public function downloadMedia($media_id, $file_name = null) {
        $data = $this->api->getMediaData($media_id);
        if (empty($data) || empty($data['url'])) {
            return false;
        }
        
        if (empty($file_name)) {
            $file_ext = self::mimeType2Ext($data['mime_type']);
            if (empty($file_ext)) {
                return false;
            }
            $file_name = 'whatsapp_'.$media_id.'.'.$file_ext;
        }
        
        $url = $data['url'];
        if (!empty($this->media_proxy) && strpos($url, self::MEDIA_URL) === 0) {
            $url = str_replace(self::MEDIA_URL, $this->media_proxy, $url);
        }

        $path = wa()->getTempPath('plugins/whatsapp/attachments', 'crm') . '/' . $file_name;
        $output = fopen($path, 'w+');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$this->access_token]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, sprintf('Webasyst-CRM/%s', wa()->getVersion('crm')));
        curl_setopt($ch, CURLOPT_FILE, $output);

        try {
            curl_exec($ch);
            $err = curl_errno($ch);
            curl_close($ch);
        } catch (Exception $e) {
            return false;
        }
        fclose($output);

        if ($err !== 0) {
            return false;
        }

        return $path;
    }

    public static function mimeType2Ext($mime_type) {
        $mime_type = strtolower($mime_type);
        if (in_array($mime_type, array_keys(self::MIME_TYPES))) {
            return self::MIME_TYPES[$mime_type]['ext'];
        }
        return false;
    }

    public static function mimeType2Media($mime_type) {
        $mime_type = strtolower($mime_type);
        if (in_array($mime_type, array_keys(self::MIME_TYPES))) {
            return self::MIME_TYPES[$mime_type]['media'];
        }
        return false;
    }
}