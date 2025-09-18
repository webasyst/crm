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
     * @var crmFileModel
     */
    protected $file_model;

    /**
     * @var crmTelegramPluginImSourceHelper
     */
    protected $helper;

    /**
     * @var crmTelegramPluginMediaDownloader
     */
    protected $downloader;

    protected $is_auto_response = false;

    protected $is_contact_updated = false;

    protected $do_not_save_this_message = false;

    protected $file_names = [];
    protected $file_paths = [];
    protected $telegram_file_ids = [];

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
        // Check uploaded photos and files
        $uploaded_photos = $this->getUploadedPhotos(ifset($data['hash']));
        $uploaded_files = $this->getUploadedFiles(ifset($data['hash']));

        $errors = $this->validate($data, (!!$uploaded_photos || !!$uploaded_files));
        if ($errors) {
            return $this->fail($errors);
        }
        $this->is_auto_response = !empty($data['is_auto_response']);
        $this->is_contact_updated = !empty($data['is_contact_updated']);
        $this->do_not_save_this_message = !empty($data['do_not_save_this_message']);

        $chat_id = ($this->message['direction'] == crmMessageModel::DIRECTION_IN) ? $this->message['from'] : $this->message['to'];
        if (!$chat_id) {
            $this->logError("Telegram user id is unknown");
            return $this->fail(array('common' => _wp("Telegram user id is unknown")));
        }

        if (ifset($data, 'verify_link', 'url', false)) {
            $data['body'] = ifset($data['verify_body'], _w('Please verify your client profile.'));
            $data['reply_markup'] = array_merge(ifset($data['reply_markup'], []), [
                'inline_keyboard' => [[
                    [
                        'text' => ifset($data['verify_link']['text'], _w('Verify client profile')),
                        'url' => $data['verify_link']['url'],
                    ]
                ]]
            ]);
        }

        $sanitizer = new crmTelegramPluginHtmlSanitizer();
        $params = [
            'chat_id' => $chat_id,
            'text'    => ifset($data['is_plain_text']) ? $sanitizer->handleMarkUp($data['body']) : $sanitizer->sanitize($data['body']),
            //'parse_mode' => $data['is_plain_text'] ? 'MarkdownV2' : 'HTML',
        ];
        if (ifset($data['reply_markup'])) {
            $params['reply_markup'] = $data['reply_markup'];
        }

        $attachments = [];
        if (!empty($uploaded_photos)) {
            foreach ($uploaded_photos as $photo_path) {
                $ext = strtolower(pathinfo($photo_path, PATHINFO_EXTENSION));
                if (in_array($ext, ['svg', 'tiff'])) {
                    // Telegram can't process these types of image
                    // so will send it as attachement
                    $uploaded_files[] = $photo_path;
                    continue;
                }
                if ($ext === 'gif' && $this->isAnimatedGif($photo_path)) {
                    // Send animated GIFs as attachement, not image
                    $uploaded_files[] = $photo_path;
                    continue;
                }

                $photo = new CURLFile(realpath($photo_path));
                // Send chat action
                $this->api->sendChatAction($chat_id, crmTelegramPluginApi::ACTION_UPLOAD_PHOTO);

                // Send photo
                $photo_params = array(
                    'chat_id' => $chat_id
                );

                // If we have only one photo, and the message text is less than two hundred
                // We will shove it into the caption, but the message will not be sent.
                if (count($uploaded_photos) == 1 && mb_strlen(ifset($params['text'], '')) <= 200 && empty($uploaded_files)) {
                    $photo_params['caption'] = $params['text'];
                    unset($params);
                }

                $response = $this->api->sendPhoto($photo, $photo_params);
                if ($response['ok']) {
                    if (isset($response['result'][crmTelegramPluginMediaDownloader::TYPE_PHOTO])) {
                        $max_thumb = end($response['result'][crmTelegramPluginMediaDownloader::TYPE_PHOTO]);
                        if (isset($max_thumb['file_id'])) {
                            $this->file_names[$max_thumb['file_id']] = basename($photo_path);
                        }
                        $attachments[][crmTelegramPluginMediaDownloader::TYPE_PHOTO] = $response['result'][crmTelegramPluginMediaDownloader::TYPE_PHOTO];
                    }
                } elseif (isset($response['error_code']) && $response['error_code'] == 400) {
                    // Telegram can't process this kind of image
                    // so will try to send it as attachement
                    $uploaded_files[] = $photo_path;
                    continue;
                }
                try {
                    waFiles::delete($photo_path);
                } catch (Exception $e) {
                }
            }
        }
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as $file_path) {
                $file_name = basename($file_path);
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
                if (count($uploaded_files) == 1 && mb_strlen(ifset($params['text'], '')) <= 200 && empty($uploaded_photos)) {
                    $doc_params['caption'] = $params['text'];
                    unset($params);
                }

                $response = (array)$this->api->sendDocument($document, $doc_params);
                if ($response['ok']) {
                    foreach([
                        crmTelegramPluginMediaDownloader::TYPE_DOCUMENT,
                        crmTelegramPluginMediaDownloader::TYPE_AUDIO,
                        crmTelegramPluginMediaDownloader::TYPE_VIDEO,
                        crmTelegramPluginMediaDownloader::TYPE_VIDEO_NOTE,
                        crmTelegramPluginMediaDownloader::TYPE_VOICE,
                    ] as $type) {
                        if (isset($response['result'][$type])) {
                            $attachments[][$type] = $response['result'][$type];
                            $this->file_names[$response['result'][$type]['file_id']] = $file_name;
                            $this->file_paths[$response['result'][$type]['file_id']] = $file_path;
                        }
                    }
                }
            }
        }

        if (!empty($params['text']) || ifset($params['text']) === '0') {
            $new_message = $this->api->sendMessage($params);
            if (!empty($attachments)) {
                $new_message['attachments'] = $attachments;
            }
        } else {
            // для пустого сообщения с вложениями
            if (!empty($attachments)) {
                $response['attachments'] = $attachments;
                unset(
                    $response['result'][crmTelegramPluginMediaDownloader::TYPE_PHOTO],
                    $response['result'][crmTelegramPluginMediaDownloader::TYPE_DOCUMENT]
                );
            }
            $new_message = ifset($response);
        }

        $new_message = (array)$new_message;
        if (!ifset($new_message['ok'])) {
            if (ifset($new_message['error_code']) === 403) {
                $this->createInternalServiceMessage(ifset($new_message['description'], _wd('crm_telegram', 'Blocked by the client.')));
            } elseif (ifset($new_message['error_code']) === 400 && $this->is_auto_response) {
                $this->createInternalServiceMessage(
                    _w('crm_telegram', 'Failed to send message:<blockquote>') . htmlspecialchars($params['text']) . '</blockquote>' .
                    _w('crm_telegram', 'Message refused by Telegram:') . '<br><i>' .
                    htmlspecialchars(ifset($new_message['description'], _wd('crm_telegram', 'Incorrect request to Telegram API.'))) . '</i>'
                );
            }
            return $this->fail([
                ifset($new_message['error_code'], 0) => htmlspecialchars(ifset($new_message['description'], _w('Unknown error')))
            ]);
        }

        $object = (array)ifset($new_message);
        $new_message = new crmTelegramPluginMessage($object);

        if ($new_message->getId() <= 0) {
            return $this->sendFailed("sendMessage result value is wrong ({$new_message->getId()})");
        }

        if ($this->do_not_save_this_message) {
            return $this->ok(['telegram_message_id' => $new_message->getId()]);
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

        $message_params = [
            'telegram_message_id' => $message->getId(),
            'username'            => $message->getChatField('username'),
            'datetime'            => $message ? $message->getDatetime() : date('Y-m-d H:i:s'),
        ];
        if ($this->is_contact_updated) {
            $message_params['is_contact_updated'] = 1;
        }

        $creator_contact_id = $this->is_auto_response ? 0 : wa()->getUser()->getId();
        $data = array(
            'creator_contact_id' => $creator_contact_id,
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $this->message['contact_id'],
            'deal_id'            => ifset($this->message['deal_id']),
            'subject'            => '',
            'body'               => crmTelegramPluginHtmlSanitizer::parser($message),
            'from'               => $message->getSenderField('username'),
            'to'                 => $message->getChatField('id'),
            'params'             => $message_params,
        );

        $this->downloader->setContext($this->message['contact_id'], ifset($this->message['deal_id']), $creator_contact_id);

        if ($message->getSticker()) {
            $data['params']['sticker_id'] = $this->downloader->getSticker($message->getSticker());
        }

        if ($message->getPhoto()) {
            $photo = $message->getPhoto();
            array_multisort(array_column($photo, 'file_size'), $photo);
            $p = end($photo);
            $this->saveFile($p, crmTelegramPluginMediaDownloader::TYPE_PHOTO, $data);
        }

        if ($message->getAudio()) {
            $this->saveFile($message->getAudio(), crmTelegramPluginMediaDownloader::TYPE_AUDIO, $data);
        }
        if ($message->getVoice()) {
            $this->saveFile($message->getVoice(), crmTelegramPluginMediaDownloader::TYPE_VOICE, $data);
        }
        if ($message->getVideo()) {
            $this->saveFile($message->getVideo(), crmTelegramPluginMediaDownloader::TYPE_VIDEO, $data);
        }
        if ($message->getVideoNote()) {
            $this->saveFile($message->getVideoNote(), crmTelegramPluginMediaDownloader::TYPE_VIDEO_NOTE, $data);
        }
        if ($message->getDocument()) {
            $this->saveFile($message->getDocument(), crmTelegramPluginMediaDownloader::TYPE_DOCUMENT, $data);
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

        if ($message->getCaption()) {
            $data['params']['caption'] = crmTelegramPluginHtmlSanitizer::parserCaption($message);
        }

        // Attachments -- files sent from CRM
        if ($message->getAttachments()) {
            foreach ($message->getAttachments() as $attachment) {
                foreach([
                    crmTelegramPluginMediaDownloader::TYPE_DOCUMENT,
                    crmTelegramPluginMediaDownloader::TYPE_AUDIO,
                    crmTelegramPluginMediaDownloader::TYPE_VIDEO,
                    crmTelegramPluginMediaDownloader::TYPE_VIDEO_NOTE,
                    crmTelegramPluginMediaDownloader::TYPE_VOICE,
                ] as $type) {
                    if (isset($attachment[$type])) {
                        $this->saveFile($attachment[$type], $type, $data);
                    }
                }
                if (isset($attachment[crmTelegramPluginMediaDownloader::TYPE_PHOTO])) {
                    $photo = $attachment[crmTelegramPluginMediaDownloader::TYPE_PHOTO];
                    array_multisort(array_column($photo, 'file_size'), $photo);
                    $p = end($photo);
                    $this->saveFile($p, crmTelegramPluginMediaDownloader::TYPE_PHOTO, $data);
                }
            }
        }

        foreach ($this->file_paths as $file_path) {
            try {
                waFiles::delete($file_path);
            } catch (Exception $e) {
            }
        }

        return $data;
    }

    protected function saveFile($f, $type, &$data)
    {
        if (in_array($f['file_id'], $this->telegram_file_ids)) {
            return;
        }
        $this->telegram_file_ids[] = $f['file_id'];
        $result = $this->downloader->downloadFile($f['file_id'], $type, ['file_name' => ifset($this->file_names[$f['file_id']])]);
        if (empty($result['crm_file_id'])) {
            if (ifset($this->file_names[$f['file_id']]) && ifset($this->file_paths[$f['file_id']])) {
                $crm_file_data = [
                    'creator_contact_id' => wa()->getUser()->getId(),
                    'name' => $this->file_names[$f['file_id']],
                    'ext' => pathinfo($this->file_names[$f['file_id']], PATHINFO_EXTENSION),
                    'source_type' => crmFileModel::SOURCE_TYPE_MESSAGE,
                ];
                if (!empty($this->message['deal_id'])) {
                    $crm_file_data['contact_id'] = -1 * $this->message['deal_id'];
                } elseif (!empty($this->message['contact_id'])) {
                    $crm_file_data['contact_id'] = $this->message['contact_id'];
                }

                $result['crm_file_id'] = $this->getFileModel()->add($crm_file_data, $this->file_paths[$f['file_id']]);
            } else {
                $data['params']['footer'] = empty($data['params']['footer']) ? '' : sprintf('%s<br>', $data['params']['footer']);
                $data['params']['footer'] .= sprintf(_wd('crm_telegram', 'File <b>%s</b> could not be saved'), ifset($this->file_names[$f['file_id']]));
                if (!empty($result['error']['description'])) {
                    $data['params']['footer'] .= ': <b>' . $result['error']['description'] . '</b>';
                }
                return;
            }
        }
        $crm_file_id = $result['crm_file_id'];
        $data['attachments'][] = $crm_file_id;
        if ($type === crmTelegramPluginMediaDownloader::TYPE_DOCUMENT) {
            $data['params']['attachment'] = true;
        } else {
            $this->getTelegramFileParamsModel()->set($crm_file_id, ['type' => $type]);
            $data['params'][$type] = true;
        }
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

    protected function validate($data, $is_attachments)
    {
        $body = (string)ifset($data['body']);
        if (!$is_attachments && strlen($body) <= 0) {
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

    protected function createInternalServiceMessage($text)
    {
        $message_id = $this->source->createMessage([
            'creator_contact_id' => 0,
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $this->message['contact_id'],
            'deal_id'            => ifset($this->message['deal_id']),
            'subject'            => '',
            'body'               => $text,
            'from'               => _wd('crm_telegram', 'Internal service message'),
            'to'                 => wa()->getUser()->getId(),
            'params'             => ['internal' => '1'],
        ], crmMessageModel::DIRECTION_OUT);
        return $message_id;
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

    /**
     * @return crmFileModel
     */
    public function getFileModel()
    {
        if (!$this->file_model) {
            $this->file_model = new crmFileModel();
        }
        return $this->file_model;
    }
}
