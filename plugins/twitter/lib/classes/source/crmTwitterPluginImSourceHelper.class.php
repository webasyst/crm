<?php

class crmTwitterPluginImSourceHelper extends crmSourceHelper
{
    public function workupMessageInList($message)
    {
        $message['icon_url'] = $this->source->getIcon();
        $message['transport_name'] = $this->source->getName();
        return $message;
    }

    public function workupConversationInList($conversation)
    {
        $conversation = parent::workupConversationInList($conversation);
        $conversation['transport_name'] = '@'.$this->source->getParam('username');
        return $conversation;
    }

    public function workupConversation($conversation)
    {
        $mm = new crmMessageModel();
        $last_message = $mm->getMessage($conversation['last_message_id']);
        if (!$last_message) {
            $conversation['reply_form_html'] = '<div class="c-reply-form-error">'._wd('crm_telegram',
                    'The bot can not start a conversation with the user.')."</div>";
        } else {
            $assign = array(
                'message'   => $last_message,
                'source_id' => $this->source->getId(),
                'hash'      => md5(time().wa()->getUser()->getId())
            );
            $template = wa()->getAppPath("plugins/twitter/templates/source/message/ConversationReplyForm.html", 'crm');
            $reply_form_html = $this->renderTemplate($template, $assign);
            $conversation['reply_form_html'] = $reply_form_html;
        }
        return $this->workupConversationInList($conversation);
    }

    public function workupMessagesInConversation($conversation, $messages)
    {
        foreach ($messages as &$message) {
            $message = self::workupMessageForDisplaying($message);
            // format From block in conversation
            $template = wa()->getAppPath("plugins/twitter/templates/source/message/ConversationFromFormatted.html", 'crm');
            $assign = array(
                'message'     => $message,
                'plugin_icon' => $this->source->getIcon(),
            );
            $from_formatted = $this->renderTemplate($template, $assign);
            $message['from_formatted'] = $from_formatted;
        }
        return $messages;
    }

    public static function workupMessageForDialog($message)
    {
        return self::workupMessageForDisplaying($message);
    }

    protected static function workupMessageForDisplaying($message)
    {
        $contact_ids = array($message['contact_id'], $message['creator_contact_id'], ifset($message['params']['forward_contact_id']));
        $contact_ids = crmHelper::toIntArray($contact_ids);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        $contact_ids = array_unique($contact_ids);

        $contacts = array();
        if ($contact_ids) {
            $col = new crmContactsCollection('id/'.join(',', $contact_ids));
            $contacts = $col->getContacts('id,name,photo_url_16,im', 0, count($contact_ids));
        }

        $message['contact'] = ifset($contacts[$message['contact_id']]);
        $message['creator_contact'] = ifset($contacts[$message['creator_contact_id']]);

        $body = crmTwitterPluginMessageBodyFormatter::format($message);
        if (ifset($message['params']['message_type']) == 'tweet') {
            $message['subject_formatted'] = _wd('crm_twitter', 'Tweet');
        } elseif (ifset($message['params']['message_type']) == 'direct') {
            $message['subject_formatted'] = _wd('crm_twitter', 'Direct message');
        } else {
            $message['subject_formatted'] = strip_tags($body);
        }
        $message['body_formatted'] = $body;
        return $message;
    }

    public function workupMessagePopupItem($message)
    {
        $message['icon_url'] = $this->source->getIcon();
        $message['transport_name'] = $this->source->getName();
        $message['from_formatted'] = '@'.htmlspecialchars($message['from']);

        if ($message['params']['message_type'] == 'tweet') {
            $message['message_type'] = _wd('crm_twitter', 'Tweet');
        }

        $message['subject_formatted'] = crmTwitterPluginMessageBodyFormatter::format($message);

        return $message;
    }

    public function workupMessageLogItemHeader($message, $log_item)
    {
        $template = wa()->getAppPath("plugins/twitter/templates/source/message/LogInline.html", 'crm');
        $assign = array(
            'message'     => $message,
            'contact'     => new crmContact($message['contact_id']),
            'plugin_icon' => $this->source->getIcon(),
        );
        $inline_html = $this->renderTemplate($template, $assign);
        $log_item['inline_html'] = $inline_html;

        return $log_item;
    }

    public function workupMessageLogItemBody($message)
    {
        $message['body_formatted'] = crmTwitterPluginMessageBodyFormatter::format($message);
        return $message;
    }

    protected function getAppIcon()
    {
        $info = wa()->getAppInfo('crm');
        $sizes = array_keys($info['icon']);
        $size = min($sizes);
        return $info['icon'][$size];
    }
}