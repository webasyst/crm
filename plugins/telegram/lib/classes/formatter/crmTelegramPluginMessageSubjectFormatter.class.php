<?php

class crmTelegramPluginMessageSubjectFormatter extends crmTelegramPluginMessageContentFormatter
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
            'subject'    => ifset($this->message['subject']),
            'body'       => ifset($this->message['body']),
            'is_forward' => ifset($this->message['params']['forward_contact_id']) ? true : false,
            'sticker'    => ifset($this->message['params']['sticker_id']) ? $this->prepareSticker(16) : false,
            'photo'      => ifset($this->message['params']['photo']) ? true : false,
            'audio'      => ifset($this->message['params']['audio']) ? true : false,
            'video'      => ifset($this->message['params']['video']) ? true : false,
            'voice'      => ifset($this->message['params']['voice']) ? true : false,
            'video_note' => ifset($this->message['params']['video_note']) ? true : false,
            'location'   => ifset($this->message['params']['location']) ? true : false,
            'venue'      => ifset($this->message['params']['venue_title']) ? $this->message['params']['venue_title'] : false,
            'attachment' => ifset($this->message['params']['attachment']) ? true : false,
        );
    }

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return wa()->getAppPath("plugins/telegram/templates/source/message/formatted/SubjectFormatted.html", 'crm');
    }
}