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
        waFiles::create($path);
        $output = fopen($path, 'wb');

        stream_copy_to_stream($input, $output);

        fclose($input);
        fclose($output);

        return $path;
    }

    protected function allowedMediaTypes($type)
    {
        $allowed_types = array(
            'audio',
            'document',
            'photo',
            'sticker',
            'video',
            'voice',
            'video_note',
        );
        return in_array($type, $allowed_types);
    }

    protected function downloadFile($file_id, $type, $options = array())
    {
        if (!$file_id || !$type || !$this->allowedMediaTypes($type)) {
            return null;
        }
        $file_data = $this->api->getFile($file_id);
        if (!$file_data['ok']) {
            return null;
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

        $file_name = uniqid($file_data['result']['file_id'].'-', true);

        $ext = pathinfo($telegram_path, PATHINFO_EXTENSION);
        if (!$ext && $type == self::TYPE_AUDIO) {
            $ext = 'mp3';
        }
        if ($type == self::TYPE_VOICE) {
            $ext = 'ogg';
        }
        if ($type == self::TYPE_VIDEO || $type == self::TYPE_VIDEO_NOTE) {
            $ext = 'mp4';
        }
        if ($ext) {
            $file_name .= '.'.$ext;
        }

        if (isset($options['file_name'])) {
            $file_name = $options['file_name'];
        }

        $path = wa()->getTempPath('plugins/telegram/'.$type.'/'.$file_name, 'crm');

        waFiles::create($path);
        $output = fopen($path, 'wb');

        stream_copy_to_stream($input, $output);

        fclose($input);
        fclose($output);

        $data = array(
            'ext' => ifset($ext),
        );

        $file_model = new crmFileModel();
        $id = $file_model->add($data, $path);

        return $id;
    }
    protected function downloadSticker($sticker)
    {
        return $this->downloadFile($sticker['file_id'], self::TYPE_STICKER);
    }

    public function getSticker($telegram_sticker_data)
    {
        $crm_sticker = $this->getTelegramStickerModel()->getByTelegramFileId($telegram_sticker_data['file_id']);
        if ($crm_sticker) {
            return $crm_sticker['sticker_id'];
        }
        $crm_file_id = $this->downloadSticker($telegram_sticker_data);

        $new_sticker_data = array(
            'crm_file_id'      => $crm_file_id,
            'telegram_file_id' => $telegram_sticker_data['file_id'],
        );
        return $this->getTelegramStickerModel()->insert($new_sticker_data);
    }

    public function getPhoto($telegram_photo_data)
    {
        $photo_data = array_pop($telegram_photo_data);
        return $this->downloadFile($photo_data['file_id'], self::TYPE_PHOTO);
    }

    public function getAudio($telegram_audio_data)
    {
        return $this->downloadFile($telegram_audio_data['file_id'], self::TYPE_AUDIO);
    }

    public function getVoice($telegram_voice_data)
    {
        return $this->downloadFile($telegram_voice_data['file_id'], self::TYPE_VOICE);
    }

    public function getVideo($telegram_video_data)
    {
        return $this->downloadFile($telegram_video_data['file_id'], self::TYPE_VIDEO);
    }

    public function getVideoNote($telegram_video_note_data)
    {
        return $this->downloadFile($telegram_video_note_data['file_id'], self::TYPE_VIDEO_NOTE);
    }

    public function getDocument($telegram_doc_data)
    {
        return $this->downloadFile($telegram_doc_data['file_id'], self::TYPE_DOCUMENT, array('file_name' => ifset($telegram_doc_data['file_name'])));
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