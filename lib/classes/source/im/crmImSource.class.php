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
            return null;
        }
        if (!isset($message['source_id'])) {
            return null;
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
    }

    /**
     * Create new conversation by message
     * @param $message
     * @return array|null
     */
    protected function createConversation($message)
    {
        $data = array_merge(
            $this->prepareConversationBeforeCreate($message),array(
                'source_id' => $this->getId(),
                'deal_id' => ifset($message['deal_id']) > 0 ? $message['deal_id'] : null,
                'contact_id' => $message['contact_id']
            )
        );
        if ($message['direction'] == crmMessageModel::DIRECTION_IN) {
            $normalized_responsible_contact_id = $this->getNormalizedResponsibleContactId();
            $data['user_contact_id'] = $normalized_responsible_contact_id > 0 ? $normalized_responsible_contact_id : null;
            if (!$data['user_contact_id'] && $message['contact_id']) {
                $cm = new waContactModel();
                $crm_user_id = $cm->select('crm_user_id')->where('id = ?', $message['contact_id'])->fetchField();
                if ($crm_user_id > 0) {
                    $data['user_contact_id'] = $crm_user_id;
                }
            }
        }
        $id = $this->getConversationModel()->add($data, crmConversationModel::TYPE_IM);
        return $this->getConversationModel()->getConversation($id);
    }

    protected function prepareConversationBeforeCreate($message)
    {
        $summary = (string)ifset($message['body']);
        if (!crmConversationModel::isColumnMb4('summary')) {
            $summary = crmHelper::removeEmoji($summary);
        }

        $summary = trim(strip_tags($summary));

        $result = array();
        if (strlen($summary) > 0) {
            $result['summary'] = strip_tags($summary);
        }

        return $result;
    }

    protected function prepareConversationBeforeUpdate($message, $message_id = null)
    {
        $direction = !empty($message['direction']) ? $message['direction'] : null;
        if ($direction !== crmMessageModel::DIRECTION_IN) {
            return array();
        }

        $summary = (string)ifset($message['body']);
        if (!crmConversationModel::isColumnMb4('summary')) {
            $summary = crmHelper::removeEmoji($summary);
        }
        $summary = trim(strip_tags($summary));

        $result = array();
        if (strlen($summary) > 0) {
            $result['summary'] = strip_tags($summary);
        }

        return $result;
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
}
