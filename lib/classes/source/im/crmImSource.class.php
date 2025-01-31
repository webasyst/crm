<?php

abstract class crmImSource extends crmSource
{
    const STATUS_NO = 'no';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_READ = 'read';

    protected $type = crmSourceModel::TYPE_IM;

    protected static $message_statuses = [
        self::STATUS_NO => -1,
        self::STATUS_SENT => 1,
        self::STATUS_FAILED => 2,
        self::STATUS_DELIVERED => 3,
        self::STATUS_READ => 4,
    ];

    public function __construct($id = null, array $options = array())
    {
        parent::__construct($id, $options);

        if (!$this->provider && $this->id > 0) {
            $provider = self::getSourceModel()->select('provider')->where('id = ?', $id)->fetchField();
            $this->provider = $provider;
        }

        if (!$this->provider) {
            throw new crmSourceException(
                sprintf("Couldn't factor im source instance: unknown provider %s", $this->provider ? $this->provider : 'NULL')
            );
        }
    }

    /**
     * @param int|string $id
     * @param array $options
     * @return crmImSource
     * @throws crmSourceException
     */
    public static function factory($id, array $options = array())
    {
        $instance = parent::factory($id, $options);
        if (!($instance instanceof crmImSource)) {
            throw new crmSourceException(sprintf("Can't factory im source '%s'", $id));
        }
        return $instance;
    }

    public function createMessage($message = array(), $direction = crmMessageModel::DIRECTION_IN)
    {
        $message_id = parent::createMessage($message, $direction);
        $this->addMessageToConversation($message_id);
        return $message_id;
    }

    public function handleMessageEdit($message, $update_ts = null)
    {
        if (empty($update_ts)) {
            $update_ts = time();
        } else {
            $prev_update_ts_row = self::getMessageParamsModel()->getByField(['message_id' => $message['id'], 'name' => 'edit_ts']);
            $prev_update_ts = ifset($update_ts_row, 'value', 0);
            if ($prev_update_ts >= $update_ts) {
                return;
            }
        }
        if (!crmMessageModel::isColumnMb4('body')) {
            $message['body'] = crmHelper::removeEmoji($message['body']);
        }
        self::getMessageModel()->updateById($message['id'], ['body' => $message['body']]);

        if (isset($message['params']) && is_array($message['params'])) {
            if (!crmMessageParamsModel::isColumnMb4('value')) {
                $message['params'] = array_map(function ($value) { 
                    if (is_string($value)) {
                        $value = crmHelper::removeEmoji($value);
                    }
                    return $value;
                }, $message['params']);
            }
            self::getMessageParamsModel()->set($message['id'], $message['params']);
        }

        // attach files to message
        self::getMessageModel()->deleteAttachments($message['id']);
        if (isset($message['attachments'])) {
            $file_ids = array_unique($message['attachments']);
            self::getMessageModel()->setAttachments($message['id'], $file_ids);
        }

        self::getMessageParamsModel()->replace(['message_id' => $message['id'], 'name' => 'edit_ts', 'value' => $update_ts]);
        
        if (empty($message['conversation_id'])) {
            return;
        }

        $res = self::getMessageModel()->getExtMessages([$message], $this);
        $message = $res ? reset($res) : $message;
        $updated_summary = null;
        $conversation_last_incoming_message = self::getMessageModel()->getConversationLastIncomingMessage($message['conversation_id']);
        if (ifset($conversation_last_incoming_message['id']) == $message['id']) {
            $updated_summary = $this->prepareConversationSummaryFromMessage($message);
            self::getConversationModel()->updateById($message['conversation_id'], ['summary' => $updated_summary]);
        }

        if (!class_exists('waServicesApi')) {
            return;
        }
        $servicesApi = new waServicesApi();
        if ($servicesApi->isConnected()) {
            $source_helper = crmSourceHelper::factory($this);
            $res = $source_helper->normalazeMessagesExtras([$message]);
            $message = $res ? reset($res) : $message;
            $view = wa()->getView();
            $view->assign('message', $message);
            if (!empty(ifset($message, 'extras', 'locations', null))) {
                try {
                    $adapter = wa()->getSetting('backend_map_adapter', 'google', 'webasyst');
                    if ($adapter !== 'disabled') {
                        $view->assign('map', wa()->getMap($adapter));
                    }
                } catch (waException $ex) {}
            }
            try {
                $servicesApi->sendWebsocketMessage(['message_edit' => [
                    'message_id' => $message['id'],
                    'message' => $message,
                    'summary_html' => crmHelper::renderSummary($updated_summary),
                    'message_html' => $view->fetch(wa()->getAppPath('templates/actions/message/MessageBody.inc.html', 'crm')),
                    'edit_datetime' => date('Y-m-d H:i:s', $update_ts),
                ]], 'conversation-'.$message['conversation_id']);
            } catch (Throwable $e) {
                //waLog::log($e->getMessage()."\n".$e->getTraceAsString(), 'error.log');
            }
        }
    }

