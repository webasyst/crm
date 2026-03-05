<?php

/**
 * MAX messenger worker for processing updates.
 */
class crmMaxPluginImSourceWorker extends crmImSourceWorker
{
    /**
     * @var crmMaxPluginImSource
     */
    protected $source;

    /**
     * @var crmMaxPluginApi
     */
    protected $api;


    protected $contact;

    protected $deal_id;

    protected $file_model;

    protected $do_not_save_attachments = false;

    /**
     * Constructor
     *
     * @param crmMaxPluginImSource $source
     */
    public function __construct(crmMaxPluginImSource $source)
    {
        parent::__construct($source);
        $this->api = new crmMaxPluginApi($this->source->getParam('token'), $this->source->getId());
        $this->do_not_save_attachments = $this->source->getParam('do_not_save_attachments');
    }

    public function handleUpdates($updates_data)
    {
        if (empty($updates_data) || !is_array($updates_data)) {
            return false;
        }

        $marker = ifset($updates_data['marker']);
        if (!empty($marker) && wa_is_int($marker)) {
            $this->source->saveParam('api_marker', $marker);
        }

        $updates = ifset($updates_data['updates'], []);
        if (empty($updates) || !is_array($updates)) {
            return false;
        }
        
        foreach ($updates as $update) {
            $this->processUpdate($update);
        }

        return true;
    }

    public function isWorkToDo(array $process = [])
    {
        return empty($this->source->getParam('webhook_mode'));
    }

    public function doWork(array $process = [])
    {
        $data = $this->getUpdates();
        //waLog::dump($data, 'crm/max.log');
        return $this->handleUpdates($data);
    }

    /**
     * Get updates via polling
     *
     * @return array|false
     */
    protected function getUpdates()
    {
        try {
            $marker = $this->source->getParam('api_marker');
            $params = empty($marker) ? [] : ['marker' => $marker];
            return $this->api->getUpdates($params);
        } catch (Exception $e) {
            waLog::log('MAX getUpdates error: ' . $e->getMessage(), 'crm/max.log');
            return false;
        }
    }

    /**
     * Process single update
     *
     * @param array $update Update data
     */
    public function processUpdate($update)
    {
        $update_type = ifset($update['update_type']);
        if (empty($update_type)) {
            return;
        }

        switch ($update_type) {
            case 'message_created':
                $this->processNewMessage(ifset($update['message']), ifset($update['user_locale']));
                break;
            case 'message_edited':
                $this->processEditedMessage(ifset($update['message']));
                break;
            case 'message_removed':
                $this->processRemovedMessage(ifset($update['message_id']));
                break;
            case 'bot_started':
                $this->processBotStarted(ifset($update['user']), ifset($update['user_locale']));
                break;
        }

    }

    protected function processBotStarted($max_user, $user_locale)
    {
        if ($this->source->isDisabled() || empty($max_user)) {
            return;
        }
        if (isset($max_user['is_bot']) && $max_user['is_bot']) {
            return;
        }

        $contact = $this->getContact($max_user, $user_locale);
        if ($contact['is_user'] == -1) {
            return;
        }

        $start_text = $this->source->getParam('start_response');
        if (!empty($start_text)) {
            $text = $this->replaceVars($start_text);
            $sanitizer = new crmMaxPluginMessageBodyWorker();
            $text = $sanitizer->sanitize($text);
            $this->api->sendMessage($max_user['user_id'], $text, null, [ 'format' => 'html' ]);
        }
    }

    protected function processNewMessage($message, $user_locale)
    {
        if ($this->source->isDisabled() || empty($message) || empty($message['body']) || empty($message['body']['mid']) || empty($message['sender'])) {
            return;
        }
        if (isset($message['sender']['is_bot']) && $message['sender']['is_bot']) {
            // do not process bot messages
            return;
        }

        $contact = $this->getContact($message['sender'], $user_locale);
        if ($contact['is_user'] == -1) {
            return;
        }

        $param = (new crmMessageParamsModel)->getByField(['name' => 'max_message_id', 'value' => $message['body']['mid']]);
        if (!empty($param)) {
            // already processed
            return;
        }

        $bot_responses = $this->botCommand($message);
        $do_not_save_this_message = !empty($bot_responses) && empty(array_filter($bot_responses, function($item) {
            return empty($item['do_not_save_this_message']);
        }));

        $crm_message = null;
        if ($do_not_save_this_message && !empty($bot_responses)) {
            $crm_message = $this->prepareMessageData($message);
        } else {
            $this->deal_id = $this->findDeal($message, $contact);
            $crm_message = $this->prepareMessageData($message);
            $message_id = $this->source->createMessage($crm_message);
            $crm_message['id'] = $message_id;
        }
        
        if (!empty($bot_responses)) {
            $source_sender = new crmMaxPluginImSourceMessageSender($this->source, $crm_message);
            foreach ($bot_responses as $_res) {
                $source_sender->reply($_res);
            }
        }
    }

