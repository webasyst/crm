<?php

class crmTelegramPluginImSourceWorker extends crmImSourceWorker
{
    /**
     * @var crmTelegramPluginApi
     */
    protected $api;

    /**
     * @var crmTelegramPluginStickerModel
     */
    protected $telegram_sticker_model;

    /**
     * @var crmTelegramPluginFileParamsModel
     */
    protected $telegram_file_params_model;

    /**
     * @var crmTelegramPluginMediaDownloader
     */
    protected $downloader;

    /**
     * @var array
     */
    protected $telegram_message;

    /**
     * @var crmContact
     */
    protected $contact;

    protected $deal_id;

    /**
     * @var bool
     */
    protected $is_new_contact;

    public function isWorkToDo(array $process = array())
    {
        return empty($this->source->getParam('webhook_mode'));
    }

    public function doWork(array $process = array())
    {
        $api_offset = $this->source->getParam('api_offset');

        $this->api = $this->getApi();
        $this->downloader = $this->getDownloader();

        $new_messages = $this->api->getUpdates($api_offset);
        if (empty($new_messages['ok'])) {
            return;
        }
        $new_messages = $new_messages['result'];

        $new_api_offset = 0;
        foreach ($new_messages as $m) {
            $_offset = $this->handleIncomingMessage($m);
            if (!empty($_offset) && $_offset > $new_api_offset && $_offset > $api_offset) {
                $new_api_offset = $_offset;
            }
        }

        if (!empty($new_api_offset)) {
            $this->source->saveParam('api_offset', $new_api_offset);
        }
    }

    public function handleIncomingMessage($tg_mess)
    {
        $this->is_new_contact = $this->deal_id = false;
        $this->telegram_message = $tg_mess;

        if (!isset($this->telegram_message['update_id']) || (int)$this->telegram_message['update_id'] <= 0) {
            return null;
        }
        $new_api_offset = ++$this->telegram_message['update_id'];

        // If source is disabled -- don't create contact, deal and message.
        // Just save telegram_update_id for api offset.
        if ($this->source->isDisabled()) {
            return $new_api_offset;
        }

        $from = isset($this->telegram_message['message']) ? 
            ifset($this->telegram_message['message']['from'], []) :
            (
                isset($this->telegram_message['edited_message']) ? 
                    ifset($this->telegram_message['edited_message']['from'], []) : []
            );
        $this->contact = $this->findContact($from);
        // Ignore blocked users
        if ($this->contact['is_user'] == -1) {
            return $new_api_offset;
        }

        if ($this->is_new_contact) {
            // add contacts to segments
            $this->source->addContactsToSegments($this->contact->getId());

            // set locale
            $locale = $this->source->getParam('locale');
            if ($locale) {
                $this->contact->save(array('locale' => $locale));
            }
        }

        // Handle edited messages
        if (isset($this->telegram_message['edited_message'])) {
            $message = $this->source->findMessage(ifset($this->telegram_message['edited_message']['message_id'], '-1'));
            if (empty($message)) {
                // Nothing to update
                return $new_api_offset;
            }
            $edited_message = $this->prepareMessage();
            $edited_message['id'] = $message['id'];
            $edited_message['conversation_id'] = $message['conversation_id'];

            $this->source->handleMessageEdit($edited_message, ifset($this->telegram_message['edited_message']['edit_date'], null));
            return $new_api_offset;
        }

        // Ignore messages from public chats and dialogs
        if (!isset($this->telegram_message['message']) ||
            $this->telegram_message['message']['from']['id'] !== $this->telegram_message['message']['chat']['id']
        ) {
            return $new_api_offset;
        }

        $message = $this->source->findMessage(ifset($this->telegram_message['message']['message_id'], '-1'));
        if (!empty($message)) {
            // Message was already processed (this is doubled callback)
            return $new_api_offset;
        }

        $commands = new crmTelegramPluginCommands($this->source, $this->contact, $this->telegram_message['message']);
        $bot_responses = $commands->botCommand();
        //waLog::dump($bot_responses, 'telegram-debug.log');
        if (isset($this->telegram_message['message']['contact'])) {
            $bot_responses[] = $commands->savePhone();
        }
        $do_not_save_this_message = !empty($bot_responses) && empty(array_filter($bot_responses, function($item) {
            return empty($item['do_not_save_this_message']);
        }));

        $crm_message = null;
        if ($do_not_save_this_message && !empty($bot_responses)) {
            $crm_message = $this->prepareMessage();
        } else {
            $this->deal_id = $this->findDeal();
            $message_id = $this->createMessage();
            $crm_message = (new crmMessageModel)->getMessage($message_id);
        }
        
        if (!empty($bot_responses)) {
            $source_sender = new crmTelegramPluginImSourceMessageSender($this->source, $crm_message);
            foreach ($bot_responses as $_res) {
                $source_sender->reply($_res);
            }
        }

        return $new_api_offset;
    }

