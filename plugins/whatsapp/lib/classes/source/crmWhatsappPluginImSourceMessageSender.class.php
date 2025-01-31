<?php

class crmWhatsappPluginImSourceMessageSender extends crmImSourceMessageSender
{
    /**
     * @var crmWhatsappPluginApi
     */
    protected $api;

    protected $to;

    /**
     * @var crmWhatsappPluginImSourceHelper
     */
    protected $helper;

    protected static $inline_attachments_formats = array('jpe', 'jpg', 'jpeg', 'png', 'gif', 'mpeg', 'mp4', 'mpg4', 'mp3', 'mpeg3');

    public function __construct(crmSource $source, $message, array $options = array())
    {
        parent::__construct($source, $message, $options);

        $this->api = crmWhatsappPluginApi::factory($this->source);
        $this->helper = new crmWhatsappPluginImSourceHelper($this->source);

        $this->to = ifset($this->message['params']['whatsapp_contact_phone']);
        if (empty($this->to)) {
            $contact = new waContact($this->message['contact_id']);
            $this->to = $contact->get('phone', 'default');
        }
    }

    public function reply($data)
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $this->fail($errors);
        }

        $is_auto_response = !empty($data['is_auto_response']);

        // Check attachments
        $uploaded_photos = $this->getUploadedPhotos(ifset($data['hash']));
        $uploaded_files = $this->getUploadedFiles(ifset($data['hash']));

        $uploaded_files = array_merge($uploaded_files, $uploaded_photos);

        $file_whatsapp_ids = [];
        $file_crm_ids = [];
        $file_model = new crmFileModel();

        $creator_contact_id = $is_auto_response ? 0 : wa()->getUser()->getId();
        $file_contact_id = !empty($this->message['deal_id']) ? $this->message['deal_id'] * -1 : $this->message['contact_id'];

        foreach ($uploaded_files as $uploaded_file) {
            $file_name = pathinfo($uploaded_file, PATHINFO_BASENAME);
            $ext = pathinfo($uploaded_file, PATHINFO_EXTENSION);
            $api_res = $this->api->uploadFile($uploaded_file);

            if (!empty($api_res['error']['message'])) {
                return $this->fail($api_res['error']['message']);
            }
            if (empty($api_res) || empty($api_res['id'])) {
                return $this->fail('error');
            }

            $file_whatsapp_ids[] = $api_res['id'];

            $media_type = crmWhatsappPluginDownloader::mimeType2Media($api_res['mime_type']) ?: 'document';
            if ($media_type === 'audio' && !empty($data['body'])) {
                $media_type = 'document';
            }
            $api_res = $this->api->sendMediaMessage(
                $this->to, 
                $api_res['id'], 
                $media_type, 
                $file_name,
                $data['body']
            );
            if (!empty($api_res['error']['message'])) {
                return $this->fail($api_res['error']['message']);
            }
            if (empty($api_res)) {
                return $this->fail('error');
            }
            $result = [
                'whatsapp_contact_phone' => ifset($api_res, 'contacts', 0, 'input', ''),
                'whatsapp_contact_id' => ifset($api_res, 'contacts', 0, 'wa_id', ''),
                'whatsapp_message_id' => ifset($api_res, 'messages', 0, 'id', ''),
            ];
            
            $crm_file_id = $file_model->add([
                'creator_contact_id' => $creator_contact_id,
                'contact_id'         => $file_contact_id,
                'ext' => $ext,
                'source_type' => crmFileModel::SOURCE_TYPE_MESSAGE,
            ], $uploaded_file);
            $data['attachments'] = [$crm_file_id];

            $file_crm_ids[] = $crm_file_id;
            $result['caption'] = $data['body'];
            $data['body'] = '';
            $message_id = $this->saveOutgoingMessage($data, $result);

            try {
                waFiles::delete($uploaded_file);
            } catch (Exception $e) {
            }
        }

        $text = ifset($data['body']);

        if (empty($text)) {
            return $this->ok(array('message_id' => $message_id));
        }

        $api_res = $this->api->sendTextMessage($this->to, $text);
        if (!empty($api_res['error']['message'])) {
            return $this->fail($api_res['error']['message']);
        }
        if (empty($api_res)) {
            return $this->fail('error');
        }

        $result = [
            'whatsapp_contact_phone' => ifset($api_res, 'contacts', 0, 'input', ''),
            'whatsapp_contact_id' => ifset($api_res, 'contacts', 0, 'wa_id', ''),
            'whatsapp_message_id' => ifset($api_res, 'messages', 0, 'id', ''),
        ];

        $message_id = $this->saveOutgoingMessage($data, $result);
        return $this->ok(array('message_id' => $message_id));
    }

    protected function saveOutgoingMessage($message_data, $whatsapp_res)
    {
        $contact = new crmContact($this->message['contact_id']);
        $data = array(
            'creator_contact_id' => !empty($message_data['is_auto_response']) ? 0 : wa()->getUser()->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $contact->getId(),
            'deal_id'            => $this->message['deal_id'],
            'subject'            => '',
            'body'               => ifempty($message_data['body']),
            'from'               => $this->source->getName(),
            'to'                 => $contact->getName(),
            'params'             => $whatsapp_res,
        );

        if (!empty($message_data['attachments'])) {
            $data['attachments'] = $message_data['attachments'];
        }

        // Inline Facebook attachments
        if (!empty($message_data['params']['attachments'])) {
            $data['params']['attachments'] = $message_data['params']['attachments'];
        }

        return $this->source->createMessage($data, crmMessageModel::DIRECTION_OUT);
    }

    protected function validate($data)
    {
        $body = (string)ifset($data['body']);
        $uploaded_photos = $this->getUploadedPhotos(ifset($data['hash']));
        $uploaded_files = $this->getUploadedFiles(ifset($data['hash']));
        $uploaded_files = array_merge($uploaded_photos, $uploaded_files);

        if (!$uploaded_files && !$uploaded_photos && empty($body)) {
            return [ 'body' => _w('This field is required') ];
        }

        $unsupported_files = [];
        foreach ($uploaded_files as $uploaded_file) {
            $mime_type = mime_content_type(realpath($uploaded_file));
            if (empty($mime_type) || !array_key_exists($mime_type, crmWhatsappPluginDownloader::MIME_TYPES)) {
                $unsupported_files[] = basename($uploaded_file);
            }
        }
        if (!empty($unsupported_files)) {
            foreach ($uploaded_files as $uploaded_file) {
                try {
                    waFiles::delete($uploaded_file);
                } catch (Exception $e) {
                }
            }
            return [ 'files' => 
                _wd('crm_whatsapp', 'Unsupported file: ', 'Unsupported files: ', sizeof($unsupported_files)) 
                . '<b>' . implode(', ', $unsupported_files) . '</b>'
                . '<br><span class="small">'
                . _wd('crm_whatsapp', 'Only following file types supported: ')
                . implode(', ', array_keys(crmWhatsappPluginDownloader::MIME_TYPES))
                . '</span>'
            ];
        }

        return [];
    }

    protected function fail($errors)
    {
        return array(
            'status' => 'fail',
            'errors' => is_array($errors) ? $errors : [$errors]
        );
    }

    protected function ok($response)
    {
        return array(
            'status'   => 'ok',
            'response' => $response
        );
    }

    protected function getUploadedPhotos($hash)
    {
        $file_paths = array();
        $temp_path = wa('crm')->getTempPath('mail', 'crm');
        $mail_dir = $temp_path.'/'.'photos-'.$hash;
        foreach (waFiles::listdir($mail_dir) as $file_path) {
            $full_file_path = $mail_dir.'/'.$file_path;
            $file_paths[] = $full_file_path;
        }
        return $file_paths;
    }

    protected function getUploadedFiles($hash)
    {
        $file_paths = array();
        $temp_path = wa('crm')->getTempPath('mail', 'crm');
        $mail_dir = $temp_path.'/'.'files-'.$hash;
        foreach (waFiles::listdir($mail_dir) as $file_path) {
            $full_file_path = $mail_dir.'/'.$file_path;
            $file_paths[] = $full_file_path;
        }
        return $file_paths;
    }

    protected function getTypeByMimetype($mimetype) {
        $type = explode('/', $mimetype);
        if (!in_array($type[0], array('image', 'video', 'audio', 'template'))) {
            return 'file';
        }
        return $type[0];
    }
}