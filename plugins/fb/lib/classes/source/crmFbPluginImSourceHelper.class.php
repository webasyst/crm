<?php

class crmFbPluginImSourceHelper extends crmSourceHelper
{

    public function workupMessageLogItemHeader($message, $log_item)
    {
        $template = wa()->getAppPath("plugins/fb/templates/source/message/formatted/LogInline.html", 'crm');
        $assign = array(
            'message'     => $message,
            'contact'     => new crmContact($message['contact_id']),
            'app_icon'    => $this->getAppIcon(),
            'plugin_icon' => $this->source->getIcon(),
            'source_url'  => wa()->getAppUrl() . 'settings/sources/' . $message['source_id'],
        );
        $inline_html = $this->renderTemplate($template, $assign);
        $log_item['inline_html'] = $inline_html;

        return $log_item;
    }

    public function workupMessageLogItemBody($message)
    {
        $message['body_formatted'] = crmFbPluginMessageBodyFormatter::format($message);
        return $message;
    }

    public function workupMessagePopupItem($message)
    {
        $message['icon_url'] = $this->source->getIcon();
        $message['transport_name'] = $this->source->getName();
        if (isset($message['contact']['name'])) {
            $message['from_formatted'] = htmlspecialchars(ifset($message['contact']['name']));
        }

        $message['subject_formatted'] = crmFbPluginMessageSubjectFormatter::format($message);

        return $message;
    }

    public function workupMessageInList($message)
    {
        $message['icon_url'] = $this->source->getIcon();
        $message['transport_name'] = $this->source->getName();
        $message['subject_formatted'] = crmFbPluginMessageSubjectFormatter::format($message);
        return $message;
    }

    public function workupConversation($conversation)
    {
        $mm = new crmMessageModel();
        $last_message = $mm->getMessage($conversation['last_message_id']);
        if (!$last_message) {
            $conversation['reply_form_html'] = '<div class="c-reply-form-error">'._wd('crm_fb', 'You can not start a conversation with the user')."</div>";
        } elseif ($this->source->isDisabled()) {
            $conversation['reply_form_html'] = '<div class="c-reply-form-error">'._wd('crm_fb', 'Deal source disabled')."</div>";
        } else {
            $assign = array(
                'message'             => $last_message,
                'source_id'           => $this->source->getId(),
                'hash'                => md5(time().wa()->getUser()->getId()),
                'send_action_url'     => wa()->getAppUrl().'?module=message&action=sendReply',
                'send_icon_url'       => wa()->getAppStaticUrl('crm', true)."plugins/fb/img/send.png",
                'attachment_icon_url' => wa()->getAppStaticUrl('crm', true)."plugins/fb/img/attachment.png",
            );
            $template = wa()->getAppPath("plugins/fb/templates/source/message/ConversationReplyForm.html", 'crm');
            $reply_form_html = $this->renderTemplate($template, $assign);
            $conversation['reply_form_html'] = $reply_form_html;
        }
        $conversation['transport_name'] = $this->source->getName();
        $conversation['icon_url'] = $this->source->getIcon();
        return $conversation;
    }

    public function workupMessagesInConversation($conversation, $messages)
    {
        foreach ($messages as &$message) {
            $message['body_formatted'] = crmFbPluginMessageBodyFormatter::format($message);
            $message['from_formatted'] = '';
        }
        unset($message);
        return $messages;
    }

    public static function workupMessageForDialog($message)
    {
        return self::workupMessageForDisplaying($message);
    }

    protected static function workupMessageForDisplaying($message)
    {
        $contact_ids = array($message['contact_id'], $message['creator_contact_id']);
        $contact_ids = crmHelper::toIntArray($contact_ids);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        $contact_ids = array_unique($contact_ids);

        if (!empty($message['deal_id'])) {
            $dm = new crmDealModel();
            $deal = $dm->getById($message['deal_id']);
            $funnel = self::getFunnel($deal);
            $message['deal'] = $deal;
            $message['funnel'] = $funnel;
        }

        $contacts = array();
        if ($contact_ids) {
            $col = new crmContactsCollection('id/'.join(',', $contact_ids));
            $contacts = $col->getContacts('name,photo_url_16', 0, count($contact_ids));
        }

        $message['contact'] = ifset($contacts[$message['contact_id']]);
        $message['creator_contact'] = ifset($contacts[$message['creator_contact_id']]);

        $message['body_formatted'] = crmFbPluginMessageBodyFormatter::format($message);
        $message['subject_formatted'] = crmFbPluginMessageSubjectFormatter::format($message);

        return $message;
    }

    protected static function getFunnel($deal)
    {
        $funnels = array();
        if (isset($deal['funnel_id'])) {
            $fm = new crmFunnelModel();
            $fsm = new crmFunnelStageModel();
            if ($deal['funnel_id'] && empty($funnels[$deal['funnel_id']])) {
                $funnel = $fm->getById($deal['funnel_id']);
                $funnel['stages'] = $fsm->getStagesByFunnel($funnel);
                $funnels[$deal['funnel_id']] = $funnel;
            }
        }
        return $funnels;
    }

    protected function getAppIcon()
    {
        $info = wa()->getAppInfo('crm');
        $sizes = array_keys($info['icon']);
        $size = min($sizes);
        return $info['icon'][$size];
    }
}