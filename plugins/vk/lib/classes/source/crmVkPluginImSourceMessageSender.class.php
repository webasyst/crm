<?php

class crmVkPluginImSourceMessageSender extends crmImSourceMessageSender
{
    /**
     * @var crmVkPluginChat
     */
    protected $chat;

    public function __construct(crmSource $source, $message, array $options = array())
    {
        /**
         * @var crmVkPluginImSource $source
         */
        parent::__construct($source, $message, $options);
        crmVkPluginImSourceHelper::markMessageAsRead($this->message, array(
            'access_token' => $source->getAccessToken()
        ));
        $this->message = crmVkPluginImSourceHelper::workupMessageForDialog($this->message);

        $chat_id = $this->message['params']['chat_id'];
        $this->chat = crmVkPluginChat::factory($chat_id);
    }

    public function getAssigns()
    {
        return array(
            'from_html' => $this->getFromHtml(),
            'to_html' => $this->getToHtml()
        );
    }

    protected function getTemplate()
    {
        return wa()->getAppPath('plugins/vk/templates/source/message/SenderDialog.html');
    }

    protected function getFromHtml()
    {
        if ($this->message['direction'] == crmMessageModel::DIRECTION_IN) {
            $template_name = "InMessageFromBlock.html";
        } else {
            $template_name = "OutMessageFromBlock.html";
        }
        $template = wa()->getAppPath("plugins/vk/templates/source/message/{$template_name}");
        return $this->renderTemplate($template, array(
            'message' => $this->message,
            'app_icon_url' => $this->getAppIcon(),
            'hash' => md5(time().wa()->getUser()->getId()),
            'source_icon_url' => $this->source->getIcon(),
        ));
    }

    protected function getToHtml()
    {
        if ($this->message['direction'] == crmMessageModel::DIRECTION_IN) {
            $template_name = "InMessageToBlock.html";
        } else {
            $template_name = "OutMessageToBlock.html";
        }
        $template = wa()->getAppPath("plugins/vk/templates/source/message/{$template_name}");
        return $this->renderTemplate($template, array(
            'message' => $this->message,
            'app_icon_url' => $this->getAppIcon(),
            'source_icon_url' => $this->source->getIcon(),
        ));
    }

    protected function getAppIcon()
    {
        $info = wa()->getAppInfo('crm');
        $sizes = array_keys($info['icon']);
        $size = min($sizes);
        return $info['icon'][$size];
    }

    public function reply($data)
    {
        if ($this->message['direction'] == crmMessageModel::DIRECTION_IN) {
            $vk_user_id = $this->message['from'] ? $this->message['from'] : '';
        } else {
            $vk_user_id = $this->message['to'] ? $this->message['to'] : '';
        }

        if (!$vk_user_id) {
            return $this->sendFailed("Vk user id is unknown");
        }

        // for ID like 'id1234111' remove 'id' prefix
        if (substr($vk_user_id, 0, 2) == 'id') {
            $num_vk_user_id = substr($vk_user_id, 2);
            if (is_numeric($num_vk_user_id)) {
                $vk_user_id = $num_vk_user_id;
            }
        }

        $attachments = $this->prepareAttachments($data, $vk_user_id);

        $errors = $this->validate($data, $attachments);
        if ($errors) {
            return $this->fail($errors);
        }

        $vk_message_id = $this->sendMessage($vk_user_id, $data['body'], $attachments);
        if ($vk_message_id <= 0) {
            return $this->sendFailed("sendMessage result value is wrong ({$vk_message_id})");
        }

        $message_id = $this->logMessage($vk_message_id);
        $this->chat->addMessage($message_id);

        return $this->ok(array(
            'vk_message_id' => $vk_message_id,
            'message_id' => $message_id
        ));
    }

    protected function sendMessage($vk_user_id, $body, $attachments = array())
    {
        $api = new crmVkPluginApi($this->source->getAccessToken());
        return $api->sendMessageWithAttachments($vk_user_id, $body, $attachments);
    }

