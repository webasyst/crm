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
        return true;
    }

    public function doWork(array $process = array())
    {
        $api_offset = $this->source->getParam('api_offset');

        $this->api = new crmTelegramPluginApi($this->source->getParam('access_token'));
        $this->downloader = new crmTelegramPluginMediaDownloader($this->source, $this->api);

        $new_messages = $this->api->getUpdates($api_offset);
        if (empty($new_messages['ok'])) {
            return;
        }
        $new_messages = $new_messages['result'];

        foreach ($new_messages as $m) {
            $this->is_new_contact = $this->deal_id = false;
            $this->telegram_message = $m;
            $new_api_offset = ++$this->telegram_message['update_id'];
            // If source is disabled -- don't create contact, deal and message.
            // Just save telegram_update_id for api offset.
            if ($this->source->isDisabled()) {
                continue;
            }

            // Ignore messages from public chats and dialogs
            if (!isset($this->telegram_message['message']) ||
                $this->telegram_message['message']['from']['id'] !== $this->telegram_message['message']['chat']['id']) {
                continue;
            }

            $this->contact = $this->findContact($this->telegram_message['message']['from']);
            // Ignore blocked users
            if ($this->contact['is_user'] == -1) {
                continue;
            }

            if ($this->is_new_contact) {
                // add contacts to segments
                $this->source->addContactsToSegments($this->contact->getId());

                // set local
                $locale = $this->source->getParam('locale');
                if ($locale) {
                    $this->contact->save(array('locale' => $locale));
                }
            }

            $this->deal_id = $this->findDeal();
            $message_id = $this->createMessage();

            if ($this->is_new_contact) {
                $commands = new crmTelegramPluginCommands($this->source, $this->contact, $this->telegram_message['message'], $message_id);
                $commands->start();
            }
        }

        if (!empty($new_api_offset)) {
            $this->source->setParam('api_offset', $new_api_offset);
        }
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
        $this->downloader->setContactPhoto($contact);
        return $contact;
    }

    /**
     * @param array $telegram_user
     * @return crmContact|null
     */
    protected function findContactByTelegramIds($telegram_user)
    {
        $searcher = new crmTelegramPluginContactSearcher($telegram_user);
        return $searcher->findByTelegramIds();
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

    protected function prepareMessage($message)
    {
        if (!($message instanceof crmTelegramPluginMessage)) {
            $message = null;
        }

        $data = array(
            'creator_contact_id' => $this->contact->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
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

        $fpm = $this->getTelegramFileParamsModel();

        if ($message->getSticker()) {
            $data['params']['sticker_id'] = $this->downloader->getSticker($message->getSticker());
        }

        if ($message->getAudio()) {
            $file_id = $this->downloader->getAudio($message->getAudio());
            $data['attachments'][] = $file_id;
            $fpm->set($file_id, array('type' => 'audio'));
            $data['params']['audio'] = true;
        }

        if ($message->getPhoto()) {
            $file_id = $this->downloader->getPhoto($message->getPhoto());
            $data['attachments'][] = $file_id;
            $fpm->set($file_id, array('type' => 'photo'));
            $data['params']['photo'] = true;
        }

        if ($message->getVoice()) {
            $file_id = $this->downloader->getVoice($message->getVoice());
            $data['attachments'][] = $file_id;
            $fpm->set($file_id, array('type' => 'voice'));
            $data['params']['voice'] = true;
        }

        if ($message->getVideo()) {
            $file_id = $this->downloader->getVideo($message->getVideo());
            $data['attachments'][] = $file_id;
            $fpm->set($file_id, array('type' => 'video'));
            $data['params']['video'] = true;
        }

        if ($message->getVideoNote()) {
            $file_id = $this->downloader->getVideo($message->getVideoNote());
            $data['attachments'][] = $file_id;
            $fpm->set($file_id, array('type' => 'video_note'));
            $data['params']['video_note'] = true;
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

        if ($message->getDocument()) {
            $data['attachments'][] = $this->downloader->getDocument($message->getDocument());
            $data['params']['attachment'] = true;
        }

        //

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

        return $data;
    }

    protected function createMessage()
    {
        $message = new crmTelegramPluginMessage((array)ifset($this->telegram_message));
        $data = $this->prepareMessage($message);
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