    /**
     * @param array $telegram_user
     * @return crmContact
     * @throws waException
     */
    protected function findContact($telegram_user)
    {
        $this->is_new_contact = false;
        $contact = $this->findContactByTelegramIds($telegram_user);
        if (!$contact) {
            $contact = $this->exportContact($telegram_user);
        }
        $this->getDownloader()->setContactPhoto($contact);
        return $contact;
    }

    protected function getDownloader()
    {
        if (empty($this->downloader)) {
            $this->downloader = new crmTelegramPluginMediaDownloader($this->source, $this->getApi());
        }
        return $this->downloader;
    }

    protected function getApi()
    {
        if (empty($this->api)) {
            $this->api = new crmTelegramPluginApi($this->source->getParam('access_token'));
        }
        return $this->api;
    }

    /**
     * @param array $telegram_user
     * @return crmContact|null
     */
    protected function findContactByTelegramIds($telegram_user)
    {
        $searcher = new crmTelegramPluginContactSearcher($telegram_user);
        return $searcher->findByTelegram();
    }

    /**
     * @param array $telegram_user
     * @return crmContact
     */
    protected function exportContact($telegram_user)
    {
        $responsible_contact_id = $this->source->getNormalizedResponsibleContactId();
        $options = [];
        if ($responsible_contact_id > 0) {
            $options['crm_user_id'] = $responsible_contact_id;
        }
        $exporter = new crmTelegramPluginContactExporter($telegram_user, $options);
        $contact = $exporter->export();
        $this->is_new_contact = true;
        return $contact;
    }

    protected function findDeal()
    {
        if ($this->source->getParam('create_deal') && $this->is_new_contact) {
            return $this->createDeal();
        }

        // Find opened conversation by this source and this contact
        $conversation = $this->source->findConversation($this->contact->getId());
        if ($conversation) {
            return $conversation['deal_id'];
        }

        // If conversation not found it would be created in createMessage step and by that time we need find deal for this new message

        $dm = new crmDealModel();
        $deals = $dm->getByField(array(
            'contact_id' => $this->contact->getId(),
            'status_id'  => crmDealModel::STATUS_OPEN,
            'funnel_id'  => $this->source->getFunnelId(),
        ), true);

        if (count($deals) > 1 && $this->source->getParam('create_deal')) {
            return $this->createDeal();
        } elseif (!empty($deals)) {
            return $deals[0]['id'];
        } elseif ($this->source->getParam('create_deal')) {
            return $this->createDeal();
        }

        return null;
    }

    protected function createDeal()
    {
        $message_data = new crmTelegramPluginMessage((array)ifset($this->telegram_message));

        $description = $message_data->getText() ? $message_data->getText() : $message_data->getCaption();

        $deal = array(
            'name'               => $this->contact->getName(),
            'contact_id'         => $this->contact->getId(),
            'creator_contact_id' => $this->contact->getId(),
            'description'        => $description ? $description : null,
        );

        return $this->source->createDeal($deal);
    }

