<?php

class crmVkPluginMessageSubjectFormatter extends crmVkPluginMessageContentFormatter
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
            'subject' => $this->message['subject'],
            'body' => $this->message['body'],
            'sticker' => $this->prepareSticker(16),
            'geo' => ifset($this->message['params']['geo']),
            'attachments' => (array)ifset($this->message['params']['attachments']),
            'links' => $this->getLinks(),
            'fwd_messages' => (array)ifset($this->message['params']['fwd_messages'])
        );
    }

    protected function getLinks()
    {
        $links = array();
        foreach ((array)ifset($this->message['params']['attachments']) as $attachment) {
            if ($attachment['type'] == 'link') {
                $link = $attachment['link'];
                $links[] = $link;
            }
        }
        return $links;
    }

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return wa()->getAppPath("plugins/vk/templates/source/message/SubjectFormatted.html", 'crm');
    }
}
