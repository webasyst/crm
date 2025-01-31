<?php

class crmMessageConversationIdAction extends crmBackendViewAction //crmContactIdAction
{
    const MESSAGE_PER_PAGE = 10;

    /** @var array */
    protected $conversation;

    protected $source;

    public function execute($contact_id = null)
    {
        // before exceptions
        wa('crm')->getConfig()->setLastVisitedUrl('message/');

        $old_message_id = waRequest::param('old_message_id', waRequest::get('old_message_id', null, waRequest::TYPE_INT), waRequest::TYPE_INT);
        $new_message_id = waRequest::param('new_message_id', waRequest::get('new_message_id', null, waRequest::TYPE_INT), waRequest::TYPE_INT);
        $conversation_id = waRequest::param('id', waRequest::get('id', null, waRequest::TYPE_INT), waRequest::TYPE_INT);
        $iframe = waRequest::request('iframe', 0, waRequest::TYPE_INT);
        $short_link = waRequest::param('short_link', null, waRequest::TYPE_INT);

        $is_ui13 = (wa()->whichUI('crm') === '1.3');

        $conversation = $this->getConversation($conversation_id);
        if (!$conversation_id || !$conversation) {
            $this->notFound(_w('Conversation not found'));
        }
        if ($conversation['source_id']) {
            $this->source = crmSource::factory($conversation['source_id']);
        }

        $mm = new crmMessageModel();
        $cs = new crmSourceModel();

        // Get Deal
        if ($conversation['deal_id']) {
            $deal = $this->getDealModel()->getById($conversation['deal_id']);
        } else {
            $deal = null;
        }

        if ($is_ui13) {
            $messages = $mm->select('*')
                ->where('conversation_id = ?', (int) $conversation_id)
                ->order('create_datetime')
                ->fetchAll('id');
        } else {
            $query = $mm->select('*')->where('conversation_id = ?', (int) $conversation_id);
            if ($old_message_id > 0) {
                $query->where('id < ?', $old_message_id);
            }
            if ($new_message_id > 0) {
                $query->where('id > ?', $new_message_id);
            }
            $messages = $query->order('create_datetime DESC')->limit(self::MESSAGE_PER_PAGE)->fetchAll('id');
            ksort($messages);
        }
        $contact_ids = [];
        $message_ids = [];
        $last_id = null;

        // collect source IDs for IN EMAIL messages
        $source_ids = [];
        foreach ($messages as $m) {
            if ($m['source_id'] > 0 && $m['transport'] === crmMessageModel::TRANSPORT_EMAIL && $m['direction'] === crmMessageModel::DIRECTION_IN) {
                $source_ids[] = $m['source_id'];
            }
        }
        $source_emails = $this->getSourceEmailAddresses($source_ids);

        $messages = $mm->getExtMessages($messages, $this->source);
        foreach ($messages as &$m) {
            $message_ids[$m['id']] = $m['id'];
            if ($m['contact_id']) {
                $contact_ids[$m['contact_id']] = intval($m['contact_id']);
            }
            $contact_ids[$m['creator_contact_id']] = intval($m['creator_contact_id']);

            $m['recipients'] = [];

            // if message is input and source is of EMAIL type then insert structure in [recipients][to] list
            if ($m['transport'] === crmMessageModel::TRANSPORT_EMAIL && $m['direction'] === crmMessageModel::DIRECTION_IN && isset($source_emails[$m['source_id']])) {
                $source_email = $source_emails[$m['source_id']];
                $m['recipients']['to'][$source_email] = array_merge((new crmMessageRecipientsModel())->getEmptyRow(), [
                    'destination' => $source_email,
                    'name' => $source_email,
                    'type' => crmMessageRecipientsModel::TYPE_TO
                ]);
            }

            $last_id = $m['id'] > $last_id ? $m['id'] : $last_id;

        }
        unset($m);

        $recipients = $this->getRecipientsByMessages($message_ids);
        foreach ($recipients as $r) {
            if (wa_is_int($r['contact_id'])) {
                $contact_ids[$r['contact_id']] = intval($r['contact_id']);
            }
        }
        $contact_ids[$conversation['contact_id']] = intval($conversation['contact_id']);
        $contact_ids[$conversation['user_contact_id']] = intval($conversation['user_contact_id']);
        $contact_ids[wa()->getUser()->getId()] = intval(wa()->getUser()->getId());

        $contacts = $this->getContacts($contact_ids);
        $conversation['contacts'] = $contacts;
        $this->conversation = $conversation = $this->workup($conversation);

        // Add userpic for recipients
        if ($is_ui13) {
            foreach ($recipients as $r) {
                if ($conversation['type'] == crmMessageModel::TRANSPORT_EMAIL && is_numeric($r['destination'])) {
                    continue;
                }
                if (isset($contacts[$r['contact_id']])) {
                    $r['photo'] = $contacts[$r['contact_id']]['photo_url_16'];
                } else {
                    $r['photo'] = null;
                }
                if ($r['type'] == 'TO') { // && $r['destination'] == $messages[$r['message_id']]['to']
                    $messages[$r['message_id']]['recipients']['to'][$r['destination']] = $r;
                } elseif ($r['type'] == 'CC') {
                    $messages[$r['message_id']]['recipients']['cc'][$r['destination']] = $r;
                } elseif ($r['type'] == 'BCC') {
                    $messages[$r['message_id']]['recipients']['bcc'][$r['destination']] = $r;
                } elseif ($r['type'] == 'FROM') {
                    $messages[$r['message_id']]['recipients']['from'][$r['destination']] = $r;
                }
            }
        }

        // Prepare a clean deal, in case the user wants to create a new for this message.
        if (!$conversation['deal_id'] && empty($deal)) {
            $clean_data = $this->getCleanDealData();
        }

        // Get Sources
        $active_sources = $cs->select("*")->where("type IN ('".crmSourceModel::TYPE_EMAIL."','".crmSourceModel::TYPE_IM."') AND disabled=0")->fetchAll();

        $this->getMessageReadModel()->setReadConversation($conversation['id']);

        $can_edit_conversation = $this->getCrmRights()->canEditConversation($conversation);

        $conversation['summary_html'] = '';
        if (!empty($conversation['summary'])) {
            $conversation['summary_html'] = $conversation['summary'] = htmlentities($conversation['summary'], ENT_QUOTES, 'UTF-8', false);
            if (mb_strpos($conversation['summary'], '[image]') === 0) {
                $_summary_tail = mb_substr($conversation['summary'], mb_strlen('[image]'));
                $conversation['summary'] = $_summary_tail ?: _w('Image');
                $conversation['summary_html'] = '<span class="icon size-16 custom-ml-0 custom-mr-4"><i class="fas fa-camera"></i></span> '.$conversation['summary'];
            } elseif (mb_strpos($conversation['summary'], '[video]') === 0) {
                $_summary_tail = mb_substr($conversation['summary'], mb_strlen('[video]'));
                $conversation['summary'] = $_summary_tail ?: _w('Video');
                $conversation['summary_html'] = '<span class="icon size-16 custom-ml-0 custom-mr-4"><i class="fab fa-youtube"></i></span> '.$conversation['summary'];
            } elseif (mb_strpos($conversation['summary'], '[audio]') === 0) {
                $_summary_tail = mb_substr($conversation['summary'], mb_strlen('[audio]'));
                $conversation['summary'] = $_summary_tail ?: _w('Audio');
                $conversation['summary_html'] = '<span class="icon size-16 custom-ml-0 custom-mr-4"><i class="fas fa-microphone"></i></span> '.$conversation['summary'];
            } elseif (mb_strpos($conversation['summary'], '[file]') === 0) {
                $_summary_tail = mb_substr($conversation['summary'], mb_strlen('[file]'));
                $conversation['summary'] = $_summary_tail ?: _w('File');
                $conversation['summary_html'] = '<span class="icon size-16 custom-ml-0 custom-mr-4"><i class="far fa-file-alt"></i></span> '.$conversation['summary'];
            } elseif (mb_strpos($conversation['summary'], '[geolocation]') === 0) {
                $_summary_tail = mb_substr($conversation['summary'], mb_strlen('[geolocation]'));
                $conversation['summary'] = $_summary_tail ?: _w('Geolocation');
                $conversation['summary_html'] = '<span class="icon size-16 custom-ml-0 custom-mr-4"><i class="fas fa-map-marker-alt"></i></span> '.$conversation['summary'];
            } elseif (mb_strpos($conversation['summary'], '[sticker]') === 0) {
                $_summary_tail = mb_substr($conversation['summary'], mb_strlen('[sticker]'));
                $conversation['summary'] = $_summary_tail ?: _w('Sticker');
                $conversation['summary_html'] = '<span class="icon size-16 custom-ml-0 custom-mr-4"><i class="fas fa-sticky-note"></i></span> '.$conversation['summary'];
            } elseif ($conversation['summary'] === '[empty]') {
                $conversation['summary'] = _w('Empty message');
                $conversation['summary_html'] = '<span class="icon size-16 custom-ml-0 custom-mr-4"><i class="fas fa-battery-empty"></i></span> '._w('Empty message');
            }
        }

        $messages = $this->workupMessages($conversation, $messages);
        $do_confirm_verification = !(new waContactSettingsModel())->getOne(wa()->getUser()->getId(), 'crm', 'verify_no_more_confirmation');
        $this->view->assign(array(
            'recipients'      => [],
            'participants'    => [],
            'iframe'          => $iframe,
            'messages'        => $messages,
            'conversation'    => $conversation,
            'deal'            => $deal,
            'clean_data'      => ifempty($clean_data),
            'funnel'          => $this->getFunnel($deal),
            'contacts'        => $contacts,
            'active_sources'  => $active_sources,
            'is_admin'        => wa()->getUser()->isAdmin(),
            'crm_app_url'     => wa()->getAppUrl('crm'),
            'last_id'         => $last_id,
            'hash'            => md5(time().wa()->getUser()->getId()),
            'send_action_url' => wa()->getAppUrl().'?module=message&action=sendReply',
            'can_edit_conversation' => $can_edit_conversation,
            'short_link' => $short_link,
            'do_confirm_verification' => $do_confirm_verification ? 1 : 0,
        ));

        if ($conversation['type'] == crmConversationModel::TYPE_EMAIL) {
            $source_is_disabled = true;
            foreach ($active_sources as $active_source) {
                if ($active_source['type'] == crmConversationModel::TYPE_EMAIL) {
                    $source_is_disabled = false;
                    break;
                }
            }
            $this->view->assign(array(
                'subject' => $this->getSubject(),
                'body' => $this->getBody(),
                'source_is_disabled' => $source_is_disabled
            ));
        } else {
            $this->view->assign([
                'source_is_disabled' => !in_array($conversation['source_id'], array_column($active_sources, 'id'))
            ]);
        }

        if (!$is_ui13) {
            /*
            if ($page == 1) {
                parent::execute($conversation['contact_id']);
            }
            */
            $extras = array_column($messages, 'extras');
            if (!empty($extras) && !empty(array_column($extras, 'locations'))) {
                try {
                    $this->view->assign('map', wa()->getMap());
                } catch (waException $ex) {}
            }
        }
    }

