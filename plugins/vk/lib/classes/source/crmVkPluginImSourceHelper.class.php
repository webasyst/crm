<?php

class crmVkPluginImSourceHelper extends crmSourceHelper
{
    /**
     * @var crmVkPluginImSource
     */
    protected $source;

    public function workupMessageInList($message)
    {
        $message['icon_url'] = $this->source->getIcon();
        $message['transport_name'] = $this->source->getName();
        $message['body_formatted'] = nl2br(htmlspecialchars($message['body']));
        $message['subject_formatted'] = crmVkPluginMessageSubjectFormatter::format($message);
        return $message;
    }

    /**
     * @param array $message
     * @param array $log_item
     * @return mixed
     */
    public function workupMessageLogItemHeader($message, $log_item)
    {
        $template = wa()->getAppPath("plugins/vk/templates/source/message/MessageLogItemHeader.html", 'crm');

        $messages = array($message['id'] => $message);
        $messages = self::workupMessagesForDisplaying($messages, array(
            'ignore_subject_formatting' => true,
            'ignore_body_formatting' => true
        ));
        $message = $messages[$message['id']];

        $assign = array(
            'message'     => $message,
            'app_icon_url'    => $this->getAppIcon(),
            'source_icon_url' => $this->source->getIcon(),
            'source_link' => wa()->getAppUrl() . 'settings/sources/' . $message['source_id'],
        );
        $inline_html = $this->renderTemplate($template, $assign);
        $log_item['inline_html'] = $inline_html;

        return $log_item;
    }

    public function workupConversation($conversation)
    {
        $mm = new crmMessageModel();
        $last_message = $mm->getMessage($conversation['last_message_id']);
        if (!$last_message) {
            $conversation['reply_form_html'] = '<div class="c-reply-form-error">'._wd('crm_vk', 'Empty conversation')."</div>";
        } elseif ($this->source->isDisabled()) {
            $conversation['reply_form_html'] = '<div class="c-reply-form-error">'._wd('crm_telegram', 'Source is disabled')."</div>";
        } else {
            $assign = array(
                'message' => $last_message,
                'source_id' => $this->source->getId(),
                'hash' => md5(time().wa()->getUser()->getId()),
                'send_action_url' => wa()->getAppUrl().'?module=message&action=sendReply'
            );
            $template = wa()->getAppPath("plugins/vk/templates/source/message/ConversationReplyForm.html", 'crm');
            $reply_form_html = $this->renderTemplate($template, $assign);
            $conversation['reply_form_html'] = $reply_form_html;
        }

        return $this->workupConversationInList($conversation);
    }

    public function workupMessagesInConversation($conversation, $messages)
    {
        self::markMessagesAsRead($messages, array(
            'access_token' => $this->source->getAccessToken()
        ));
        return self::workupMessagesForDisplaying($messages);
    }

    public function workupMessagePopupItem($message)
    {
        $message['icon_url'] = $this->source->getIcon();
        $message['transport_name'] = $this->source->getName();

        $message = self::workupMessageForDialog($message, array(
            'ignore_body_formatting' => true,
            'ignore_subject_formatting' => true
        ));

        $message['subject_formatted'] = crmVkPluginMessagePopupBodyFormatter::format($message);

        $template = wa()->getAppPath("plugins/vk/templates/source/message/InMessagePopupFromBlock.html");
        $message['from_formatted'] = $this->renderTemplate($template, array(
            'message' => $message
        ));

        return $message;
    }