    protected function getContact($max_user, $user_locale)
    {
        $contact = $this->findContact($max_user);

        if (empty($contact)) {
            $contact = $this->createContact($max_user, $user_locale);
        }
        $this->setPhoto($max_user, $contact);
        $this->contact = $contact;
        return $contact;
    }

    protected function findContact($max_user)
    {
        $contact_data_model = new waContactDataModel();
        $find_by_username = false;
        $items = $contact_data_model->getByField([
            'field' => 'max_user_id',
            'value' => $max_user['user_id'],
        ], 'contact_id');

        if (empty($items) && !empty($max_user['username'])) {
            $items = $contact_data_model->getByField([
                'field' => 'im', 
                'ext'   => 'max',
                'value' => [
                    $max_user['username'], 
                    'id'.$max_user['user_id'], 
                ],
            ], 'contact_id');
            $find_by_username = true;
        }

        if (empty($items)) {
            return null;
        }

        $contact_ids = array_keys($items);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (empty($contact_ids)) {
            return null;
        }
        $contact_id = $contact_ids[0];
        $contact = new crmContact($contact_id);
        if (empty($contact) || !$contact->exists()) {
            return null;
        }
        if ($find_by_username) {
            $contact->add('max_user_id', $max_user['user_id']);
            $contact->save();
        }

        return $contact;
    }

    protected function createContact($max_user, $user_locale)
    {
        $locale = $user_locale ?: $this->source->getParam('locale');
        $data = [
            'firstname'         => ifset($max_user['first_name'], ifset($max_user['name'], ifset($max_user['username']))),
            'lastname'          => ifset($max_user['last_name']),
            'im.max'            => ifset($max_user['username'], 'id'.$max_user['user_id']),
            'max_user_id'       => $max_user['user_id'],
            'locale'            => in_array($locale, ['ru', 'ru_RU']) ? 'ru_RU' : 'en_US',
            'create_app_id'     => 'crm',
            'create_contact_id' => 0,
            'create_method'     => 'source/im/max',
            'crm_user_id'       => $this->source->getNormalizedResponsibleContactId(),
        ];
        $data = array_filter($data);
        $contact = new crmContact();
        if ($errors = $contact->save($data)) {
            waLog::log(waUtils::jsonEncode($errors), 'crm/plugins/max.log');
        }

        $this->source->addContactsToSegments($contact->getId());

        return $contact;
    }

    protected function setPhoto($max_user, waContact $contact, $force_update = false)
    {
        if (empty($max_user['full_avatar_url']) && empty($max_user['avatar_url']) || !$force_update && !empty($contact->get('photo'))) {
            return;
        }

        if (empty($max_user['avatar_url'])) {
            return;
        }

        $net = new waNet();
        $url = $max_user['avatar_url'];
        try {
            $file = $net->query($url);
            $content_type = $net->getResponseHeader('Content-Type');
        } catch (waException $ex) {
            return;
        }

        if (empty($file) || empty($content_type) || strpos($content_type, 'image/') === false) {
            return;
        }
        $ext = substr($content_type, 6);
        $filepath = tempnam(sys_get_temp_dir(), __METHOD__) . '.' . $ext;
        file_put_contents($filepath, $file);
        $contact->setPhoto($filepath);
        waFiles::delete($filepath);
    }

    /**
     * Process edited message
     *
     * @param array $message Edited message data
     */
    protected function processEditedMessage($message)
    {
        if ($this->source->isDisabled() || empty($message) || empty($message['body']) || empty($message['body']['mid'])) {
            return;
        }

        $max_message_id = $message['body']['mid'];

        // Find existing message
        $param = (new crmMessageParamsModel)->getByField(['name' => 'max_message_id', 'value' => $max_message_id]);
        if (empty($param)) {
            return;
        }

        $message_id = $param['message_id'];
        $new_body = crmMaxPluginMessageBodyWorker::parser($message['body']);

        (new crmMessageModel)->updateById($message_id, ['body' => $new_body]);
    }

    protected function processRemovedMessage($max_message_id)
    {
        // TODO
    }