    protected function getConversation($conversation_id)
    {
        if ($this->conversation) {
            return $this->conversation;
        }

        if (!$conversation_id) {
            $this->notFound(_w('Conversation not found'));
        }

        $conversation = $this->getConversationModel()->getConversation($conversation_id);
        if (!$conversation) {
            return null;
        }

        if (!$this->getCrmRights()->canViewConversation($conversation)) {
            $this->accessDenied();
        }

        // Get conversation source
        $conversation['source'] = null;
        if ($conversation['source_id']) {
            $conversation['source'] = $this->getSourceModel()->getSource($conversation['source_id']);
        }

        $this->conversation = $conversation;
        return $this->conversation;
    }

    protected function workup($conversation)
    {
        $conversation['icon_url'] = null;
        $conversation['icon'] = null;
        $conversation['icon_fa'] = null;
        $conversation['transport_name'] = _w('Unknown');

        if ($conversation['type'] == crmMessageModel::TRANSPORT_EMAIL) {
            $conversation['icon'] = 'email';
            $conversation['icon_fa'] = 'envelope';
            $conversation['transport_name'] = 'Email';
        } elseif ($conversation['type'] == crmMessageModel::TRANSPORT_SMS) {
            $conversation['icon'] = 'mobile';
            $conversation['icon_fa'] = 'mobile';
            $conversation['transport_name'] = 'SMS';
        }
        if ($conversation['source_id']) {
            $source_helper = crmSourceHelper::factory($this->source);
            $res = $source_helper->workupConversation($conversation);
            $conversation = $res ? $res : $conversation;
            $conversation['features'] = $source_helper->getFeatures();
            if (wa()->whichUI('crm') === '2.0') {
                $aux_items = [];
                $contact = ifset($conversation['contacts'][$conversation['contact_id']]);
                if (!empty($contact) && $this->source->getParam('ask_verify') && !$contact['has_password']) {
                    $aux_items = [
                        'reply_form_dropdown_items' => [
                            '<a href="javascript:void(0);" class="inline-link js-request-auth"><span class="icon"><i class="fas fa-user-check"></i></span><span class="nowrap">' . _w('Request authorization').'</span></a>'
                        ]
                    ];
                }
                $source_aux = ['source' => $source_helper->getUI20ConversationAuxItems($conversation)];
                $plugins_aux = wa('crm')->event('conversation_view', $conversation, [ 'reply_form_dropdown_items' ]);
                $plugins_aux = array_merge($source_aux, $plugins_aux);
                foreach ($plugins_aux as $_plugin_aux) {
                    foreach ((array) $_plugin_aux as $_key => $_aux_items) {
                        if (!empty($_aux_items) && is_array($_aux_items)) {
                            $aux_items[$_key] = array_merge(ifset($aux_items, $_key, []), $_aux_items);
                        }
                    }
                }
                $conversation['aux_items'] = $aux_items;
            }
        }
        if (empty($conversation['icon_url']) && empty($conversation['icon'])) {
            $conversation['icon'] = 'exclamation';
        }
        if (empty($conversation['icon_url']) && empty($conversation['icon_fa'])) {
            $conversation['icon_fa'] = 'exclamation-circle';
        }

        // In the last message, we store only the last incoming message.
        // Let's get the last letter, it will be incoming or outgoing.
        $last_message = $this->getMessageModel()->select('*')->where('conversation_id = '.(int)$conversation['id'])->order('id DESC')->limit(1)->fetchAssoc();
        $conversation['conversation_last_message'] = $last_message;
        return $conversation;
    }

