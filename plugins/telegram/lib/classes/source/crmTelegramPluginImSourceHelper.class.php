<?php

class crmTelegramPluginImSourceHelper extends crmSourceHelper
{
    /**
     * @var crmTelegramPluginApi
     */
    protected $api;

    public function __construct(crmSource $source, array $options = array())
    {
        parent::__construct($source, $options);
        $this->api = new crmTelegramPluginApi($this->source->getParam('access_token'));
    }

    public function workupMessageInList($message)
    {
        $message['icon_url'] = $this->source->getIcon();
        $message['transport_name'] = $this->source->getName();
        $message['body_formatted'] = htmlspecialchars(ifset($message['body']));
        $message['subject_formatted'] = crmTelegramPluginMessageSubjectFormatter::format($message);
        return $message;
    }

    public function workupMessageLogItemHeader($message, $log_item)
    {
        $template = wa()->getAppPath("plugins/telegram/templates/source/message/LogInline.html", 'crm');
        $assign = array(
            'message'     => $message,
            'contact'     => new crmContact($message['contact_id']),
            'app_icon'    => $this->getAppIcon(),
            'plugin_icon' => $this->source->getIcon(),
            'bot_name'    => $this->source->getParam('username'),
            'source_link' => wa()->getAppUrl() . 'settings/sources/' . $message['source_id'],
        );
        $inline_html = $this->renderTemplate($template, $assign);
        $log_item['inline_html'] = $inline_html;

        return $log_item;
    }

    public function workupMessageLogItemBody($message)
    {
        $attachment_ids = array_keys((array)ifset($message['attachments']));
        $tfpm = new crmTelegramPluginFileParamsModel();
        $params = $tfpm->get($attachment_ids);
        foreach ($params as $file_id => $param) {
            if (isset($param['type'])) {
                $message[$param['type']][$file_id] = $message['attachments'][$file_id];
                unset($message['attachments'][$file_id]);
            }
        }

        $message['body_formatted'] = crmTelegramPluginMessageBodyFormatter::format($message);
        return $message;
    }

    public function workupMessagePopupItem($message)
    {
        $message['icon_url'] = $this->source->getIcon();
        $message['transport_name'] = $this->source->getName();
        if (isset($message['params']['username'])) {
            $message['from_formatted'] = '@'.htmlspecialchars($message['params']['username']);
        } elseif (isset($message['contact']['name'])) {
            $message['from_formatted'] = htmlspecialchars(ifset($message['contact']['name']));
        }

        $attachment_ids = array_keys((array)ifset($message['attachments']));
        $tfpm = new crmTelegramPluginFileParamsModel();
        $params = $tfpm->get($attachment_ids);
        foreach ($params as $file_id => $param) {
            if (isset($param['type'])) {
                $message[$param['type']][$file_id] = $message['attachments'][$file_id];
                unset($message['attachments'][$file_id]);
            }
        }

        $message['subject_formatted'] = crmTelegramPluginMessagePopupFormatter::format($message);

        return $message;
    }

    public function workupConversation($conversation)
    {
        $mm = new crmMessageModel();
        $last_message = $mm->getMessage($conversation['last_message_id']);
        if (!$last_message) {
            $conversation['reply_form_html'] = '<div class="c-reply-form-error">'._wd('crm_telegram',
                    'The bot can not start a conversation with the user')."</div>";
        } elseif ($this->source->isDisabled()) {
            $conversation['reply_form_html'] = '<div class="c-reply-form-error">'._wd('crm_telegram',
                    'Deal source disabled')."</div>";
        } else {
            $assign = array(
                'message'         => $last_message,
                'source_id'       => $this->source->getId(),
                'hash'            => md5(time().wa()->getUser()->getId()),
                'send_action_url' => wa()->getAppUrl().'?module=message&action=sendReply',
            );
            $template = wa()->getAppPath("plugins/telegram/templates/source/message/ConversationReplyForm.html", 'crm');
            $reply_form_html = $this->renderTemplate($template, $assign);
            $conversation['reply_form_html'] = $reply_form_html;
        }
        $conversation['transport_name'] = $this->source->getParam('username');
        $conversation['icon_url'] = $this->source->getIcon();
        return $conversation;
    }

    public function workupMessagesInConversation($conversation, $messages)
    {
        foreach ($messages as &$message) {
            $message = self::workupMessageForDisplaying($message);
            // format From block in conversation
            $template = wa()->getAppPath("plugins/telegram/templates/source/message/ConversationFromFormatted.html", 'crm');
            $assign = array(
                'message'     => $message,
                'plugin_icon' => $this->source->getIcon(),
            );
            $from_formatted = $this->renderTemplate($template, $assign);
            $message['from_formatted'] = $from_formatted;
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
        $message['forward_contact'] = ifset($contacts[$message['params']['forward_contact_id']]);

        if (!empty($message['deal_id'])) {
            $dm = new crmDealModel();
            $deal = $dm->getById($message['deal_id']);
            $funnel = self::getFunnel($deal);
            $message['deal'] = $deal;
            $message['funnel'] = $funnel;
        }

        if ($message['direction'] == crmMessageModel::DIRECTION_IN) {
            $message['to_telegram_url'] = $message['to'] ? "https://t.me/".$message['to'] : false; // Bot url
            $message['from_telegram_url'] = ifset($message['params']['username']) ? "https://t.me/".$message['params']['username'] : false; // User url
        } else {
            $message['to_telegram_url'] = ifset($message['params']['username']) ? "https://t.me/".$message['params']['username'] : false; // User url
            $message['from_telegram_url'] = $message['from'] ? "https://t.me/".$message['from'] : false; // Bot url
        }

        $attachment_ids = array_keys((array)ifset($message['attachments']));
        $tfpm = new crmTelegramPluginFileParamsModel();
        $params = $tfpm->get($attachment_ids);
        foreach ($params as $file_id => $param) {
            if (isset($param['type'])) {
                $message[$param['type']][$file_id] = $message['attachments'][$file_id];
                unset($message['attachments'][$file_id]);
            }
        }

        if (!empty($message['params']['caption'])) {
            $message['params']['caption_sanitized'] = crmHtmlSanitizer::work($message['params']['caption']);
        }

        $message['body_formatted'] = crmTelegramPluginMessageBodyFormatter::format($message);
        $message['subject_formatted'] = crmTelegramPluginMessageSubjectFormatter::format($message);

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