    protected static function workupMessagesForDisplaying($messages, $options = array())
    {
        $chat_ids = array();
        $contact_ids = array();
        foreach ($messages as $message) {
            $chat_ids[] = (int)ifset($message['params']['chat_id']);
            $contact_ids[] = $message['contact_id'];
            $contact_ids[] = $message['creator_contact_id'];
        }

        $chats = array();
        $chat_ids = array_unique(crmHelper::dropNotPositive($chat_ids));
        foreach ($chat_ids as $chat_id) {
            $chat = crmVkPluginChat::factory($chat_id);
            $contact_ids[] = $chat->getPrincipalParticipant()->getContactId();
            $contact_ids[] = $chat->getParticipant()->getContactId();
            $chats[$chat_id] = $chat;
        }

        $contacts = array();
        $contact_ids = array_unique(crmHelper::dropNotPositive($contact_ids));
        if ($contact_ids) {
            $col = new crmContactsCollection('id/' . join(',', $contact_ids));
            $contacts = $col->getContacts('id,name,photo_url_16', 0, count($contact_ids));
        }

        foreach ($messages as &$message) {

            if (!ifset($options['ignore_subject_formatting'])) {
                $message['subject_formatted'] = crmVkPluginMessageSubjectFormatter::format($message);
            }

            if (!ifset($options['ignore_body_formatting'])) {
                $message['body_formatted'] = crmVkPluginMessageBodyFormatter::format($message);
            }

            if (ifset($message['params']['chat_id'])) {
                $chat = $chats[$message['params']['chat_id']];
                $message['principal_participant'] = $chat->getPrincipalParticipant()->getInfo();
                $message['principal_participant']['contact'] = ifset($contacts[$message['principal_participant']['contact_id']]);
                $message['participant'] = $chat->getParticipant()->getInfo();
                $message['participant']['contact'] = ifset($contacts[$message['participant']['contact_id']]);
            }

            $message['contact'] = ifset($contacts[$message['contact_id']]);
            $message['creator_contact'] = ifset($contacts[$message['creator_contact_id']]);
        }
        unset($message);

        return $messages;
    }

    public function normalazeMessagesExtras($messages)
    {
        $verification_key = $this->source->getParam('verification_key');
        return array_map(function($m) use ($verification_key) {
            $m = $this->normalazeVkAttachments($m, ifset($m, 'params', 'attachments', []));
            $m = $this->normalazeVkGeo($m, ifset($m, 'params', 'geo', null));
            $m = $this->normalazeNestedVkMessagesExtras($m, $m);

            // convert plain-text message body to html
            $body = $this->formatVkMessageText(ifset($m['body'], ''));
            $nested_body = $this->formatNestedVkMessagesBody($m);
            if ($nested_body) {
                $body .= ($body ? '<br>' : '') . $nested_body;
            }
            $m['body_sanitized'] = crmHtmlSanitizer::work($body, ['verification_key' => $verification_key]);
            return $m;
        }, $messages);

    }

    private function normalazeVkAttachments($message, $attachments)
    {
        foreach ((array)$attachments as $att) {
            if (ifset($att['type']) == 'doc' && isset($att['doc'])) {
                if (strtolower(ifset($att, 'doc', 'ext', '')) === 'gif') {
                    $message = $this->addExtra($message, 'images', [
                        'name' => ifset($att, 'doc', 'title', ''),
                        'ext' => ifset($att, 'doc', 'ext', ''),
                        'url' => ifset($att, 'doc', 'url', ''),
                    ]);
                } else {
                    $message = $this->addAttachement($message, $att['doc']);
                }
                continue;
            }
            if (ifset($att['type']) == 'photo' && isset($att['photo'])) {
                foreach ($this->extractVkPhotoFiles($att['photo']) as $file) {
                    $message = $this->addExtra($message, 'images', $file);
                }
                continue;
            }
            if (ifset($att['type']) == 'audio_message' && isset($att['audio_message'])) {
                $audio = [];
                if (isset($att['audio_message']['link_ogg'])) {
                    $audio['name'] = $att['audio_message']['id'] . 'ogg';
                    $audio['ext'] = 'ogg';
                    $audio['url'] = $att['audio_message']['link_ogg'];
                } elseif (isset($att['audio_message']['link_mp3'])) {
                    $audio['name'] = $att['audio_message']['id'] . 'mp3';
                    $audio['ext'] = 'mp3';
                    $audio['url'] = $att['audio_message']['link_mp3'];
                }
                if (empty($audio)) {
                    continue;
                }

                $message = $this->addExtra($message, 'audios', $audio);
                continue;
            }
            if (ifset($att['type']) == 'video' && isset($att['video'])) {
                $message['caption'] = '<span class="gray">' . _wd('crm_vk', '(Video is not supported)') . '</span>';
                if (ifset($att['video']['title'])) {
                    $message['caption'] .= ' <span class="small">' . crmHtmlSanitizer::work($att['video']['title']) . '</span>';
                }
                // TODO: add video normalization
                continue;
            }

            if (ifset($att['type']) == 'sticker' && isset($att['sticker'])) {
                if (isset($att['sticker']['images']) && is_array($att['sticker']['images'])) {
                    $file = $this->extractBiggestThumbFile($att['sticker']['images'], 'png');
                    if ($file) {
                        $message = $this->addExtra($message, 'stickers', $file);
                    }
                }
                continue;
            }
        }

        return $message;
    }