    protected function prepareMessage()
    {
        $message = new crmTelegramPluginMessage((array)ifset($this->telegram_message));

        $data = array(
            'creator_contact_id' => $this->contact->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'direction'          => crmMessageModel::DIRECTION_IN,
            'source_id'          => $this->source->getId(),
            'contact_id'         => $this->contact->getId(),
            'deal_id'            => ifset($this->deal_id),
            'subject'            => '',
            'body'               => crmTelegramPluginHtmlSanitizer::parser($message),
            'from'               => $message->getSenderField('id'),
            'to'                 => $this->source->getParam('username'),
            'params'             => array(
                'telegram_message_id' => $message->getId(),
                'username'            => $message->getSenderField('username'),
                'datetime'            => $message ? $message->getDatetime() : date('Y-m-d H:i:s'),
            )
        );

        $contact_data = $message->getContactData();
        if (!empty($contact_data)) {
            $name_parts = [];
            if (!empty($contact_data['first_name'])) {
                $name_parts[] = $contact_data['first_name'];
            }
            if (!empty($contact_data['last_name'])) {
                $name_parts[] = $contact_data['last_name'];
            }
            $body_parts = [];
            if (!empty($data['body'])) {
                $body_parts[] = $data['body'];
            }
            $body_parts[] = implode(' ', $name_parts) . ': ' . ifset($contact_data['phone_number']);
            $data['body'] = implode('<br><br>', $body_parts);
        }

        $this->downloader->setContext($this->contact->getId(), ifset($this->deal_id), $this->contact->getId());

        if ($message->getSticker()) {
            $data['params']['sticker_id'] = $this->downloader->getSticker($message->getSticker());
        }

        if ($message->getPhoto()) {
            $photo = $message->getPhoto();
            array_multisort(array_column($photo, 'file_size'), $photo);
            $p = end($photo);
            $this->saveFile($p, crmTelegramPluginMediaDownloader::TYPE_PHOTO, $data);
        }

        if ($message->getAudio()) {
            $this->saveFile($message->getAudio(), crmTelegramPluginMediaDownloader::TYPE_AUDIO, $data);
        }
        if ($message->getVoice()) {
            $this->saveFile($message->getVoice(), crmTelegramPluginMediaDownloader::TYPE_VOICE, $data);
        }
        if ($message->getVideo()) {
            $this->saveFile($message->getVideo(), crmTelegramPluginMediaDownloader::TYPE_VIDEO, $data);
        }
        if ($message->getVideoNote()) {
            $this->saveFile($message->getVideoNote(), crmTelegramPluginMediaDownloader::TYPE_VIDEO_NOTE, $data);
        }
        if ($message->getDocument()) {
            $this->saveFile($message->getDocument(), crmTelegramPluginMediaDownloader::TYPE_DOCUMENT, $data);
        }

        if ($message->getLocation()) {
            $location = $message->getLocation();
            $data['params']['location'] = $location['latitude'].', '.$location['longitude'];
        }

        if ($message->getVenue()) {
            $venue = $message->getVenue();
            $data['params']['venue_location'] = $venue['location']['latitude'].', '.$venue['location']['longitude'];
            $data['params']['venue_title'] = ifset($venue['title']);
            $data['params']['venue_address'] = ifset($venue['address']);
            $data['params']['venue_foursquare_id'] = ifset($venue['foursquare_id']);
            unset($data['params']['location']);
        }

        if ($message->getCaption()) {
            $data['params']['caption'] = crmTelegramPluginHtmlSanitizer::parserCaption($message);
        }

        if ($message->getForwardData()) {
            $fwd = $message->getForwardData();
            $fwd_contact = $this->findContact($fwd);
            $data['params']['forward_contact_id'] = $fwd_contact->getId();
            $data['params']['forward_name'] = trim(ifset($fwd['first_name']) .' '. ifset($fwd['last_name']));
            $data['params']['forward_username'] = ifset($fwd['username']);
        }

        $this->downloader->clearContext();

        return $data;
    }

    protected function saveFile($f, $type, &$data)
    {
        $result = $this->downloader->downloadFile($f['file_id'], $type, ['file_name' => ifset($f['file_name'])]);
        if (empty($result['crm_file_id'])) {
            $data['params']['footer'] = empty($data['params']['footer']) ? '' : sprintf('%s<br>', $data['params']['footer']);
            $data['params']['footer'] = sprintf(_wd('crm_telegram', 'File <b>%s</b> could not be received'), ifset($f['file_name']));
            if (!empty($result['error']['description'])) {
                $data['params']['footer'] .= ': <b>'.$result['error']['description'].'</b>';
            }
            return;
        }
        $crm_file_id = $result['crm_file_id'];
        $data['attachments'][] = $crm_file_id;
        if ($type === crmTelegramPluginMediaDownloader::TYPE_DOCUMENT) {
            $data['params']['attachment'] = true;
        } else {
            $this->getTelegramFileParamsModel()->set($crm_file_id, ['type' => $type]);
            $data['params'][$type] = true;
        }
    }

    protected function createMessage()
    {
        $data = $this->prepareMessage();
        $message_id = $this->source->createMessage($data);
        return $message_id;
    }

    /**
     * @return crmTelegramPluginStickerModel
     */
    public function getTelegramStickerModel()
    {
        if (!$this->telegram_sticker_model) {
            $this->telegram_sticker_model = new crmTelegramPluginStickerModel();
        }
        return $this->telegram_sticker_model;
    }

    /**
     * @return crmTelegramPluginFileParamsModel
     */
    public function getTelegramFileParamsModel()
    {
        if (!$this->telegram_file_params_model) {
            $this->telegram_file_params_model = new crmTelegramPluginFileParamsModel();
        }
        return $this->telegram_file_params_model;
    }
}
