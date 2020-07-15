<?php

class crmVkPluginMessageBodyFormatter extends crmVkPluginMessageContentFormatter
{
    public static function format($message)
    {
        $formatter = new self($message);
        return $formatter->execute();
    }

    protected function workupBody($body)
    {
        $formatter = new crmVkPluginBodyHtmlFormatter();
        $body = $formatter->execute($body);
        return $body;
    }


    /**
     * @return array
     */
    protected function getAssigns()
    {
        return array(
            'body' => $this->workupBody($this->message['body']),
            'sticker' => $this->prepareSticker(64),
            'geo' => $this->prepareGeo(),
            'inline_attachments' => $this->prepareInlineAttachments(),
            'doc_attachments' => $this->prepareDocAttachments(),
            'link_attachments' => $this->prepareLinkAttachments(),
            'fwd_messages' => (array)ifset($this->message['params']['fwd_messages'])
        );
    }

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return wa()->getAppPath("plugins/vk/templates/source/message/BodyFormatted.html", 'crm');
    }

    protected function prepareDocAttachments()
    {
        $attachments = array();
        foreach ((array)ifset($this->message['params']['attachments']) as $attachment) {
            if ($attachment['type'] == 'doc') {
                if ($attachment['doc']['type'] == 4) {
                    $attachments[] = $this->prepareDocPhotoAttachment($attachment['doc']);
                } else {
                    $attachments[] = $attachment['doc'];
                }
            }
        }
        return $attachments;
    }

    protected function prepareLinkAttachments()
    {
        $attachments = array();
        foreach ((array)ifset($this->message['params']['attachments']) as $attachment) {
            if ($attachment['type'] == 'link') {
                $link = $attachment['link'];

                if (isset($link['photo'])) {
                    $photo_info = $this->prepareLinkPhoto($link['photo']);
                    $link['photo'] = $photo_info;
                } else {
                    $link['photo'] = array();
                }

                $attachments[] = $link;
            }
        }
        return $attachments;
    }

    protected function prepareLinkPhoto($photo, $width = 200)
    {
        if (!is_array($photo)) {
            $photo = array();
        }
        if (!isset($photo['height']) || !isset($photo['width'])) {
            return array();
        }

        $rate = $photo['height'] / $photo['width'];
        $photo_url = $this->choosePhotoUrlByWidth($width, $photo);
        $height = $rate * $width;
        return array(
            'photo_url' => $photo_url,
            'width' => $this->formatNumber($width),
            'height' => $this->formatNumber($height)
        );
    }

    protected function prepareInlineAttachments()
    {
        $attachments = array();
        foreach ((array)ifset($this->message['params']['attachments']) as $attachment) {
            if ($attachment['type'] == 'photo') {
                $attachments[] = $this->preparePhotoAttachment($attachment['photo']);
            } elseif ($attachment['type'] == 'wall') {
                $attachments[] = $this->prepareWallAttachment($attachment['wall']);
            }
        }
        return $attachments;
    }

    protected function preparePhotoAttachmentObjectThumbs($photo, $width = 350)
    {
        $photo_thumbs = array();
        foreach ($photo['sizes'] as $thumb) {
            $photo_thumbs[$thumb['width']] = $thumb;
        }

        ksort($photo_thumbs, SORT_NUMERIC);

        $photo_url = null;
        $height = null;
        foreach ($photo_thumbs as $_width => $thumb) {
            $photo_url = $thumb['url'];
            $height = $thumb['height'];
            if ($width <= $_width) {
                break;
            }
        }

        return array(
            'type' => 'photo',
            'photo_url' => $photo_url,
            'width' => $this->formatNumber($width),
            'height' => $this->formatNumber($height)
        );
    }

    protected function preparePhotoAttachmentObjectWithPhotoUrls($photo, $width = 350)
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
            'type' => 'photo',
            'photo_url' => $photo_url,
            'width' => $this->formatNumber($width),
            'height' => $this->formatNumber($height)
        );
    }

    protected function preparePhotoAttachment($photo, $width = 350)
    {
        if (isset($photo['sizes']) && is_array($photo['sizes'])) {
            return $this->preparePhotoAttachmentObjectThumbs($photo, $width);
        } else {
            return $this->preparePhotoAttachmentObjectWithPhotoUrls($photo, $width);
        }
    }

    protected function prepareDocPhotoAttachment($photo, $size_type = 'm')
    {
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
        return $photo;
    }


    protected function prepareWallAttachment($wall)
    {
        return array(
            'type' => 'wall',
            'text' => $wall['text']
        );
    }

    protected function prepareGeo()
    {
        if (empty($this->message['params']['geo'])) {
            return null;
        }
        $geo = $this->message['params']['geo'];

        $location = array();
        if (isset($geo['place']['latitude']) && isset($geo['place']['longitude'])) {
            $location[] = $geo['place']['latitude'];
            $location[] = $geo['place']['longitude'];
            $location = join(',', $location);
        } elseif (isset($geo['coordinates'])) {
            $location = str_replace(' ', ',', $geo['coordinates']);
        } elseif (isset($geo['place'])) {
            if (!empty($geo['place']['country'])) {
                $location[] = $geo['place']['country'];
            }
            if (!empty($geo['place']['city'])) {
                $location[] = $geo['place']['city'];
            }
            $location = join(',', $location);
        }

        $content = _wd('crm_vk', 'Unknown location');

        if ($location) {
            try {
                $content = wa()->getMap()->getHTML($location, array('width' => '470px', 'height' => '270px', 'zoom' => 16));
            } catch (waException $e) {

            }
        }

        return array(
            'html' => $content
        );
    }
}
