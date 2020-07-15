<?php

class crmFbPluginImSourceMessageSender extends crmImSourceMessageSender
{
    /**
     * @var crmFbPluginApi
     */
    protected $api;

    /**
     * @var crmTwitterPluginImSourceHelper
     */
    protected $helper;

    protected static $inline_attachments_formats = array('jpe', 'jpg', 'jpeg', 'png', 'gif', 'mpeg', 'mp4', 'mpg4', 'mp3', 'mpeg3');

    public function __construct(crmSource $source, $message, array $options = array())
    {
        parent::__construct($source, $message, $options);

        $this->api = new crmFbPluginApi($this->source->getParam('access_marker'), [
            'human_agent_tag' => $this->source->getParam('human_agent_tag')
        ]);
        $this->helper = new crmFbPluginImSourceHelper($this->source);
        $this->message = crmFbPluginImSourceHelper::workupMessageForDialog($this->message);
    }

    protected function getTemplate()
    {
        return wa()->getAppPath('plugins/fb/templates/source/message/SenderDialog.html');
    }

    public function getAssigns()
    {
        return array(
            'source_id'           => $this->source->getId(),
            'app_icon_url'        => $this->getAppIcon(),
            'attachment_icon_url' => wa()->getAppStaticUrl('crm', true)."plugins/fb/img/attachment.png",
        );
    }

    public function reply($data)
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $this->fail($errors);
        }

        // Check attachments
        $uploaded_file = $this->getUploadedFile(ifset($data['hash']));
        $attachment = false;
        if ($uploaded_file) {
            $mime_type = mime_content_type(realpath($uploaded_file));
            $type = $this->getTypeByMimetype($mime_type);
            $file = new CURLFile(realpath($uploaded_file), $mime_type);
            $response = (array)$this->api->sendAttachment($file);

            if (isset($response['attachment_id'])) {
                $attachment = array(
                    'type' => $type,
                    'id'   => $response['attachment_id'],
                );
            }

            // Save attachment in server
            $downloader = new crmFbPluginDownloader();
            $crm_file_id = $downloader->downloadFile(realpath($uploaded_file));
            $file_model = new crmFileModel();
            $crm_file = $file_model->getById($crm_file_id);
            $crm_file['mime_type'] = waFiles::getMimeType($crm_file['name']);
            $crm_file['type'] = $this->getTypeByMimetype($crm_file['mime_type']);

            if (in_array($crm_file['ext'], self::$inline_attachments_formats)) {
                $data['params']['attachments'][$crm_file['ext']][] = $crm_file['id'];
            } else {
                $data['attachments'][] = $crm_file['id'];
            }

            try {
                waFiles::delete($uploaded_file);
            } catch (Exception $e) {
            }
        }

        $content = array();
        $content['text'] = ifset($data['body']);
        $content['attachment'] = $attachment;

        $res = $this->api->sendMessage($this->message['params']['fb_contact_id'], $content);
        if (!empty($res['error']['message'])) {
            return $this->fail($res['error']['message']);
        }
        if (empty($res)) {
            return $this->fail('error');
        }

        $message_id = $this->saveOutgoingMessage($data, $res);
        return $this->ok(array('message_id' => $message_id));
    }

    protected function saveOutgoingMessage($message_data, $facebook_res)
    {
        $contact = new crmContact($this->message['contact_id']);
        $data = array(
            'creator_contact_id' => wa()->getUser()->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $contact->getId(),
            'deal_id'            => $this->message['deal_id'],
            'subject'            => '',
            'body'               => ifempty($message_data['body']),
            'from'               => $this->source->getId(),
            'to'                 => $contact->getName(),
            'params'             => array(
                'fb_message_id' => ifempty($facebook_res['message_id']),
                'fb_contact_id' => ifempty($facebook_res['recipient_id']),
            )
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
        $upload_files = $this->getUploadedFile(ifempty($data['hash']));
        $body = (string)ifset($data['body']);
        if (!$upload_files && strlen($body) <= 0) {
            return array(
                'body' => _w('This field is required')
            );
        }
        return array();
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

    protected function getUploadedFile($hash)
    {
        $file_path = null;
        $temp_path = wa('crm')->getTempPath('mail', 'crm');
        $mail_dir = $temp_path.'/'.$hash;
        foreach (waFiles::listdir($mail_dir) as $file_path) {
            $file_path = $mail_dir.'/'.$file_path;
        }
        return $file_path;
    }

    protected function getAppIcon()
    {
        $info = wa()->getAppInfo('crm');
        $sizes = array_keys($info['icon']);
        $size = min($sizes);
        return $info['icon'][$size];
    }

    protected function getTypeByMimetype($mimetype) {
        $type = explode('/', $mimetype);
        if (!in_array($type[0], array('image', 'video', 'audio', 'template'))) {
            return 'file';
        }
        return $type[0];
    }
}
