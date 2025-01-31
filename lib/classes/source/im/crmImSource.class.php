<?php

abstract class crmImSource extends crmSource
{
    protected $type = crmSourceModel::TYPE_IM;

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
        if (!empty($body)) {
            if (!$is_emoji_copatible) {
                $body = crmHelper::removeEmoji($body);
            }
            $body = trim(strip_tags($body));
            return empty($summary) ? $body : $summary . ' ' . $body;
        }

        $caption = (string)ifset($res[0]['caption'], '');
        if (!empty($caption)) {
            if (!$is_emoji_copatible) {
                $caption = crmHelper::removeEmoji($caption);
            }
            $caption = trim(strip_tags($caption));
            return empty($summary) ? $caption : $summary . ' ' . $caption;
        }
        if (!empty($message['attachments'])) {
            $file = reset($message['attachments']);
            if (!empty($file['name'])) {
                return empty($summary) ? $file['name'] : $summary . ' ' . $file['name'];
            }
        }
        if (empty($summary)) {
            $summary = '[empty]';
        }

        return $summary;
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
