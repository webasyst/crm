<?php

class crmTelegramPluginMediaDownloader
{
    const TYPE_AUDIO      = 'audio';
    const TYPE_VOICE      = 'voice';
    const TYPE_DOCUMENT   = 'document';
    const TYPE_PHOTO      = 'photo';
    const TYPE_STICKER    = 'sticker';
    const TYPE_VIDEO      = 'video';
    const TYPE_VIDEO_NOTE = 'video_note';

    /**
     * @var crmTelegramPluginStickerModel
     */
    protected $telegram_sticker_model;

    /**
     * @var crmTelegramPluginAudioModel
     */
    protected $telegram_audio_model;

    /**
     * @var crmTelegramPluginVideoModel
     */
    protected $telegram_video_model;

    /**
     * @var crmSource
     */
    protected $source;

    /**
     * @var crmTelegramPluginApi
     */
    protected $api;

    protected $client_contact_id = null;

    protected $creator_contact_id = 0;

    protected $deal_id = null;

    /**
     * @param crmSource $source
     * @param crmTelegramPluginApi $api
     * crmTelegramPluginMediaDownloader constructor.
     */
    public function __construct($source, $api)
    {
        $this->source = $source;
        $this->api = $api;
    }

    public function setContext($client_contact_id, $deal_id = null, $creator_contact_id = 0)
    {
        $this->client_contact_id = $client_contact_id;
        $this->deal_id = $deal_id;
        $this->creator_contact_id = $creator_contact_id;
    }

    public function clearContext()
    {
        $this->deal_id = null;
        $this->client_contact_id = null;
    }

    /**
     * @param crmContact $contact
     * @throws waException
     */
    public function setContactPhoto(crmContact &$contact)
    {
        if (!$contact->get('telegram_id')) {
            return;
        }
        if ($contact->get('photo')) {
            return;
        }

        $user_photos = $this->api->getUserProfilePhotos($contact->get('telegram_id'));
        if (!$user_photos['ok']) {
            return;
        }
        if (empty($user_photos['result']['photos'])) {
            return;
        };
        $file = array_pop($user_photos['result']['photos']['0']);
        $file_data = $this->api->getFile($file['file_id']);
        if (!$file_data['ok']) {
            return;
        }
        $file_path = $file_data['result']['file_path'];
        if (strtolower(substr($file_path, -4)) !== '.jpg') {
            return;
        }
        $url = 'https://api.telegram.org/file/bot'.$this->source->getParam('access_token').'/'.$file_path;
        $path = $this->downloadProfilePhoto($url);
        try {
            $contact->setPhoto($path);
            waFiles::delete($path);
        } catch (Exception $e) {

        }
    }

    protected function downloadProfilePhoto($url)
    {
        $protocol = substr($url, 0, 5) === 'https' ? 'https' : 'http';
        $context_options = array(
            $protocol => array(
                'method' => 'GET'
            )
        );
        $context = stream_context_create($context_options);
        $input = fopen($url, 'rb', false, $context);

        $path = wa()->getTempPath('plugins/telegram/'.uniqid('userpic', true), 'crm');
        $output = fopen($path, 'wb');

        stream_copy_to_stream($input, $output);

        fclose($input);
        fclose($output);

        return $path;
    }

    protected function allowedMediaTypes($type)
    {
        $allowed_types = [
            self::TYPE_AUDIO,
            self::TYPE_DOCUMENT, 
            self::TYPE_PHOTO, 
            self::TYPE_STICKER, 
            self::TYPE_VIDEO, 
            self::TYPE_VOICE,
            self::TYPE_VIDEO_NOTE,
        ];
        return in_array($type, $allowed_types);
    }

