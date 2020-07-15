<?php

class crmVkPluginMessagePopupBodyFormatter extends crmVkPluginMessageContentFormatter
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
            'body' => $this->message['body'],
            'sticker' => $this->prepareSticker(20),
            'geo' => ifset($this->message['params']['geo']),
            'attachments' => (array)ifset($this->message['params']['attachments']),
            'inline_photo' => $this->prepareInlinePhoto(),
            'fwd_messages' => (array)ifset($this->message['params']['fwd_messages'])
        );
    }

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return wa()->getAppPath("plugins/vk/templates/source/message/PopupBodyFormatted.html", 'crm');
    }

    protected function prepareDocAttachments()
    {
        $attachments = array();

        return $attachments;
    }

    protected function prepareInlinePhoto()
    {
        $photo = null;
        foreach ((array)ifset($this->message['params']['attachments']) as $attachment) {
            if ($attachment['type'] == 'photo') {
                $photo = $this->preparePhotoAttachment($attachment['photo']);
                break;
            }
        }
        if ($photo) {
            return $photo;
        }
        foreach ((array)ifset($this->message['params']['attachments']) as $attachment) {
            if ($attachment['type'] == 'doc') {
                if ($attachment['doc']['type'] == 4) {
                    $photo = $this->prepareDocPhotoAttachment($attachment['doc']);
                    break;
                }
            }
        }
        return $photo;
    }

    protected function preparePhotoAttachment($photo, $width = 20)
    {
        $rate = $photo['height'] / $photo['width'];
        $height = $rate * $width;

        $photo_urls = array();
        foreach ($photo as $key => $value) {
            if (substr($key, 0, 6) == 'photo_') {
                $photo_urls[substr($key, 6)] = $value;
            }
        }

        ksort($photo_urls, SORT_NUMERIC);

        $photo_url = null;
        foreach ($photo_urls as $size => $value) {
            $photo_url = $value;
            if ($width <= $size && $height <= $size) {
                break;
            }
        }

        return array(
            'photo_url' => $photo_url,
            'width' => $width
        );
    }

    protected function prepareDocPhotoAttachment($photo, $width = 20)
    {
        $size_type = 's';

        $preview = null;
        foreach ($photo['preview']['photo']['sizes'] as $item) {
            if ($item['type'] == $size_type) {
                $preview = $item;
                break;
            }
        }
        if (!$preview) {
            $preview = reset($photo['preview']['photo']['sizes']);
        }
        $photo['preview'] = $preview;
        return array(
            'photo_url' => $preview['src'],
            'width' => $width
        );
        return $photo;
    }
}
