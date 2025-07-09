<?php

class crmTelegramPluginCommands
{
    /**
     * @var crmMessageModel
     */
    protected $mm;

    /**
     * @var crmTelegramPluginImSourceMessageSender
     */
    protected $source_sender;

    /**
     * @var crmSource
     */
    protected $source;

    /**
     * @var crmContact
     */
    protected $contact;

    protected $telegram_message;

    protected $crm_message;

    /**
     * crmTelegramPluginCommands constructor.
     * @param crmSource $source
     * @param crmContact $contact
     * @param array $telegram_message
     * @param int $crm_message_id
     */
    public function __construct(crmSource $source, crmContact $contact, $telegram_message)
    {
        $this->source = $source;
        $this->contact = $contact;
        $this->telegram_message = $telegram_message;
    }


    public function botCommand()
    {
        if (empty($this->telegram_message['text']) || !preg_match('/^\/\w+/', (string)$this->telegram_message['text'])) {
            // Not a bot command
            return [];
        }

        $locale = wa()->getLocale();
        wa()->setLocale($this->contact->getLocale());
        $params = [
            'command' => $this->telegram_message['text'],
            'contact' => $this->contact,
            'source' => $this->source,
        ];
        $event_result = wa()->event(['crm', 'message.bot.command'], $params);
        $result = array_values(array_filter($event_result, function($item) {
            return !empty($item['answer']);
        }));
        $result = array_map(function($item) {
            $item['body'] = $item['answer'];
            unset($item['answer']);
            $item['is_auto_response'] = true;
            return $item;
        }, $result);
        
        if (empty($result)) {
            if ($this->telegram_message['text'] === '/start') {
                $start_text = $this->source->getParam('start_response');
                if (!empty($start_text)) {
                    $result = [[
                        'body' => $this->replaceVars($start_text),
                        'is_auto_response' => true,
                    ]];
                }
            } else {
                $commands_arr = $this->source->getParam('commands');
                if (!empty($commands_arr['command']) && 
                    count($commands_arr['command']) === count(ifempty($commands_arr['response'], []))
                ) {
                    $commands = array_combine($commands_arr['command'], $commands_arr['response']);
                    if (isset($commands[$this->telegram_message['text']])) {
                        $command_index = array_search($this->telegram_message['text'], $commands_arr['command']);
                        $result = [[
                            'body' => $commands[$this->telegram_message['text']],
                            'is_auto_response' => true,
                            'do_not_save_this_message' => empty($commands_arr['save'][$command_index]),
                        ]];
                    }
                }
            }
        }

        if (empty($result)) {
            $answer_text = $this->source->getParam('unknown_command_response') ?: _wd('crm_telegram', 'Unknown command');
            $result = [[
                'body' => $this->replaceVars($answer_text),
                'is_auto_response' => true,
            ]];
        }
        wa()->setLocale($locale);
        
        return $result;
    }

    /**
     * Save phone number on message with contact data
     */
    public function savePhone()
    {
        if (ifset($this->telegram_message, 'contact', 'phone_number', false) &&
            ifset($this->telegram_message, 'from', 'id', null) === ifset($this->telegram_message, 'contact', 'user_id', null)
        ) {
            $phone = waContactPhoneField::cleanPhoneNumber($this->telegram_message['contact']['phone_number']);
            $phones = array_filter($this->contact->get('phone'), function($ph) use ($phone) {
                return waContactPhoneField::cleanPhoneNumber($ph['value']) === $phone;
            });

            $is_contact_updated = false;
            if (empty($phones)) {
                $found_contact = $this->findContactByPhone($phone);
                if (empty($found_contact)) {
                    // if contact was not merged with another, add phone to current contact
                    $this->contact->add('phone', [
                        'value' => $phone,
                        'ext' => 'telegram',
                        'status' => 'confirmed',
                    ]);
                    $this->contact->save();
                    $is_contact_updated = true;
                } else {
                    $internal_message = sprintf(
                        _wd('crm_telegram', 'Another client was found with this phone number: <a href="%s">%s</a>.'),
                        wa()->getConfig()->getBackendUrl(true) . 'crm/contact/' . $found_contact['id'],
                        $found_contact['name']
                    );
                    $this->createInternalServiceMessage($internal_message);
                }
            }

            $text = $this->source->getParam('phone_response') or $text = _wd('crm_telegram', 'Thank you!');
            return [
                'body' => $text,
                'reply_markup' => [ 'remove_keyboard' => true ],
                'is_auto_response' => true,
                'is_contact_updated' => $is_contact_updated,
            ];
        }
    }

    protected function findContactByPhone($phone)
    {
        $phone_record = (new waContactDataModel)->getByField(['field' => 'phone', 'value' => $phone, 'sort' => '0']);
        if (!empty($phone_record) && !empty(ifset($phone_record['contact_id']))) {
            $contact = new waContact($phone_record['contact_id']);
            if ($contact && $contact->exists()) {
                return $contact;
            }
        }
    }

    protected function createInternalServiceMessage($text)
    {
        $message_id = $this->source->createMessage([
            'creator_contact_id' => 0,
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $this->contact->getId(),
            //'deal_id'            => ifset($this->message['deal_id']),
            'subject'            => '',
            'body'               => $text,
            'from'               => _wd('crm_telegram', 'Internal service message'),
            'to'                 => wa()->getUser()->getId(),
            'params'             => ['internal' => '1'],
        ], crmMessageModel::DIRECTION_OUT);
        return $message_id;
    }

    /**
     * Set the value of some variables when sending a prepared answer.
     * @param string $content
     * @return string
     */
    protected function replaceVars($content, $vars = [])
    {
        $vars = [
            '$contact_name' => $this->contact->getName(),
            '$site_name'    => wa()->accountName(),
            '$site_url'     => wa()->getRootUrl(true),
            '$site_link'    => '<a href="'.wa()->getRootUrl(true).'">'.wa()->accountName().'</a>',
            '$bot_name'     => $this->source->getParam('firstname'),
            '$bot_username' => $this->source->getParam('username'),
        ] + $vars;

        return strtr($content, $vars);
    }
}