    public function downloadFile($file_id, $type, $options = array())
    {
        if (!$file_id || !$type || !$this->allowedMediaTypes($type)) {
            return [
                'crm_file_id' => null,
                'error' => [
                    'code' => 0,
                    'description' => 'Invalid file type',
                ]
            ];
        }
        $file_data = $this->api->getFile($file_id);
        if (!$file_data['ok']) {
            return [
                'crm_file_id' => null,
                'error' => [
                    'code' => $file_data['error_code'],
                    'description' => $file_data['description'],
                ],
            ];
        }

        $telegram_path = $file_data['result']['file_path'];
        $telegram_url = 'https://api.telegram.org/file/bot'.$this->source->getParam('access_token').'/'.$telegram_path;

        $protocol = substr($telegram_url, 0, 5) === 'https' ? 'https' : 'http';
        $context_options = array(
            $protocol => array(
                'method' => 'GET'
            )
        );
        $context = stream_context_create($context_options);
        $input = fopen($telegram_url, 'rb', false, $context);

        $file_name = pathinfo($telegram_path, PATHINFO_BASENAME);
        $ext = pathinfo($telegram_path, PATHINFO_EXTENSION);
        if (empty($ext)) {
            if ($type == self::TYPE_AUDIO) {
                $ext = 'mp3';
            } elseif ($type == self::TYPE_VOICE) {
                $ext = 'ogg';
            } elseif ($type == self::TYPE_VIDEO || $type == self::TYPE_VIDEO_NOTE) {
                $ext = 'mp4';
            }
            if (!empty($ext)) {
                $file_name .= '.'.$ext;
            }
        }

        if (isset($options['file_name'])) {
            $file_name = $options['file_name'];
            $ext_orig = pathinfo($file_name, PATHINFO_EXTENSION);
            if (!empty($ext) && $ext_orig != $ext) {
                $file_name = pathinfo($file_name, PATHINFO_FILENAME).'.'.$ext;
            }
        }

        $tmp_file_name = uniqid($file_data['result']['file_id'].'-', true);
        if (!empty($ext)) {
            $tmp_file_name .= '.'.$ext;
        }
        $path = wa()->getTempPath('plugins/telegram/'.$type, 'crm') .'/'.$tmp_file_name;
        $output = fopen($path, 'wb');

        stream_copy_to_stream($input, $output);

        fclose($input);
        fclose($output);

        $data = [
            'creator_contact_id' => $this->creator_contact_id,
            'name' => $file_name,
            'ext' => ifset($ext),
            'source_type' => crmFileModel::SOURCE_TYPE_MESSAGE,
        ];
        if (!empty($this->deal_id)) {
            $data['contact_id'] = -1 * $this->deal_id;
        } elseif (!empty($this->client_contact_id)) {
            $data['contact_id'] = $this->client_contact_id;
        }

        $file_model = new crmFileModel();
        $id = $file_model->add($data, $path);

        return [
            'crm_file_id' => $id,
            'error' => null,
        ];
    }

    protected function downloadSticker($sticker)
    {
        return $this->downloadFile($sticker['file_id'], self::TYPE_STICKER, ['file_name' => ifset($sticker['file_name'])]);
    }

    public function getSticker($telegram_sticker_data)
    {
        $crm_sticker = $this->getTelegramStickerModel()->getByTelegramFileId($telegram_sticker_data['file_id']);
        if ($crm_sticker) {
            return $crm_sticker['sticker_id'];
        }
        $result = $this->downloadSticker($telegram_sticker_data);
        if (empty($result['crm_file_id'])) {
            return;
        }

        $new_sticker_data = array(
            'crm_file_id'      => $result['crm_file_id'],
            'telegram_file_id' => $telegram_sticker_data['file_id'],
        );
        return $this->getTelegramStickerModel()->insert($new_sticker_data);
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
     * @return crmTelegramPluginAudioModel
     */
    public function getTelegramAudioModel()
    {
        if (!$this->telegram_audio_model) {
            $this->telegram_audio_model = new crmTelegramPluginAudioModel();
        }
        return $this->telegram_audio_model;
    }

    /**
     * @return crmTelegramPluginVideoModel
     */
    public function getTelegramVideoModel()
    {
        if (!$this->telegram_video_model) {
            $this->telegram_video_model = new crmTelegramPluginVideoModel();
        }
        return $this->telegram_video_model;
    }
}