    protected function workupMessages($conversation, $messages)
    {
        if (!$messages) {
            return $messages;
        }
        $source_helper = crmSourceHelper::factory(crmSource::factory($conversation['source_id']));
        if (wa()->whichUI() === '1.3') {
            $res = $source_helper->workupMessagesInConversation($conversation, $messages);
            $messages = $res ? $res : $messages;
        } else {
            $res = $source_helper->normalazeMessagesExtras($messages);
            $messages = $res ? $res : $messages;
        }
        return $messages;
    }

    protected function getRecipientsByMessages($ids, $type = null)
    {
        $mrm = new crmMessageRecipientsModel();
        $recipients = $mrm->getRecipientsByMessages($ids, $type);
        foreach ($recipients as &$recipient) {
            if ($recipient['destination'] == $recipient['contact_id']) {
                unset($recipients[$recipient['destination']]);
                continue;
            }
        }
        unset($recipient);
        return $recipients;
    }

    protected function getFunnel($deal)
    {
        if (!$deal) {
            return false;
        }

        $funnel = $this->getFunnelModel()->getById($deal['funnel_id']);
        $funnel['stages'] = $this->getFunnelStageModel()->getStagesByFunnel($funnel);

        return $funnel;
    }

    /**
     * @return array
     */
    protected function getContacts($ids)
    {
        $collection = new crmContactsCollection('/id/'.join(',', $ids));
        $col = $collection->getContacts('name,firstname,lastname,middlename,email,password,photo_url_16', 0, count($ids));

        $contacts = array();
        foreach ($col as $id => $c) {
            $c['has_password'] = !empty($c['password']);
            unset($c['password']);
            $contacts[$id] = new crmContact($c);
        }
        return $contacts;
    }