    protected function findDeal($message, crmContact $contact)
    {
        // Find opened conversation by this source and this contact
        $conversation = $this->source->findConversation($contact->getId());
        if ($conversation) {

            return $conversation['deal_id'];
        }

        // If conversation not found it would be created in createMessage step and by that time we need find deal for this new message

        $dm = new crmDealModel();
        $deals = $dm->getByField(array(
            'contact_id' => $contact->getId(),
            'status_id'  => crmDealModel::STATUS_OPEN,
            'funnel_id'  => $this->source->getFunnelId(),
        ), true);

        if (count($deals) > 1 && $this->source->getParam('create_deal')) {
            return $this->createDeal($message, $contact);
        } elseif (!empty($deals)) {
            return $deals[0]['id'];
        } elseif ($this->source->getParam('create_deal')) {
            return $this->createDeal($message, $contact);
        }

        return null;
    }

    protected function createDeal($message, crmContact $contact)
    {
        $description = $message['body']['text'];
        $deal = [
            'name'               => $contact->getName(),
            'contact_id'         => $contact->getId(),
            'creator_contact_id' => $contact->getId(),
            'description'        => $description ? $description : null,
        ];
        return $this->source->createDeal($deal);
    }

    protected function prepareMessageData($message)
    {
        $message_params = [
            'max_message_id' => $message['body']['mid'],
            'max_chat_id'    => $message['recipient']['chat_id'],
        ];

        $failed_download_files = [];
        $attachments = [];
        if (!empty($message['body']['attachments'])) {
            foreach($message['body']['attachments'] as $attachment) {
                if (in_array($attachment['type'], ['image', 'video', 'audio', 'file', 'sticker'])) {
                    $file_id = $this->saveFile($attachment, $failed_download_files);
                    if ($file_id) {
                        $attachments[] = $file_id;
                    }
                } elseif ($attachment['type'] === 'location') {
                    $message_params['location'] = $attachment['latitude'] . ', ' . $attachment['longitude'];    
                }
            }
        }

        $body = crmMaxPluginMessageBodyWorker::parser($message['body']);

        if (!empty($message['link']) && !empty($message['link']['message'])) {
            $blockquote = crmMaxPluginMessageBodyWorker::parser($message['link']['message']);
            if (!empty($blockquote)) {
                $body = '<blockquote>' . $blockquote . '</blockquote>' . $body;
            }
            if (!empty($message['link']['message']['attachments'])) {
                foreach($message['link']['message']['attachments'] as $attachment) {
                    if (in_array($attachment['type'], ['image', 'video', 'audio', 'file', 'sticker'])) {
                        $file_id = $this->saveFile($attachment, $failed_download_files);
                        if ($file_id) {
                            $attachments[] = $file_id;
                        }
                    } elseif ($attachment['type'] === 'location') {
                        $message_params['location'] = $attachment['latitude'] . ', ' . $attachment['longitude'];    
                    }
                }
            }
        }

        if (!empty($failed_download_files)) {
            $message_params['max_attachments'] = array_map( function ($file) {
                $res = [
                    'type' => $file['type'],
                    'url'  => $file['payload']['url'],
                ];
                if (isset($file['filename'])) {
                    $res['filename'] = $file['filename'];
                }
                if (isset($file['size'])) {
                    $res['size'] = $file['size'];
                }
                return $res;
            }, $failed_download_files);
        }

        $message_params['attachment'] = !empty($attachments);
        
        $data = [
            'source_id'          => $this->source->getId(),
            'creator_contact_id' => $this->contact->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'direction'          => crmMessageModel::DIRECTION_IN,
            'contact_id'         => $this->contact->getId(),
            'deal_id'            => $this->deal_id,
            'subject'            => '',
            'body'               => $body,
            'from'               => $message['sender']['user_id'],
            'to'                 => $message['recipient']['user_id'],
            'params'             => $message_params,
        ];

        if (!empty($attachments)) {
            $data['attachments'] = $attachments;
        }
        return $data;
    }

