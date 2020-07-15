<?php

class crmTelegramPluginImSourceMessageSender extends crmImSourceMessageSender
{
    /**
     * @var crmTelegramPluginApi
     */
    protected $api;

    /**
     * @var crmTelegramPluginStickerModel
     */
    protected $telegram_sticker_model;

    /**
     * @var crmTelegramPluginFileParamsModel
     */
    protected $telegram_file_params_model;

    /**
     * @var crmTelegramPluginImSourceHelper
     */
    protected $helper;

    /**
     * @var crmTelegramPluginMediaDownloader
     */
    protected $downloader;

    public function __construct(crmSource $source, $message, array $options = array())
    {
        parent::__construct($source, $message, $options);

        $this->api = new crmTelegramPluginApi($this->source->getParam('access_token'));
        $this->helper = new crmTelegramPluginImSourceHelper($this->source);
        $this->downloader = new crmTelegramPluginMediaDownloader($this->source, $this->api);

        $this->message = $this->helper->workupMessageForDialog($this->message);
    }

    protected function getTemplate()
    {
        return wa()->getAppPath('plugins/telegram/templates/source/message/TelegramImSourceMessageSenderDialog.html');
    }

    public function getAssigns()
    {
        return array(
            'from_html' => $this->getFromHtml(),
            'to_html'   => $this->getToHtml()
        );
    }

    protected function getFromHtml()
    {
        $template = wa()->getAppPath('plugins/telegram/templates/source/message/FromBlock.html');
        return $this->renderTemplate($template, array(
            'message'     => $this->message,
            'plugin_icon' => $this->source->getIcon(),
        ));
    }

    protected function getToHtml()
    {
        $template = wa()->getAppPath('plugins/telegram/templates/source/message/ToBlock.html');
        return $this->renderTemplate($template, array(
            'message'     => $this->message,
            'app_icon'    => $this->getAppIcon(),
            'plugin_icon' => $this->source->getIcon(),
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
        $errors = $this->validate($data);
        if ($errors) {
            return $this->fail($errors);
        }

        $chat_id = ($this->message['direction'] == crmMessageModel::DIRECTION_IN) ? $this->message['from'] : $this->message['to'];
        if (!$chat_id) {
            $this->logError("Telegram user id is unknown");
            return $this->fail(array('common' => _wp("Telegram user id is unknown")));
        }

        $params = array(
            'chat_id' => $chat_id,
            'text'    => crmTelegramPluginHtmlSanitizer::convector($data['body']),
        );

        // Check uploaded photos and files
        $uploaded_photos = $this->getUploadedPhotos(ifset($data['hash']));
        $uploaded_files = $this->getUploadedFiles(ifset($data['hash']));
        $attachments = array();
        if (!empty($uploaded_photos)) {
            foreach ($uploaded_photos as $photo_path) {
                $photo = new CURLFile(realpath($photo_path));
                // Send chat action
                $this->api->sendChatAction($chat_id, crmTelegramPluginApi::ACTION_UPLOAD_PHOTO);

                // Send photo
                $photo_params = array(
                    'chat_id' => $chat_id
                );

                // If we have only one photo, and the message text is less than two hundred
                // We will shove it into the caption, but the message will not be sent.
                if (count($uploaded_photos) == 1 && mb_strlen($params['text']) <= 200 && empty($uploaded_files)) {
                    $photo_params['caption'] = $params['text'];
                    unset($params);
                }

                $response = $this->api->sendPhoto($photo, $photo_params);
                if ($response['ok']) {
                    if (isset($response['result']['photo'])) {
                        $attachments[]['photo'] = $response['result']['photo'];
                    }
                }
                try {
                    waFiles::delete($photo_path);
                } catch (Exception $e) {
                }
            }
        }
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as $file_path) {
                $document = new CURLFile(realpath($file_path));
                // Send chat action
                $ext = mb_strtolower(pathinfo($document->getFilename(), PATHINFO_EXTENSION));
                if ($ext == 'mp4' || $ext == 'avi' || $ext == 'wmv' || $ext == 'mov' || $ext == 'mkv' || $ext == '3gp' || $ext == 'mpeg') {
                    $this->api->sendChatAction($chat_id, crmTelegramPluginApi::ACTION_UPLOAD_VIDEO);
                } else {
                    $this->api->sendChatAction($chat_id, crmTelegramPluginApi::ACTION_UPLOAD_DOCUMENT);
                }
                // Send document
                $doc_params = array(
                    'chat_id' => $chat_id
                );

                // If we only have one file, and the message text is less than two hundred
                // We will shove it into the caption, but the message will not be sent.
                if (count($uploaded_files) == 1 && mb_strlen(ifset($params['text'])) <= 200 && empty($uploaded_photos)) {
                    $doc_params['caption'] = $params['text'];
                    unset($params);
                }

                $response = (array)$this->api->sendDocument($document, $doc_params);
                if ($response['ok']) {
                    if (isset($response['result']['document'])) {
                        $attachments[]['document'] = $response['result']['document'];
                    }
                    if (isset($response['result']['audio'])) {
                        $attachments[]['audio'] = $response['result']['audio'];
                    }
                    if (isset($response['result']['video'])) {
                        $attachments[]['video'] = $response['result']['video'];
                    }
                }
                try {
                    waFiles::delete($file_path);
                } catch (Exception $e) {
                }
            }
        }

        if (isset($params)) {
            $new_message = $this->api->sendMessage($params);
            if (!empty($attachments)) {
                $new_message['attachments'] = $attachments;
            }
        } else {
            $new_message = ifset($response);
        }

        $new_message = (array)$new_message;

        if (!ifset($new_message['ok'])) {
            return $this->fail(array(ifset($new_message['error_code'], 0) => ifset($new_message['description'], 'Unkown error')));
        }

        $object = (array)ifset($new_message);
        $new_message = new crmTelegramPluginMessage($object);

        if ($new_message->getId() <= 0) {
            return $this->sendFailed("sendMessage result value is wrong ({$new_message->getId()})");
        }

        $message_id = $this->createMessage($new_message);

        return $this->ok(array(
            'telegram_message_id' => $new_message->getId(),
            'message_id'          => $message_id,
        ));
    }

