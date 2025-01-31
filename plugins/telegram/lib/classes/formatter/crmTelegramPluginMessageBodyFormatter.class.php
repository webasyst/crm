<?php

class crmTelegramPluginMessageBodyFormatter extends crmTelegramPluginMessageContentFormatter
{
    public static function format($message)
    {
        $formatter = new self($message);
        return $formatter->execute();
    }

    /**
     * @return array
     */
    protected function getAssigns()
    {
        return array(
            'body'             => ifset($this->message['body_sanitized']),
            'caption'          => ifset($this->message['params']['caption_sanitized']),
            'sticker'          => ifset($this->message['params']['sticker_id']) ? $this->prepareSticker(256) : null,
            'photo'            => ifset($this->message['photo']) ? $this->preparePhoto() : null,
            'audio'            => ifset($this->message['audio']) ? $this->prepareAudio() : null,
            'video'            => ifset($this->message['video']) ? $this->prepareVideo() : null,
            'voice'            => ifset($this->message['voice']) ? $this->prepareVoice() : null,
            'video_note'       => ifset($this->message['video_note']) ? $this->prepareVideoNote() : null,
            'location'         => ifset($this->message['params']['location']) ? $this->prepareLocation() : null,
            'venue'            => ifset($this->message['params']['venue_location']) ? $this->prepareVenue() : null,
            'is_forward'       => ifset($this->message['params']['forward_contact_id']) ? true : false,
            'forward_contact'  => ifset($this->message['forward_contact']),
            'forward_name'     => ifset($this->message['params']['forward_name']),
            'forward_username' => ifset($this->message['params']['forward_username']),
        );
    }

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return wa()->getAppPath("plugins/telegram/templates/source/message/formatted/BodyFormatted.html", 'crm');
    }

    protected function preparePhoto()
    {
        $photo_files = (array) ifset($this->message['photo']);
        return array(
            'files' => $photo_files,
        );
    }

    protected function prepareAudio()
    {
        $audio_files = (array) ifset($this->message['audio']);
        if (!$audio_files) {
            return array(
                'files' => null,
            );
        }

        foreach ($audio_files as &$file) {
            $file['ext'] = $file['ext'] ? $file['ext'] : pathinfo($file['name'], PATHINFO_EXTENSION);
        }
        unset($file);

        return array(
            'files' => $audio_files,
        );
    }

    protected function prepareVideo()
    {
        $video_files = (array) ifset($this->message['video']);
        return array(
            'files' => $video_files,
        );
    }

    protected function prepareVoice()
    {
        $voice_file = (array) ifset($this->message['voice']);
        return array(
            'files'  => $voice_file,
        );
    }

    protected function prepareVideoNote()
    {
        $video_note_file = (array) ifset($this->message['video_note']);
        return array(
            'files' => $video_note_file,
        );
    }

    protected function prepareLocation()
    {
        try {
            $map_html = wa()->getMap()->getHTML($this->message['params']['location'], array('width' => '470px', 'height' => '270px', 'zoom' => 16));
        } catch (waException $e) {
            $map_html = _wd('crm_telegram', 'Unknown location');
        }

        return array(
            'map_html' => $map_html,
        );
    }

    protected function prepareVenue()
    {
        try {
            $map_html = wa()->getMap()->getHTML($this->message['params']['venue_location'], array('width' => '470px', 'height' => '270px', 'zoom' => 16));
        } catch (waException $e) {
            $map_html = _wd('crm_telegram', 'Unknown venue');
        }

        return array(
            'title'            => $this->message['params']['venue_title'],
            'address'          => ifset($this->message['params']['venue_address']),
            'map_html'         => $map_html,
            'foursquare_id'    => ifset($this->message['params']['venue_foursquare_id']),
            'foursquare_icon'  => wa()->getAppStaticUrl('crm/plugins/telegram/img', true).'foursquare-icon.png',
        );
    }
}