    public function handleMessageStatus($message_id, $status, $error = null)
    {
        $message_params_model = self::getMessageParamsModel();
        $message_status_record = $message_params_model->getByField([
            'message_id' => $message_id,
            'name' => 'status',
        ]);
        $prev_status = ifset($message_status_record, 'value', self::STATUS_NO);
        if (ifset(self::$message_statuses[$prev_status], 0) >= ifset(self::$message_statuses[$status], 0)) {
            return;
        }
        $message_params_model->replace([
            'message_id' => $message_id,
            'name' => 'status',
            'value' => $status,
        ]);
        if ($status == 'failed') {
            $message_params_model->replace([
                'message_id' => $message_id,
                'name' => 'error_code',
                'value' => 'not_delivered',
            ]);
            $error = _w('The message was not sent.') . (empty($error) ? '' : "\n{$error}");
        }
        if (!empty($error)) {
            $message_params_model->replace([
                'message_id' => $message_id,
                'name' => 'error_details',
                'value' => $error,
            ]);
        }

        if (!class_exists('waServicesApi')) {
            return;
        }
        $servicesApi = new waServicesApi();
        if ($servicesApi->isConnected()) {
            $message = (new crmMessageModel)->getById($message_id);
            if (!empty($message) && !empty($message['conversation_id'])) {
                try {
                    $servicesApi->sendWebsocketMessage(['new_message_status' => [
                        'message_id' => $message_id,
                        'status' => $status,
                        'error' => $error,
                    ]], 'conversation-'.$message['conversation_id']);
                } catch (Throwable $e) {
                    //waLog::log($e->getMessage()."\n".$e->getTraceAsString(), 'error.log');
                }
            }
        }
    } 

    protected function addMessageToConversation($message_id)
    {
        $message = $this->getMessageModel()->getMessage($message_id);
        if (!$message) {
            return;
        }
        if (!isset($message['source_id'])) {
            return;
        }

        $update = array();
        $conversation = $this->findConversation($message['contact_id']);
        if (!$conversation) {
            $conversation = $this->createConversation($message);
        } else {
            $update = $this->prepareConversationBeforeUpdate($message, $conversation['id']);
        }

        $mm = new crmMessageModel();
        $mm->addToConversation($message, $conversation['id'], $update);

        $message['conversation_id'] = $conversation['id'];
        (new crmPushService)->notifyAboutMessage(null, $message, $conversation);

        if (!class_exists('waServicesApi')) {
            return;
        }
        $servicesApi = new waServicesApi();
        if ($servicesApi->isConnected()) {
            try {
                $servicesApi->sendWebsocketMessage(['new_message' => $message], 'conversation-'.$conversation['id']);
            } catch (Throwable $e) {
                //waLog::log($e->getMessage()."\n".$e->getTraceAsString(), 'error.log');
            }
        }
    }

    /**
     * Create new conversation by message
     * @param $message
     * @return array|null
     */
    protected function createConversation($message)
    {
        $data = array_merge(
            $this->prepareConversationBeforeCreate($message),
            [
                'source_id' => $this->getId(),
                'deal_id' => ifset($message['deal_id']) > 0 ? $message['deal_id'] : null,
                'contact_id' => $message['contact_id']
            ]
        );
        if ($message['direction'] == crmMessageModel::DIRECTION_IN) {
            $cm = new waContactModel();
            $crm_user_id = $cm->select('crm_user_id')->where('id = ?', $message['contact_id'])->fetchField();
            if ($crm_user_id > 0) {
                $data['user_contact_id'] = $crm_user_id;
            } else {
                $normalized_responsible_contact_id = $this->getNormalizedResponsibleContactId();
                $data['user_contact_id'] = $normalized_responsible_contact_id > 0 ? $normalized_responsible_contact_id : null;

            }
        }
        $id = $this->getConversationModel()->add($data, crmConversationModel::TYPE_IM);
        return $this->getConversationModel()->getConversation($id);
    }

    protected function prepareConversationBeforeCreate($message)
    {
        return [ 'summary' => $this->prepareConversationSummaryFromMessage($message) ];
    }