    protected function saveFile($file_data, &$not_downloaded_files)
    {
        if ($this->do_not_save_attachments && !in_array($file_data['type'], ['video', 'audio'])) {
            // video files will be saved in any case
            $not_downloaded_files[] = $file_data;
            return;
        }
        
        $net = new waNet();
        $url = $file_data['payload']['url'];
        try {
            $file = $net->query($url);
            $content_type = $net->getResponseHeader('Content-Type');
        } catch (waException $ex) {
            if ($ex->getCode() == 28) {
                $not_downloaded_files[] = $file_data;
            }
            waLog::dump('Error downloading file (' . $url . '): ' . $ex->getMessage(), 'crm/max.log');
            return;
        }

        if (empty($file) || empty($content_type)) {
            waLog::dump('Error downloading file (' . $url . '): empty file or content type ('.$content_type.')', 'crm/max.log');
            return;
        }
        if ($file_data['type'] === 'image') {
            if (strpos($content_type, 'image/') === false) {
                waLog::dump('Error downloading file (' . $url . '): invalid image content type - '.$content_type, 'crm/max.log');
                return;
            }
            $ext = substr($content_type, 6);
            $filepath = tempnam(sys_get_temp_dir(), 'max_plugin_') . '.' . $ext;
            $filename = pathinfo($filepath, PATHINFO_BASENAME);
        } elseif ($file_data['type'] === 'video') {
            if (strpos($content_type, 'video/') === false) {
                waLog::dump('Error downloading file (' . $url . '): invalid video content type - '.$content_type, 'crm/max.log');
                return;
            }
            $ext = substr($content_type, 6);
            $filepath = tempnam(sys_get_temp_dir(), 'max_plugin_') . '.' . $ext;
            $filename = pathinfo($filepath, PATHINFO_BASENAME);
        } elseif ($file_data['type'] === 'audio') {
            if (strpos($content_type, 'audio/') === false) {
                waLog::dump('Error downloading file (' . $url . '): invalid audio content type - '.$content_type, 'crm/max.log');
                return;
            }
            $ext = substr($content_type, 6);
            $filepath = tempnam(sys_get_temp_dir(), 'max_plugin_') . '.' . $ext;
            $filename = pathinfo($filepath, PATHINFO_BASENAME);
        } elseif ($file_data['type'] === 'sticker') {
            if (strpos($content_type, 'image/') === 0) {
                $ext = substr($content_type, 6);
                $filepath = tempnam(sys_get_temp_dir(), 'max_plugin_') . '.' . $ext;
                $filename = pathinfo($filepath, PATHINFO_BASENAME);
            }
        } elseif ($file_data['type'] === 'file') {
            if (empty($file_data['filename'])) {
                waLog::dump('Error downloading file (' . $url . '): empty filename', 'crm/max.log');
                return;
            }
            $filename = $file_data['filename'];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $ext = empty($ext) ? '' : '.'.$ext;
            $filepath = tempnam(sys_get_temp_dir(), 'max_plugin_') . $ext;
        } else {
            waLog::dump('Error downloading file (' . $url . '): empty file type', 'crm/max.log');
            return;
        }

        if (empty($filepath)) {
            waLog::dump('Error downloading file (' . $url . '): empty file path', 'crm/max.log');
            return;
        }

        file_put_contents($filepath, $file);
        $data = [
            'creator_contact_id' => $this->contact->getId(),
            'name' => $filename,
            'ext' => $ext,
            'source_type' => crmFileModel::SOURCE_TYPE_MESSAGE,
        ];
        if (!empty($this->deal_id)) {
            $data['contact_id'] = -1 * $this->deal_id;
        } else {
            $data['contact_id'] = $this->contact->getId();
        }

        $file_id = $this->getFileModel()->add($data, $filepath);
        return $file_id;
    }

    public function botCommand($message)
    {
        if (empty($message['body']['text']) || !preg_match('/^\/\w+/', (string)$message['body']['text'])) {
            // Not a bot command
            return [];
        }

        $command = $message['body']['text'];

        $locale = wa()->getLocale();
        wa()->setLocale($this->contact->getLocale());
        $params = [
            'command' => $command,
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
            $commands_arr = $this->source->getParam('commands');
            if (!empty($commands_arr['command']) && 
                count($commands_arr['command']) === count(ifempty($commands_arr['response'], []))
            ) {
                $commands = array_combine($commands_arr['command'], $commands_arr['response']);
                if (isset($commands[$command])) {
                    $command_index = array_search($command, $commands_arr['command']);
                    $result = [[
                        'body' => $commands[$command],
                        'is_auto_response' => true,
                        'do_not_save_this_message' => empty($commands_arr['save'][$command_index]),
                    ]];
                }
            }
        }

        if (empty($result)) {
            $answer_text = $this->source->getParam('unknown_command_response') ?: _wd('crm_max', 'Unknown command');
            $result = [[
                'body' => $this->replaceVars($answer_text),
                'is_auto_response' => true,
            ]];
        }
        wa()->setLocale($locale);
        
        return $result;
    }

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

    protected function getFileModel()
    {
        if (empty($this->file_model)) {
            $this->file_model = new crmFileModel();
        }
        return $this->file_model;
    }
}