    /**
     * @param crmTelegramPluginMessage $message
     * @return array
     */
    protected function prepareMessage($message)
    {
        if (!($message instanceof crmTelegramPluginMessage)) {
            $message = null;
        }

        $data = array(
            'creator_contact_id' => wa()->getUser()->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $this->message['contact_id'],
            'deal_id'            => ifset($this->message['deal_id']),
            'subject'            => '',
            'body'               => crmTelegramPluginHtmlSanitizer::parser($message),
            'from'               => $message->getSenderField('username'),
            'to'                 => $message->getChatField('id'),
            'params'             => array(
                'telegram_message_id' => $message->getId(),
                'username'            => $message->getChatField('username'),
                'datetime'            => $message ? $message->getDatetime() : date('Y-m-d H:i:s'),
            )
        );

        $fpm = $this->getTelegramFileParamsModel();

        if ($message->getSticker()) {
            $data['params']['sticker_id'] = $this->downloader->getSticker($message->getSticker());
        }

        if ($message->getAudio()) {
            $file_id = $this->downloader->getAudio($message->getAudio());
            $data['attachments'][] = $file_id;
            $fpm->set($file_id, array('type' => 'audio'));
            $data['params']['audio'] = true;
        }

        if ($message->getPhoto()) {
            $file_id = $this->downloader->getPhoto($message->getPhoto());
            $data['attachments'][] = $file_id;
            $fpm->set($file_id, array('type' => 'photo'));
            $data['params']['photo'] = true;
        }

        if ($message->getVoice()) {
            $file_id = $this->downloader->getVoice($message->getVoice());
            $data['attachments'][] = $file_id;
            $fpm->set($file_id, array('type' => 'voice'));
            $data['params']['voice'] = true;
        }

        if ($message->getVideo()) {
            $file_id = $this->downloader->getVideo($message->getVideo());
            $data['attachments'][] = $file_id;
            $fpm->set($file_id, array('type' => 'video'));
            $data['params']['video'] = true;
        }

        if ($message->getVideoNote()) {
            $file_id = $this->downloader->getVideo($message->getVideoNote());
            $data['attachments'][] = $file_id;
            $fpm->set($file_id, array('type' => 'video_note'));
            $data['params']['video_note'] = true;
        }

        if ($message->getLocation()) {
            $location = $message->getLocation();
            $data['params']['location'] = $location['latitude'].', '.$location['longitude'];
        }

        if ($message->getVenue()) {
            $venue = $message->getVenue();
            $data['params']['venue_location'] = $venue['location']['latitude'].', '.$venue['location']['longitude'];
            $data['params']['venue_title'] = ifset($venue['title']);
            $data['params']['venue_address'] = ifset($venue['address']);
            $data['params']['venue_foursquare_id'] = ifset($venue['foursquare_id']);
            unset($data['params']['location']);
        }

        if ($message->getDocument()) {
            $data['attachments'][] = $this->downloader->getDocument($message->getDocument());
            $data['params']['attachment'] = true;
        }

        //

        if ($message->getCaption()) {
            $data['params']['caption'] = crmTelegramPluginHtmlSanitizer::parserCaption($message);
        }

        // Attachments -- files sent from CRM
        if ($message->getAttachments()) {
            foreach ($message->getAttachments() as $attachment) {
                if (isset($attachment['audio'])) {
                    $file_id = $this->downloader->getAudio($attachment['audio']);
                    $data['attachments'][] = $file_id;
                    $fpm->set($file_id, array('type' => 'audio'));
                    $data['params']['audio'] = true;
                }

                if (isset($attachment['video'])) {
                    $file_id = $this->downloader->getVideo($attachment['video']);
                    $data['attachments'][] = $file_id;
                    $fpm->set($file_id, array('type' => 'video'));
                    $data['params']['video'] = true;
                }

                if (isset($attachment['photo'])) {
                    $file_id = $this->downloader->getPhoto($attachment['photo']);
                    $data['attachments'][] = $file_id;
                    $fpm->set($file_id, array('type' => 'photo'));
                    $data['params']['photo'] = true;
                }

                if (isset($attachment['document'])) {
                    $data['attachments'][] = $this->downloader->getDocument($attachment['document']);
                    $data['params']['attachment'] = true;
                }
            }
        }

        return $data;
    }