    protected function prepareConversationBeforeUpdate($message, $message_id = null)
    {
        $direction = !empty($message['direction']) ? $message['direction'] : null;
        if ($direction !== crmMessageModel::DIRECTION_IN) {
            return [];
        }

        return [ 'summary' => $this->prepareConversationSummaryFromMessage($message) ];
    }

    protected function prepareConversationSummaryFromMessage($message)
    {
        $summary = '';
        $source_helper = crmSourceHelper::factory($this);
        $res = $source_helper->normalazeMessagesExtras([$message]);
        if (ifset($res[0]['extras']['images'])) {
            $summary = '[image]';
        } elseif (ifset($res[0]['extras']['videos'])) {
            $summary = '[video]';
        } elseif (ifset($res[0]['extras']['audios'])) {
            $summary = '[audio]';
        } elseif (ifset($res[0]['extras']['stickers'])) {
            $summary = '[sticker]';
        } elseif (ifset($res[0]['extras']['locations'])) {
            $summary = '[geolocation]';
        } elseif (!empty($message['attachments'])) {
            $summary = '[file]';
        }

        $is_emoji_copatible = crmConversationModel::isColumnMb4('summary');
        $body = (string)ifset($message['body'], '');
        if (!$this->isEmptyString($body)) {
            if (!$is_emoji_copatible) {
                $body = crmHelper::removeEmoji($body);
            }
            $features = $source_helper->getFeatures();
            if (ifset($features['html'])) {
                $body = strip_tags($body);
            }
            $body = trim($body);
            return $this->isEmptyString($summary) ? $body : $summary . ' ' . $body;
        }

        $caption = (string)ifset($res[0]['caption'], '');
        if (!$this->isEmptyString($caption)) {
            if (!$is_emoji_copatible) {
                $caption = crmHelper::removeEmoji($caption);
            }
            $caption = trim(strip_tags($caption));
            return $this->isEmptyString($summary) ? $caption : $summary . ' ' . $caption;
        }
        if (!empty($message['attachments'])) {
            $file = reset($message['attachments']);
            if (!empty($file['name'])) {
                return empty($summary) ? $file['name'] : $summary . ' ' . $file['name'];
            }
        }
        if ($error_code = ifset($message['params']['error_code'])) {
            if ($error_code == 'unsupported') {
                $summary = '[unsupported]';
            } else {
                $summary = '[error]';
            }
        }
        if ($this->isEmptyString($summary)) {
            $summary = '[empty]';
        }

        return $summary;
    }

    protected function isEmptyString($string) {
        return $string === null || $string === '';
    }

    /**
     * Find opened conversation by this source and this contact
     * @param int $contact_id
     * @return array|null
     * @throws waException
     */
    public function findConversation($contact_id)
    {
        return $this->getConversationModel()->getByField(array(
            'contact_id' => $contact_id,
            'source_id'  => $this->getId(),
            'is_closed'  => 0,
        ));
    }

    protected function prepareDealBeforeCreate($deal = array())
    {
        $deal = parent::prepareDealBeforeCreate($deal);
        if (!$deal) {
            return $deal;
        }
        $create_contact_id = (int)ifset($deal['create_contact_id']);
        if ($create_contact_id <= 0) {
            $create_contact_id = (int)ifset($deal['contact_id']);
        }
        $deal['create_contact_id'] = $deal['contact_id'] = $create_contact_id;
        return $deal;
    }

    protected function prepareMessageBeforeCreate($default = array(), $direction = crmMessageModel::DIRECTION_IN)
    {
        $message = array_merge($default, array(
            'transport' => crmMessageModel::TRANSPORT_IM,
            'direction' => $direction,
            'source_id' => $this->getId()
        ));
        return $message;
    }

    /**
     * @return int
     */
    public function getNormalizedResponsibleContactId()
    {
        $contact_id = null;
        $responsible_contact_id = $this->getResponsibleContactId();
        if ($responsible_contact_id > 0) {
            $contact_id = $responsible_contact_id;
        }


        if ($responsible_contact_id < 0) {
            $responsible_user_id = $this->getConversationModel()->getResponsibleUserOfGroup(-$responsible_contact_id);
            if ($responsible_user_id > 0) {
                $contact_id = $responsible_user_id;
            }
            if (!$contact_id) {
                $contact_id = $this->getDealModel()->getResponsibleUserOfGroup(-$responsible_contact_id);
            }
        }
        return (int)$contact_id;
    }

    /**
     * Can source init new conversation
     * @return bool
     */
    public function canInitConversation()
    {
        return false;
    }

    /**
     * Render link (html code) to init new conversation with given contact
     * @param string|int|array|waContact $contact
     * @return string
     */
    public function renderInitConversationLink($contact)
    {
        return null;
    }

}
