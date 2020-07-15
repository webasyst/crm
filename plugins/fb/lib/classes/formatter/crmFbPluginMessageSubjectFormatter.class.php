<?php

class crmFbPluginMessageSubjectFormatter extends crmFbPluginMessageContentFormatter
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
            'subject'    => ifset($this->message['subject']),
            'body'       => ifset($this->message['body']),
            'image'      => !empty($images) ? true : false,
            'video'      => !empty($videos) ? true : false,
            'audio'      => !empty($audios) ? true : false,
            'attachment' => isset($attachments['file']) ? true : false,
        );
    }

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return wa()->getAppPath("plugins/fb/templates/source/message/formatted/SubjectFormatted.html", 'crm');
    }
}