    private function normalazeVkGeo($message, $geo)
    {
        if (empty($geo)) {
            return $message;
        }

        $location = [];
        if (isset($geo['coordinates']) && isset($geo['coordinates']['latitude']) && isset($geo['coordinates']['longitude'])) {
            $coordinates = [];
            $coordinates[] = $geo['coordinates']['latitude'];
            $coordinates[] = $geo['coordinates']['longitude'];
            $location['point'] = join(',', $coordinates);
        }
        if (isset($geo['place']) && isset($geo['place']['title'])) {
            $location['title'] = $geo['place']['title'];
            $location['address'] = $geo['place']['title'];
        }
        if (!empty($location)) {
            $message = $this->addExtra($message, 'locations', $location);
        }

        return $message;
    }

    private function normalazeNestedVkMessagesExtras($message, $container)
    {
        foreach ($this->getForwardedVkMessages($container) as $nested_message) {
            $message = $this->normalazeVkAttachments($message, ifset($nested_message, 'attachments', []));
            $message = $this->normalazeVkGeo($message, ifset($nested_message, 'geo', null));
            $message = $this->normalazeNestedVkMessagesExtras($message, $nested_message);
        }

        return $message;
    }

    private function formatNestedVkMessagesBody($container)
    {
        $html = '';
        foreach ($this->getNestedVkMessages($container) as $nested_message) {
            $html .= $this->formatVkMessageBlockquote($nested_message);
        }

        return $html;
    }

    private function formatVkMessageBlockquote($message)
    {
        $body = $this->formatVkMessageText($this->getVkMessageText($message));
        $nested_body = $this->formatNestedVkMessagesBody($message);
        if ($nested_body) {
            $body .= ($body ? '<br>' : '') . $nested_body;
        }
        if (!$body) {
            return '';
        }

        return '<blockquote>' . $body . '</blockquote>';
    }

    private function formatVkMessageText($text)
    {
        return (new crmVkPluginBodyHtmlFormatter)->execute((string)$text);
    }

    private function getVkMessageText($message)
    {
        $text = ifset($message, 'text', null);
        return $text === null ? ifset($message, 'body', '') : $text;
    }

    private function getNestedVkMessages($message)
    {
        $params = ifset($message, 'params', null);
        if (is_array($params)) {
            $message = $params;
        }

        $nested_messages = [];
        $reply_message = ifset($message, 'reply_message', null);
        if (is_array($reply_message) && !empty($reply_message)) {
            $nested_messages[] = $reply_message;
        }

        foreach ((array)ifset($message, 'fwd_messages', []) as $fwd_message) {
            if (is_array($fwd_message) && !empty($fwd_message)) {
                $nested_messages[] = $fwd_message;
            }
        }

        return $nested_messages;
    }

