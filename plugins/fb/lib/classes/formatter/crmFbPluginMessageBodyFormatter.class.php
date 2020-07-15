<?php

class crmFbPluginMessageBodyFormatter extends crmFbPluginMessageContentFormatter
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
        $attachments = isset($this->message['params']['attachments']) ? $this->message['params']['attachments'] : null;
        $images = array_merge(
            ifempty($attachments['jpe'], array()),
            ifempty($attachments['jpg'], array()),
            ifempty($attachments['jpeg'], array()),
            ifempty($attachments['png'], array()),
            ifempty($attachments['gif'], array()),
            ifempty($attachments['image'], array())
        );

        $videos = array_merge(
            ifempty($attachments['mpeg'], array()),
            ifempty($attachments['mp4'], array()),
            ifempty($attachments['mpg4'], array()),
            ifempty($attachments['video'], array())
        );

        $audios = array_merge(
            ifempty($attachments['mp3'], array()),
            ifempty($attachments['mpeg3'], array()),
            ifempty($attachments['audio'], array())
        );

        return array(
            'body'   => ifset($this->message['body_sanitized']),
            'images' => ifempty($images) ? $images : null,
            'videos' => ifempty($videos) ? $videos : null,
            'audios' => ifempty($audios) ? $audios : null,
        );
    }

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return wa()->getAppPath("plugins/fb/templates/source/message/formatted/BodyFormatted.html", 'crm');
    }
}