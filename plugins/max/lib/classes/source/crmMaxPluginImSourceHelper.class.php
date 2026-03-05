<?php

/**
 * MAX messenger helper for CRM.
 */
class crmMaxPluginImSourceHelper extends crmSourceHelper
{

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

            $ext_attachments = ifset($m, 'params', 'max_attachments', []);
            foreach($ext_attachments as $att) {
                if (ifset($att['type']) == 'image') {
                    $m = $this->addExtra($m, 'images', [
                        'url' => $att['url'],
                    ]);
                } elseif (ifset($att['type']) == 'video') {
                    $m = $this->addExtra($m, 'videos', [
                        'url' => $att['url'],
                    ]);
                } elseif (ifset($att['type']) == 'audio') {
                    $m = $this->addExtra($m, 'audios', [
                        'url' => $att['url'],
                    ]);
                } elseif (!empty($att['filename'])) {
                    $file_data = [
                        'name' => $att['filename'],
                        'ext' => pathinfo($att['filename'], PATHINFO_EXTENSION),
                        'url' => $att['url'],
                    ];
                    if (isset($att['size'])) {
                        $file_data['size'] = $att['size'];
                    }
                    $m = $this->addAttachment($m, $file_data);
                }
            }

            if ($location = ifset($m, 'params', 'location', null)) {
                $location_extra = ['point' => $location];
                if ($title = ifset($m, 'params', 'location_title', null)) {
                    $location_extra['title'] = $title;
                }
                $m = $this->addExtra($m, 'locations', $location_extra);
            }
            
            $error_code = ifset($m, 'params', 'error_code', null);
            if ($error_code) {
                $m['error_code'] = $error_code;
                switch ($error_code) {
                    case 'unsupported':
                        $m['error_body'] = _wd('crm_max', 'This message type is not currently supported by MAX.');
                        break;
                    case 'not_delivered':
                        // do nothing here (all nedded is already done)
                        break;
                    default:
                        $m['error_body'] = _wd('crm_max', 'Failed to render the message.');
                }
                // $m['error_details'] = nl2br(htmlspecialchars(ifset($m, 'params', 'error_details', '')));
            }
            
            //$m['body_sanitized'] = crmHtmlSanitizer::work($body, ['verification_key' => $verification_key]);
            return $m;
        }, $messages);
    }

    public function getFeatures()
    {
        return [
            'html' => true,
            'attachments' => true,
            'images' => true,
        ];
    }

    public function getUI20ConversationAuxItems($conversation)
    {
        return [
            'reply_form_dropdown_items' => [
                $this->cheatSheetDropdownItem(),
            ],
        ];
    }

    private function cheatSheetDropdownItem()
    {
        $template = wa()->getAppPath("plugins/max/templates/source/message/ConversationCheatSheetDropdownItem.html", 'crm');
        return $this->renderTemplate($template);
    }

    protected function ext2extra($ext)
    {
        switch ($ext) {
            case 'png':
            case 'jpg':
            case 'jpeg':
            case 'webp':
                return 'images';
            case 'mp4':
            case '3gp':
            case 'mov':
            case 'webm':
                return 'videos';
            case 'mp3':
            case 'aac':
            case 'ogg':
            //case 'amr':
                return 'audios';
        }
        return false;
    }

    protected function addAttachment($message, $file)
    {
        if (!isset($message['attachments'])) {
            $message['attachments'] = [];
        }
        $message['attachments'][] = $file;
        return $message;
    }
}
