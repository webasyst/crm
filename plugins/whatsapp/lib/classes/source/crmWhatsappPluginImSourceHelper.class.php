<?php

class crmWhatsappPluginImSourceHelper extends crmSourceHelper
{

    public function workupMessageInList($message)
    {
        $message['icon_url'] = $this->source->getIcon();
        $message['transport_name'] = $this->source->getName();
        $message['body_formatted'] = $this->formatBody($message['body']);
        return $message;
    }

    public function getFeatures()
    {
        return [
            'html' => false,
            'attachments' => true,
            'images' => true,
        ];
    }

    public function workupMessagesInConversation($conversation, $messages)
    {
        return array_map(function ($m) {
            $m['body_formatted'] = $this->formatBody($m['body']);
            return $m;
        }, $messages);
    }

    public function normalazeMessagesExtras($messages)
    {
        $verification_key = $this->source->getParam('verification_key');

        return array_map(function($m) use ($verification_key) {
            foreach (ifset($m['attachments'], []) as $idx => $file) {
                if ($extra_type = $this->ext2extra($file['ext'])) {
                    $m = $this->addExtra($m, $extra_type, $file);
                    unset($m['attachments'][$idx]);
                }
            }
            if (!empty($m['attachments'])) {
                $m['attachments'] = array_values($m['attachments']);
            }
            if ($location = ifset($m, 'params', 'location', null)) {
                $location_extra = ['point' => $location];
                if ($title = ifset($m, 'params', 'location_title', null)) {
                    $location_extra['title'] = $title;
                }
                $m = $this->addExtra($m, 'locations', $location_extra);
            }
            $caption = ifset($m, 'params', 'caption', null);
            if ($caption) {
                $m['caption'] = crmHtmlSanitizer::work($caption);
            }
            $header = ifset($m, 'params', 'message_header', null);
            if ($header) {
                $m['header'] = crmHtmlSanitizer::work($header);
            }
            $footer = ifset($m, 'params', 'message_footer', null);
            if ($footer) {
                $m['footer'] = crmHtmlSanitizer::work($footer);
            }
            $error_code = ifset($m, 'params', 'error_code', null);
            if ($error_code) {
                $m['error_code'] = $error_code;
                switch ($error_code) {
                    case 'unsupported':
                        $m['error_body'] = _wd('crm_whatsapp', 'This message type is not currently supported by WhatsApp Business.');
                        break;
                    case 'not_delivered':
                        // do nothing here (all nedded is already done)
                        break;
                    default:
                        $m['error_body'] = _wd('crm_whatsapp', 'Failed to render the message.');
                }
                // $m['error_details'] = nl2br(htmlspecialchars(ifset($m, 'params', 'error_details', '')));
            }
            // convert plain-text message body to html
            $body = $this->formatBody($m['body']);
            $m['body_sanitized'] = crmHtmlSanitizer::work($body, ['verification_key' => $verification_key]);
            return $m;
        }, $messages);
    }

    public function getUI20ConversationAuxItems($conversation)
    {
        return [
            'reply_form_dropdown_items' => [
                $this->templatesDropdownItem($conversation['id'], $conversation['contact_id']),
                $this->cheatSheetDropdownItem(),
            ],
        ];
    }

    private function cheatSheetDropdownItem()
    {
        $template = wa()->getAppPath("plugins/whatsapp/templates/source/message/ConversationCheatSheetDropdownItem.html", 'crm');
        return $this->renderTemplate($template);
    }

    public function templatesDropdownItem($conversation_id, $contact_id)
    {
        $template = wa()->getAppPath("plugins/whatsapp/templates/source/message/ConversationTemplateDropdownItem.html", 'crm');
        return $this->renderTemplate($template, [
            'icon' => $this->source->getFontAwesomeBrandIcon(),
            'conversation_id' => $conversation_id,
            'source_id'       => $this->source->getId(),
            'contact_id'      => $contact_id,
            'source_name'     => $this->source->getName(),
        ]);
    }

    protected function formatBody($content)
    {
        $content = nl2br(htmlspecialchars($content));
        $content = preg_replace('/```((?:(?!```).)+)```/s', '<pre>\1</pre>', $content);
        $content = preg_replace('/\\*((?:(?!\\*).)+)\\*/s', '<b>\1</b>', $content);
        $content = preg_replace('/~((?:(?!~).)+)~/s', '<s>\1</s>', $content);
        $content = preg_replace('/_((?:(?!_).)+)_/s', '<i>\1</i>', $content);
        return $content;
    }

    protected function ext2extra($ext)
    {
        switch ($ext) {
            case 'png':
            case 'jpg':
            case 'jpeg':
                return 'images';
            case 'webp':
                return 'stickers';
            case 'mp4':
            case '3gp':
                return 'videos';
            case 'mp3':
            case 'aac':
            case 'ogg':
            case 'amr':
                return 'audios';
        }
        return false;
    }
}
