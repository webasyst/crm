<?php

class crmWhatsappPluginApi
{
    protected $access_token;
    protected $phone_number_id;
    protected $business_account_id;
    protected $options;
    protected $api_endpoint; // api proxy url 'https://wadev.webasyst.com/v19.0/'
    protected $app_mode;

    const API_URL = 'https://graph.facebook.com/v19.0/';

    public function __construct($access_token, $phone_number_id, $business_account_id = null, $options = [])
    {
        $this->access_token = $access_token;
        $this->phone_number_id = $phone_number_id;
        $this->business_account_id = $business_account_id;
        $this->api_endpoint = ifset($options, 'api_endpoint', self::API_URL);
        $this->app_mode = ifset($options, 'app_mode', 'live');
        $this->options = $options;
    }

    public static function factory(crmWhatsappPluginImSource $source, $options = [])
    {
        $access_token = $source->getParam('access_token');
        $phone_number_id = $source->getParam('phone_id');
        $business_account_id = $source->getParam('account_id');
        $api_endpoint = $source->getParam('api_endpoint');
        if (!empty($api_endpoint)) {
            $options['api_endpoint'] = $api_endpoint;
        }
        $app_mode = $source->getParam('app_mode');
        if (!empty($app_mode)) {
            $options['app_mode'] = $app_mode;
        }
        return new self($access_token, $phone_number_id, $business_account_id, $options);
    }

    public function getPhoneNumber() {
        return $this->call('');
    }