    private function getForwardedVkMessages($message)
    {
        $params = ifset($message, 'params', null);
        if (is_array($params)) {
            $message = $params;
        }

        $fwd_messages = [];
        foreach ((array)ifset($message, 'fwd_messages', []) as $fwd_message) {
            if (is_array($fwd_message) && !empty($fwd_message)) {
                $fwd_messages[] = $fwd_message;
            }
        }

        return $fwd_messages;
    }

    private function extractVkPhotoFiles($photo)
    {
        if (!is_array($photo)) {
            return [];
        }

        if (isset($photo['sizes']) && is_array($photo['sizes'])) {
            $file = $this->extractBiggestThumbFile($photo['sizes']);
            return $file ? [$file] : [];
        }

        $thumbs = [];
        foreach ($photo as $key => $url) {
            if (substr($key, 0, 6) !== 'photo_' || empty($url)) {
                continue;
            }
            $thumbs[] = [
                'width' => substr($key, 6),
                'url' => $url,
            ];
        }

        $file = $this->extractBiggestThumbFile($thumbs);
        return $file ? [$file] : [];
    }

    private function extractBiggestThumbFile($thumbs, $default_ext = null)
    {
        if (!is_array($thumbs) || empty($thumbs)) {
            return null;
        }

        array_multisort(array_column($thumbs, 'width'), SORT_DESC, SORT_NUMERIC, $thumbs);
        $thumb = reset($thumbs);
        $url = ifset($thumb['url']);
        if (empty($url)) {
            return null;
        }

        $_parts = explode('?', $url);
        $_parts = reset($_parts);
        $_parts = explode('/', $_parts);
        $name = end($_parts);
        $_parts = explode('.', $name);
        $ext = end($_parts);
        if ($default_ext && $ext == $name) {
            // Some VK sticker URLs do not contain a file extension.
            $ext = $default_ext;
            $name .= '.' . $default_ext;
        }

        return [
            'name' => $name,
            'ext' => $ext,
            'url' => $url,
        ];
    }

    public function getFeatures()
    {
        return [
            'html' => false,
            'attachments' => true,
            'images' => true,
        ];
    }

    private function addAttachement($message, $file)
    {
        if (!isset($message['attachments'])) {
            $message['attachments'] = [];
        }
        $message['attachments'][] = [
            'name' => $file['title'],
            'ext' => $file['ext'],
            'size' => $file['size'],
            'url' => $file['url'],
        ];
        return $message;
    }

    /**
     * Mark as read for list list of messages
     * @param array $messages
     * @param array $options
     */
    public static function markMessagesAsRead($messages, $options = array())
    {
        if (!isset($options['access_token'])) {
            return;
        }

        $unread_vk_message_ids = array();
        foreach ($messages as $message) {
            if (isset($message['params']) && is_array($message['params']) && isset($message['params']['id'])) {
                if (isset($message['params']['unread']) && $message['params']['unread']) {
                    $unread_vk_message_ids[$message['id']] = $message['params']['id'];
                }
            }
        }

        if ($unread_vk_message_ids) {
            $api = new crmVkPluginApi($options['access_token']);

            if ($api->markAsRead(array('message_ids' => $unread_vk_message_ids))) {

                // not cool, not beautiful, side-effect, fix it latter
                // update DB

                $mpm = new crmMessageParamsModel();
                $mpm->deleteByField(array(
                    'message_id' => array_keys($unread_vk_message_ids),
                    'name' => 'unread'
                ));
            }
        }
    }

    /**
     * Mark as read for signle message
     * @param $message
     * @param array $options
     */
    public static function markMessageAsRead($message, $options = array())
    {
        self::markMessagesAsRead(array($message['id'] => $message), $options);
    }

    public static function workupMessageForDialog($message, $options = array())
    {
        $messages = array($message['id'] => $message);
        $messages = self::workupMessagesForDisplaying($messages, $options);
        return $messages[$message['id']];
    }

    protected function getAppIcon()
    {
        $info = wa()->getAppInfo('crm');
        $sizes = array_keys($info['icon']);
        $size = min($sizes);
        return $info['icon'][$size];
    }
}