    protected function getCleanDealData()
    {
        $funnel = $this->getFunnelModel()->getAvailableFunnel();
        if (!$funnel) {
            return array();
        }

        $stage_id = $this->getFunnelStageModel()->select('id')->where(
            'funnel_id = '.(int)$funnel['id']
        )->order('number')->limit(1)->fetchField('id');


        // Just empty deal, for new message
        $now = date('Y-m-d H:i:s');
        $deal = $this->getDealModel()->getEmptyDeal();
        $deal = array_merge($deal, array(
            'creator_contact_id' => wa()->getUser()->getId(),
            'create_datetime'    => $now,
            'update_datetime'    => $now,
            'funnel_id'          => $funnel['id'],
            'stage_id'           => $stage_id,
        ));

        $funnels = $this->getFunnelModel()->getAllFunnels();

        if (empty($funnels[$deal['funnel_id']])) {
            return array();
        }

        $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnels[$deal['funnel_id']]);

        return array(
            'deal'    => $deal,
            'funnels' => $funnels,
            'stages'  => $stages,
        );
    }

    protected function getSubject()
    {
        $message = $this->conversation['conversation_last_message'];
        $subject = trim(ifset($message, 'subject', ''));
        $prefix  = substr($subject, 0, 3);
        if (strtolower($prefix) !== 're:') {
            $subject = "Re: $subject";
        }

        return $subject;
    }
    
    protected function getBody()
    {
        $message = $this->conversation['conversation_last_message'];
        $create_datetime = $message['create_datetime'];
        $body = crmHtmlSanitizer::work($message['body']);
        try {
            $contact = new crmContact($message['creator_contact_id']);
            $name = htmlspecialchars($contact->getName());
        } catch (waException $e) {
            $name = _w('Deleted contact');
        }
        $text = _w('<section><p><br></p></section><section data-role="c-email-signature"><p>:SIGNATURE:</p></section><p><br></p><p>:MESSAGE_TIME:, :CLIENT: wrote:</p><blockquote>:BODY:</blockquote>');
        $text = str_replace(':MESSAGE_TIME:', wa_date('datetime', $create_datetime), $text);
        $text = str_replace(':CLIENT:', $name, $text);
        $text = str_replace(':BODY:', $body, $text);
        $text = str_replace(':SIGNATURE:', $this->getUserContact()->getEmailSignature(), $text);
        return $text;
    }

    /**
     * Get addresses for email sources
     * @param array $source_ids
     * @return array - map of type: source_id => email
     * @throws waException
     */
    protected function getSourceEmailAddresses(array $source_ids)
    {
        if (!$source_ids) {
            return [];
        }

        $sm = new crmSourceModel();

        // drop not email sources
        $source_ids = $sm->select('id')->where('id IN(:ids) AND type = :type', [
            'type' => crmSourceModel::TYPE_EMAIL,
            'ids' => $source_ids,
        ])->fetchAll(null, true);

        if (!$source_ids) {
            return [];
        }

        $spm = new crmSourceParamsModel();
        $email_records = $spm->getByField([
            'source_id' => $source_ids,
            'name' => 'email'
        ], true);

        return waUtils::getFieldValues($email_records, 'value', 'source_id');
    }
}