    /**
     * @param crmTelegramPluginMessage $message
     * @return int
     */
    protected function createMessage($message)
    {
        $data = $this->prepareMessage($message);
        $message_id = $this->source->createMessage($data, crmMessageModel::DIRECTION_OUT);
        return $message_id;
    }

    protected function validate($data)
    {
        $body = (string)ifset($data['body']);
        if (strlen($body) <= 0) {
            return array(
                'body' => _w('This is a required field.')
            );
        }
        return array();
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
            'status'   => 'ok',
            'response' => $response
        );
    }

    protected function sendFailed($reason)
    {
        $this->logError($reason);
        return $this->fail(array('common' => _wp("Reply cannot be sent.")));
    }

    private function logError($error)
    {
        waLog::log($error, 'crm/plugins/telegram/source/message_sender.log');
    }

    /**
     * @return crmTelegramPluginStickerModel
     */
    public function getTelegramStickerModel()
    {
        if (!$this->telegram_sticker_model) {
            $this->telegram_sticker_model = new crmTelegramPluginStickerModel();
        }
        return $this->telegram_sticker_model;
    }

    /**
     * @return crmTelegramPluginFileParamsModel
     */
    public function getTelegramFileParamsModel()
    {
        if (!$this->telegram_file_params_model) {
            $this->telegram_file_params_model = new crmTelegramPluginFileParamsModel();
        }
        return $this->telegram_file_params_model;
    }
}