    public function sendTextMessage($to, $text) {
        return $this->call('messages', waNet::METHOD_POST, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->dumb($to),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text,
            ]
        ]);
    }

    public function sendMediaMessage($to, $media_id, $media_type, $file_name = null, $caption = null) 
    {
        $media = ['id' => $media_id];
        if ($media_type === 'document' && !empty($file_name)) {
            $media['filename'] = $file_name;
        }
        if (!empty($caption)) {
            $media['caption'] = $caption;
        }
        return $this->call('messages', waNet::METHOD_POST, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->dumb($to),
            'type' => $media_type,
            $media_type => $media,
        ]);
    }

    public function sendTemplateMessage($to, $template_name, $template_lang, $template_params = []) 
    {
        $components = [];

        $body_params = array_map(function($param) {
            return [
                'type' => 'text',
                'text' => $param,
            ];
        }, ifset($template_params, 'body_params', []));

        if (!empty($body_params)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_values($body_params),
            ];
        }

        $header_params = array_map(function($param) {
            return [
                'type' => 'text',
                'text' => $param,
            ];
        }, ifset($template_params, 'header_params', []));

        $image = null;
        if (!empty($template_params['header_image_url'])) {
            $image = ['link' => $template_params['header_image_url']];
        } elseif (!empty($template_params['header_image_id'])) {
            $image = ['id' => $template_params['header_image_id']];
        }

        if (!empty($image)) {
            $header_params[] = [
                'type' => 'image',
                'image' => $image,
            ];
        }

        if (!empty($header_params)) {
            $components[] = [
                'type' => 'header',
                'parameters' => array_values($header_params),
            ];
        }

        return $this->call('messages', waNet::METHOD_POST, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->dumb($to),
            'type' => 'template',
            'template' => [
                'name' => $template_name,
                'language' => [
                    'code' => $template_lang,
                ],
                'components' => $components,
            ],
        ]);
    }

    public function uploadFile($file_path) {
        $mime_type = mime_content_type(realpath($file_path));
        if (empty($mime_type) || !array_key_exists($mime_type, crmWhatsappPluginDownloader::MIME_TYPES)) {
            return [
                'error' => [
                    'code' => 'unallowed_mime_type',
                    'message' => sprintf(_w('Unallowed mime type: %s'), $mime_type),
                ]
            ];
        }

        $url = $this->api_endpoint . $this->phone_number_id . '/media';
        $file = new CURLFile(realpath($file_path), $mime_type, basename($file_path));
        $params = [
            'messaging_product' => 'whatsapp',
            'type' => $mime_type,
            'file' => $file
        ];

        $res = null;
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$this->access_token, 
            'Content-Type: multipart/form-data'
        ]);
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $params);
        try {
            $res = waUtils::jsonDecode(curl_exec($curl_handle), true);
        } catch (Exception $e) {
            return [
                'error' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ]
            ];
        }
        curl_close($curl_handle);
        
        if (empty($res) || !empty($res['error']) || empty($res['id'])) {
            return $res;
        }
        return [
            'id' => $res['id'],
            'mime_type' => $mime_type,
        ];
    }

    public function getMediaData($media_id) 
    {
        return $this->mediaCall($media_id);
    }

    public function getTemplateList($filter = [])
    {
        $filter = [
            'status' => 'APPROVED',
            'limit' => 10,
        ] + $filter;
        $res = $this->businessCall('message_templates', waNet::METHOD_GET, $filter);
        if (empty($res) || empty($res['data'])) {
            return $res;
        }
        $res['data'] = $this->convertTemplateComponents($res['data']);
        return $res;
    }

    public function getTemplate($name, $lang) 
    {
        $res = $this->getTemplateList(['name' => $name, 'language' => $lang]);
        if (empty($res) || empty($res['data']) || !is_array($res['data'])) {
            return $res;
        }
        return $res['data'][0];
    }

    protected function convertTemplateComponents($data)
    {
        return array_map(function($item) {
            $components = $item['components'];
            foreach ($components as $component) {
                $_type = ifset($component['type']);
                switch ($_type) {
                    case 'BODY':
                        $item['body'] = ifset($component['text'], '');
                        $item['body_vars'] = ifset($component, 'example', 'body_text', 0, []);
                        break;
                    case 'FOOTER':
                        $item['footer'] = ifset($component['text'], '');
                        break;
                    case 'HEADER':
                        $item['header'] = ifset($component['text'], '');
                        $item['header_image_example_url'] = (ifset($component['format']) === 'IMAGE') ? ifset($component, 'example', 'header_handle', 0, '') : '';
                        $item['header_vars'] = (ifset($component['format']) === 'TEXT') ? ifset($component, 'example', 'header_text', []) : [];
                }
            }
            unset($_type);
            unset($item['components']);
            return $item;
        }, $data);
    }

    protected function call($method, $http_method = waNet::METHOD_GET, $params = [], $net_options = [])
    {
        $url = $this->api_endpoint . $this->phone_number_id . '/' . $method;
        return $this->request($url, $http_method, $params, $net_options);
    }

    protected function mediaCall($media_id, $http_method = waNet::METHOD_GET, $params = [], $net_options = [])
    {
        $url = $this->api_endpoint . $media_id;
        return $this->request($url, $http_method, $params, $net_options);
    }

    protected function businessCall($method, $http_method = waNet::METHOD_GET, $params = [], $net_options = [])
    {
        $url = $this->api_endpoint . $this->business_account_id . '/' . $method;
        return $this->request($url, $http_method, $params, $net_options);
    }

    protected function request($url, $http_method = waNet::METHOD_GET, $params = [], $net_options = []) {
        $net_options += [
            'timeout' => 20,
            'format' => waNet::FORMAT_JSON,
            'request_format' => waNet::FORMAT_JSON,
            'expected_http_code' => null
        ];
        $headers = [
            'Authorization' => "Bearer {$this->access_token}"
        ];
        $net = new waNet($net_options, $headers);

        try {
            return $net->query($url, $params, $http_method);
        } catch (Exception $e) {
            return [
                'error' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ]
            ];
        }
    }

    protected function dumb($to) {        
        if ($this->app_mode === 'dev' && strpos($to, '7') === 0 && strlen($to) === 11) {
            // Это какаята идиотия со стороны Whatsapp - они добавляют 8 после стартовой 7 в номер телефона.
            // На этапе разработки приложения нужно в номере телефона добавлять 78 вместо 7 - иначе, не пропускает.
            // При этом, если приложение переведено в рабочий режим, то все нормально.
            $to = '78'.substr($to, 1);
        }
        return $to;
    }
}