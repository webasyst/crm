<?php

class crmVkPluginChat extends crmVkPluginChatEntity
{
    /**
     * @var crmVkPluginChatParticipant
     */
    protected $principal_participant;

    /**
     * @var crmVkPluginChatParticipant
     */
    protected $participant;

    public static function factory($id)
    {
        return new self($id);
    }

    public static function tieWith($principal_participant, $participant)
    {
        $field = array();

        if (wa_is_int($principal_participant)) {
            $field['principal_participant_id'] = $principal_participant;
        } elseif ($principal_participant instanceof crmVkPluginChatParticipant) {
            $field['principal_participant_id'] = $principal_participant->getId();
        }

        if (wa_is_int($participant)) {
            $field['participant_id'] = $participant;
        } elseif ($participant instanceof crmVkPluginChatParticipant) {
            $field['participant_id'] = $participant->getId();
        }

        if (count($field) < 2) {
            throw new crmVkPluginException("Invalid argument");
        }

        $instance = self::factoryByField($field);
        if ($instance->exists()) {
            return $instance;
        }

        $data = $field;
        $instance->save($data);

        if ($participant instanceof crmVkPluginChatParticipant) {
            $instance->getInfo();
            $instance->participant = $participant;
        }

        return $instance;
    }

    protected static function factoryByField($field)
    {
        $info = self::getChatModel()->getByField($field);
        if (!$info) {
            return self::factory(0);
        }
        $instance = self::factory($info['id']);
        $instance->info = $info;
        return $instance;
    }

    public function addMessage($message_id)
    {
        if (!$this->exists()) {
            $info = $this->getInfo();
            $this->save($info, true);
        }
        self::getChatMessagesModel()->insert(array(
            'chat_id' => $this->id,
            'message_id' => $message_id
        ));
    }

    public function save($data, $delete_old_params = false)
    {
        if ($this->info && $this->info['principal_participant_id'] && empty($data['principal_participant_id'])) {
            $data['principal_participant_id'] = $this->info['principal_participant_id'];
        }
        if ($this->info && $this->info['participant_id'] && empty($data['participant_id'])) {
            $data['participant_id'] = $this->info['participant_id'];
        }

        if (empty($data['name']) && $data['principal_participant_id'] && $data['participant_id']) {
            $principal_participant = crmVkPluginChatParticipant::factory($data['principal_participant_id']);
            $participant = crmVkPluginChatParticipant::factory($data['participant_id']);
            $name = _wp('Chat between %s and %s');
            $name = sprintf($name, $principal_participant->getContact()->getName(), $participant->getContact()->getName());
            $data['name'] = $name;
        }

        parent::save($data, $delete_old_params);
        $this->principal_participant = null;
        $this->participant = null;
    }

    /**
     * @return crmVkPluginChatParticipant
     */
    public function getPrincipalParticipant()
    {
        if ($this->principal_participant) {
            return $this->principal_participant;
        }
        $info = $this->getInfo();
        $this->principal_participant = crmVkPluginChatParticipant::factory($info['principal_participant_id']);
        return $this->principal_participant;
    }

    /**
     * @return crmVkPluginChatParticipant
     */
    public function getParticipant()
    {
        if ($this->participant) {
            return $this->participant;
        }
        $info = $this->getInfo();
        $this->participant = crmVkPluginChatParticipant::factory($info['participant_id']);
        return $this->participant;
    }

    public function getMessageIds()
    {
        if ($this->getId() <= 0) {
            return array();
        }
        $items = self::getChatMessagesModel()->getByField(array('chat_id' => $this->getId()), 'message_id');
        return array_keys($items);
    }

    public function getDealId()
    {
        $info = $this->getInfo();
        return $info['deal_id'];
    }

    /**
     * @param string $direction crmMessageModel::DIRECTION_*
     * @param crmVkPluginVkMessage $message|null
     * @return array
     */
    public function prepareMessageData($direction, $message)
    {
        $principal_participant = $this->getPrincipalParticipant();
        $participant = $this->getParticipant();

        if ($direction == crmMessageModel::DIRECTION_IN) {
            $to = $principal_participant->getDomain();
            $from = $participant->getDomain();
            $contact_id = $participant->getContactId();
            $creator_contact_id = $participant->getContactId();
        } else {
            $to = $participant->getDomain();
            $from = $principal_participant->getDomain();
            $contact_id = $participant->getContactId();
            $creator_contact_id = wa()->getUser()->getId();
            $direction = crmMessageModel::DIRECTION_OUT;
        }

        if (!($message instanceof crmVkPluginVkMessage)) {
            $message = null;
        }

        $data = array(
            'to' => $to,
            'from' => $from,
            'direction' => $direction,
            'creator_contact_id' => $creator_contact_id,
            'contact_id' => $contact_id,
            'subject' => $message ? $message->getTitle() : '',
            'body' => $message ? $message->getBody() : '',
            'params' => array(
                'id' => $message ? $message->getId() : '',
                'from_id' => $message ? $message->getFromId() : '',
                'has_emoji' => $message ? $message->hasEmoji() : false,
                'sticker' => $message ? $message->getSticker() : null,
                'datetime' => $message ? $message->getDatetime() : date('Y-m-d H:i:s'),
                'fwd_messages' => $message ? $message->getFwdMessages() : array(),
                'attachments' => $message ? $message->getAttachments() : array(),
                'important' => $message ? $message->isImportant() : false,
                'deleted' => $message ? $message->isDeleted() : false,
                'chat_id' => $this->getId(),
                'geo' => $message ? $message->getGeo() : null,
                'unread' => true
            )
        );
        return $data;
    }

    /**
     * @return crmVkPluginChatModel
     */
    protected function getEntityModel()
    {
        return self::getChatModel();
    }

    /**
     * @return crmVkPluginChatParamsModel
     */
    protected function getEntityParamsModel()
    {
        return self::getChatParamsModel();
    }

    /**
     * @return crmVkPluginChatModel
     */
    protected static function getChatModel()
    {
        return self::getModel('chat', 'crmVkPluginChatModel');
    }

    /**
     * @return crmVkPluginChatParamsModel
     */
    protected static function getChatParamsModel()
    {
        return self::getModel('chat_params', 'crmVkPluginChatParamsModel');
    }

    /**
     * @return crmVkPluginChatMessagesModel
     */
    protected function getChatMessagesModel()
    {
        return self::getModel('chat_messages', 'crmVkPluginChatMessagesModel');
    }

}