    protected function prepareAttachments($data, $vk_user_id)
    {
        if (!isset($data['hash'])) {
            return array();
        }

        $uploaded_photos = $this->getUploadedPhotos($data['hash']);
        $uploaded_files = $this->getUploadedFiles($data['hash']);

        if (!$uploaded_photos && !$uploaded_files) {
            return array();
        }

        $attachments = array(
            crmVkPluginApi::ATTACH_TYPE_PHOTO => array(),
            crmVkPluginApi::ATTACH_TYPE_DOC => array()
        );

        if (!wa_is_int($vk_user_id)) {
            $params = $this->chat->getParticipant()->getParams();
            if (isset($params['screen_name']) && $params['screen_name'] == $vk_user_id && isset($params['id'])) {
                $vk_user_id = $params['id'];
            } else {
                $vk_user = new crmVkPluginVkUser($vk_user_id);
                $info = $vk_user->getInfo();
                $vk_user_id = $info['id'];
            }
        }

        $api = new crmVkPluginApi($this->source->getAccessToken());

        foreach ($uploaded_photos as $photo) {
            $attachment = $api->attachFile($vk_user_id, $photo, crmVkPluginApi::ATTACH_TYPE_PHOTO);
            if ($attachment) {
                $attachments[crmVkPluginApi::ATTACH_TYPE_PHOTO][] = $attachment;
                try {
                    waFiles::delete($photo);
                } catch (Exception $e) {
                    //nop
                }
            }
        }

        foreach ($uploaded_files as $file) {
            $attachment = $api->attachFile($vk_user_id, $file, crmVkPluginApi::ATTACH_TYPE_DOC);
            if ($attachment) {
                $attachments[crmVkPluginApi::ATTACH_TYPE_DOC][] = $attachment;
                try {
                    waFiles::delete($file);
                } catch (Exception $e) {
                    //nop
                }
            }
        }

        return $attachments;
    }

    protected function getUploadedPhotos($hash)
    {
        return $this->getUploadedFiles($hash, 'photos-');
    }

    protected function getUploadedFiles($hash, $prefix = 'files-')
    {
        $file_paths = array();
        $temp_path = wa('crm')->getTempPath('mail', 'crm');
        $mail_dir = $temp_path.'/'.$prefix.$hash;
        foreach (waFiles::listdir($mail_dir) as $file_path) {
            $full_file_path = $mail_dir.'/'.$file_path;
            $file_paths[] = $full_file_path;
        }
        return $file_paths;
    }

    protected function fail($errors)
    {
        return array(
            'status' => 'fail',
            'errors' => $errors
        );
    }

    protected function ok($response)
    {
        return array(
            'status' => 'ok',
            'response' => $response
        );
    }

    protected function sendFailed($reason)
    {
        $this->logError($reason);
        return $this->fail(array('common' => _wp("Sorry, couldn't send reply message")));
    }

    protected function logMessage($vk_message_id)
    {
        try {
            $vk_message = new crmVkPluginVkMessage($vk_message_id, array(
                'access_token' => $this->source->getAccessToken()
            ));
            $message = $this->chat->prepareMessageData(crmMessageModel::DIRECTION_OUT, $vk_message);
        } catch (crmVkPluginException $e) {
            $message = $this->chat->prepareMessageData(crmMessageModel::DIRECTION_OUT);
            $message['params']['id'] = $vk_message_id;
        }
        $message_id = $this->source->createMessage($message, crmMessageModel::DIRECTION_OUT);
        return $message_id;
    }

    protected function validate($data, $attachments = array())
    {
        $body = (string)ifset($data['body']);
        if (strlen($body) <= 0 && $this->isAllEmpty($attachments)) {
            return array(
                '' => 'All empty'
            );
        }
        return array();
    }

    protected function isAllEmpty($array)
    {
        foreach ($array as $value) {
            if (!empty($value)) {
                return false;
            }
        }
        return true;
    }

    private function logError($error)
    {
        waLog::log($error, 'crm/plugins/vk/source/message_sender.log');
    }
}
