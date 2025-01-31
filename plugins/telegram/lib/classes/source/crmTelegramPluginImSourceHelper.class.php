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

    public function workupConversationInList($conversation)
    {
        $conversation = parent::workupConversationInList($conversation);
        $conversation['transport_name'] = $this->source->getParam('username');
        return $conversation;
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
        } elseif (wa()->whichUI('crm') === '1.3') {
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
        return $this->workupConversationInList($conversation);
    }

    public function getUI20ConversationAuxItems($conversation)
    {
        if (!ifset($conversation, 'contacts', $conversation['contact_id'], false)) {
            return [];
        }
        $reply_form_dropdown_items = [];
        
        // Cheat sheet item
        $template = wa()->getAppPath("plugins/telegram/templates/source/message/ConversationCheatSheetDropdownItem.html", 'crm');
        $reply_form_dropdown_items[] = $this->renderTemplate($template);

        // Ask phone item
        $contact = $conversation['contacts'][$conversation['contact_id']];
        if ($this->source->getParam('ask_phone') && empty($contact->get('phone', 'default'))) {
            $do_confirm_phone_request = !(new waContactSettingsModel())->getOne(wa()->getUser()->getId(), 'crm.telegram', 'phone_request_no_more_confirmation');
            $template = wa()->getAppPath("plugins/telegram/templates/source/message/ConversationAskPhoneDropdownItem.html", 'crm');
            $reply_form_dropdown_items[] = $this->renderTemplate($template, [
                'icon' => $this->source->getFontAwesomeBrandIcon(),
                'message_id'    => $conversation['last_message_id'],
                'source_id'     => $this->source->getId(),
                'contact'       => $contact,
                'do_confirm_phone_request' => $do_confirm_phone_request ? 1 : 0,
            ]);
        }
        return [
            'reply_form_dropdown_items' => $reply_form_dropdown_items,
        ];
    }

    public function workupMessagesInConversation($conversation, $messages)
    {
        $messages = self::workupMessagesForDisplaying($messages);
        foreach ($messages as &$message) {
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

        $res = self::workupMessagesForDisplaying([$message]);
        return reset($res);
    }

    protected static function workupMessagesForDisplaying($messages)
    {
        $attachment_ids = [];
        foreach($messages as $message) {
            $attachment_ids = array_merge($attachment_ids, array_keys((array)ifset($message['attachments'])));
        }
        $files_params = (new crmTelegramPluginFileParamsModel)->get($attachment_ids);
        
        return array_map(function($m) use ($files_params) {
            if ($m['direction'] == crmMessageModel::DIRECTION_IN) {
                $m['to_telegram_url'] = $m['to'] ? "https://t.me/".$m['to'] : false; // Bot url
                $m['from_telegram_url'] = ifset($m['params']['username']) ? "https://t.me/".$m['params']['username'] : false; // User url
            } else {
                $m['to_telegram_url'] = ifset($m['params']['username']) ? "https://t.me/".$m['params']['username'] : false; // User url
                $m['from_telegram_url'] = $m['from'] ? "https://t.me/".$m['from'] : false; // Bot url
            }

            foreach (ifset($m['attachments'], []) as $file_id => $file) {
                if (ifset($files_params, $file_id, 'type', null)) {
                    $m[$files_params[$file_id]['type']][$file_id] = $file;
                    unset($m['attachments'][$file_id]);
                }
            }

            if (!empty($m['params']['caption'])) {
                $m['params']['caption_sanitized'] = crmHtmlSanitizer::work($m['params']['caption']);
            }
            $m['body_formatted'] = crmTelegramPluginMessageBodyFormatter::format($m);
            $m['subject_formatted'] = crmTelegramPluginMessageSubjectFormatter::format($m);
    
            return $m;
        }, $messages);
    }

    public function normalazeMessagesExtras($messages)
    {
        $attachment_ids = [];
        $sticker_ids = [];
        foreach($messages as $message) {
            $attachment_ids = array_merge($attachment_ids, array_column(array_values(ifset($message['attachments'], [])), 'id'));
            $sticker_id = ifset($message, 'params', 'sticker_id', null);
            if ($sticker_id) {
                $sticker_ids[] = $sticker_id;
            }
        }
        $files_params = (new crmTelegramPluginFileParamsModel)->get($attachment_ids);
        $stickers = $sticker_files = [];
        if (!empty($sticker_ids)) {
            $stickers = (new crmTelegramPluginStickerModel)->getByField(['id' => $sticker_ids], 'id');
            $file_ids = array_column(array_values($stickers), 'crm_file_id');
            if (!empty($file_ids)) {
                $sticker_files = (new crmFileModel)->getByField(['id' => $file_ids], 'id');
            }
        }

        return array_map(function($m) use ($files_params, $stickers, $sticker_files) {
            foreach (ifset($m['attachments'], []) as $idx => $file) {
                if (ifset($files_params, $file['id'], 'type', null) 
                    && $extra_type = $this->normilizeExtraType($files_params[$file['id']]['type'])
                ) {
                    $m = $this->addExtra($m, $extra_type, $file);
                    unset($m['attachments'][$idx]);
                } elseif (strtolower($file['ext']) === 'mp4') {
                    $m = $this->addExtra($m, 'videos', $file);
                    unset($m['attachments'][$idx]);
                } elseif (strtolower($file['ext']) === 'gif') {
                    $m = $this->addExtra($m, 'images', $file);
                    unset($m['attachments'][$idx]);
                }
            }
            if (!empty($m['attachments'])) {
                $m['attachments'] = array_values($m['attachments']);
            }

            $sticker_file = null;
            $sticker_id = ifset($m, 'params', 'sticker_id', null) 
                and $sticker_file_id = ifset($stickers, $sticker_id, 'crm_file_id', null)
                and $sticker_file = ifset($sticker_files[$sticker_file_id]);
            if ($sticker_file) {
                $m = $this->addExtra($m, 'stickers', $sticker_file);
            }

            $location = ifset($m, 'params', 'location', null);
            if ($location) {
                $m = $this->addExtra($m, 'locations', ['point' => $location]);
            }

            $location = ifset($m, 'params', 'venue_location', null);
            if ($location) {
                $m = $this->addExtra($m, 'locations', [
                    'point' => $location,
                    'title' => ifset($m, 'params', 'venue_title', null),
                    'address' => ifset($m, 'params', 'venue_address', null),
                    'foursquare_id' => ifset($m, 'params', 'venue_foursquare_id', null)
                ]);
            }

            $caption = ifset($m, 'params', 'caption', null);
            if ($caption) {
                $m['caption'] = crmHtmlSanitizer::work($caption);
            }

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

    protected function normilizeExtraType($type)
    {
        switch ($type) {
            case 'photo':
                return 'images';
            case 'video':
            case 'video_note':
                return 'videos';
            case 'audio':
            case 'voice':
                return 'audios';
        }
        return false;
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
