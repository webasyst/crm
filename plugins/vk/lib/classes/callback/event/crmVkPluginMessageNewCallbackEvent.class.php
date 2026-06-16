<?php

class crmVkPluginMessageNewCallbackEvent extends crmVkPluginCallbackEvent
{
    /**
     * @var crmVkPluginVkUser
     */
    protected $vk_user;

    /**
     * @var crmVkPluginVkGroup
     */
    protected $vk_group;

    /**
     * @var crmVkPluginChat
     */
    protected $chat;

    protected $direction;

    public function __construct(array $event, crmVkPluginImSource $source, array $options = array())
    {
        parent::__construct($event, $source, $options);
        if (!empty($this->event['object']['message'])) {
            $this->event['object'] = $this->event['object']['message'];
        }
        $object = (array)ifset($this->event['object']);

        $user_id = (empty($object['user_id']) ? (empty($object['out']) ? $object['from_id'] : $object['peer_id']) : $object['user_id']);
        $this->vk_user = new crmVkPluginVkUser($user_id, $this->source->getApiParams());

        $group_id = $this->source->getGroupId();
        $this->vk_group = new crmVkPluginVkGroup($group_id, $this->source->getApiParams());

        $principal_participant = crmVkPluginChatParticipant::tieWith($this->vk_group);
        $participant = crmVkPluginChatParticipant::tieWith($this->vk_user);
        $this->chat = crmVkPluginChat::tieWith($principal_participant, $participant);

        $this->direction = empty($object['out']) ? crmMessageModel::DIRECTION_IN : crmMessageModel::DIRECTION_OUT;
    }

    /**
     * @return string
     */
    public function execute()
    {
        if (!$this->checkMessage()) {
            // Do nothing
            return 'ok';
        }

        $participant = $this->chat->getParticipant();

        $contact = $participant->getContact();
        if ($contact->get('is_user') == -1) {
            // nothing to do, contact is banned
            return 'ok';
        }

        // contact is new - just exported from VK
        if ($participant->isContactJustExported()) {

            // add to segments
            $this->source->addContactsToSegments($contact->getId());

            // set locale
            $locale = $this->source->getParam('locale');
            if ($locale) {
                $contact->save(array('locale' => $locale));
            }

        }

        $deal_id = null;
        if ($this->source->getParam('create_deal')) {
            $deal_id = $this->processDeal($contact->getId());
        }

        $responsible_contact_id = $this->source->getNormalizedResponsibleContactId();
        if ($responsible_contact_id > 0 && $participant->isContactJustExported()) {
            $contact->save(array(
                'crm_user_id' => $responsible_contact_id
            ));
        }

        $this->createMessage($deal_id);

        return 'ok';
    }

    protected function checkMessage()
    {
        if ($this->source->isDisabled()) {
            return false;
        }

        $message = $this->source->findMessage($this->event['object']['id']);
        if (!empty($message)) {
            // Message was already processed (this is doubled callback)
            return false;
        }

        return true;
    }

    protected function processDeal($contact_id)
    {
        // Find opened conversation by this source and this contact
        $conversation = $this->source->findConversation($contact_id);
        if ($conversation) {
            return $conversation['deal_id'];
        }

        // If conversation not found it would be created in createMessage step and by that time we need find deal for this new message

        $dm = new crmDealModel();

        $deal_id = $this->chat->getDealId();

        $deal = null;
        if ($deal_id > 0) {
            $deal = $dm->getByField(array(
                'id' => $deal_id,
                'status_id' => crmDealModel::STATUS_OPEN
            ));
        }
        if (!$deal) {
            $deal_id = $this->createDeal();
            $this->chat->save(array(
                'deal_id' => $deal_id > 0 ? $deal_id : null
            ));
        }

        return $deal_id;
    }

    /**
     * @return bool|int
     */
    protected function createDeal()
    {
        $object = (array)ifset($this->event['object']);
        $name = (string)ifset($object['title']);
        if (strlen($name) <= 0) {
            $name = sprintf(
                _wp("Deal between %s and %s"),
                $this->chat->getPrincipalParticipant()->getContact()->getName(),
                $this->chat->getParticipant()->getContact()->getName()
            );
        }
        $deal = array(
            'name' => $name,
            'contact_id' => $this->chat->getParticipant()->getContactId(),
            'creator_contact_id' => $this->chat->getParticipant()->getContactId(),
            'description' => $object['body']
        );
        return $this->source->createDeal($deal);
    }

    /**
     * @param int|null $deal_id
     * @return array
     */
    protected function prepareMessage($deal_id = null)
    {
        $object = (array)ifset($this->event['object']);
        if ($this->needLoadMessage($object)) {
            $loaded_object = $this->loadVkMessage($object);
            if ($loaded_object) {
                $object = $loaded_object;
            }
        }
        $object = $this->hydrateVkMessagesContainer($object);
        $vk_message = new crmVkPluginVkMessage($object);
        $message = $this->chat->prepareMessageData($this->direction, $vk_message);
        $message['deal_id'] = (int)$deal_id;
        return $message;
    }

    protected function needLoadMessage($object)
    {
        if (!empty($object['is_cropped'])) {
            return true;
        }

        $attachments = isset($object['attachments']) && is_array($object['attachments']) ? $object['attachments'] : array();
        foreach ($attachments as $attachment) {
            if ($attachment['type'] === 'link') {
                return true;
            }
        }
        return false;
    }

    protected function hydrateVkMessagesContainer($container)
    {
        if (!is_array($container)) {
            return $container;
        }

        if (isset($container['reply_message']) && is_array($container['reply_message'])) {
            $container['reply_message'] = $this->hydrateVkMessage($container['reply_message']);
            $container['reply_message'] = $this->stripVkMessageAttachments($container['reply_message']);
        }

        if (isset($container['fwd_messages']) && is_array($container['fwd_messages'])) {
            foreach ($container['fwd_messages'] as &$fwd_message) {
                if (is_array($fwd_message)) {
                    $fwd_message = $this->hydrateVkMessage($fwd_message);
                }
            }
            unset($fwd_message);
        }

        return $container;
    }

    protected function stripVkMessageAttachments($message)
    {
        if (!is_array($message)) {
            return $message;
        }

        unset($message['attachments']);
        if (isset($message['reply_message']) && is_array($message['reply_message'])) {
            $message['reply_message'] = $this->stripVkMessageAttachments($message['reply_message']);
        }

        return $message;
    }

    protected function hydrateVkMessage($message)
    {
        if (!is_array($message)) {
            return $message;
        }

        if (!empty($message['is_cropped'])) {
            $loaded_message = $this->loadVkMessageByConversationMessageId($message);
            if ($loaded_message) {
                $message = array_merge($message, $loaded_message);
            }
        }

        return $this->hydrateVkMessagesContainer($message);
    }

    protected function loadVkMessageByConversationMessageId($message)
    {
        static $cache = [];

        $peer_id = (int)ifset($message, 'peer_id', 0);
        $conversation_message_id = (int)ifset($message, 'conversation_message_id', 0);
        if (!$peer_id || $conversation_message_id <= 0) {
            return null;
        }

        $cache_key = $peer_id . ':' . $conversation_message_id;
        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        try {
            $api = new crmVkPluginApi($this->source->getAccessToken());
            $messages = $api->getMessagesByConversationMessageIds($peer_id, array($conversation_message_id));
            $cache[$cache_key] = $messages ? reset($messages) : null;
        } catch (Exception $e) {
            $cache[$cache_key] = null;
        }

        return $cache[$cache_key];
    }

    protected function loadVkMessage($message)
    {
        $message_id = (int)ifset($message, 'id', 0);
        if ($message_id > 0) {
            return $this->loadVkMessageById($message_id);
        }

        return $this->loadVkMessageByConversationMessageId($message);
    }

    protected function loadVkMessageById($message_id)
    {
        try {
            $api = new crmVkPluginApi($this->source->getAccessToken());
            return $api->getMessage($message_id);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param int|null $deal_id
     * @return bool|int|resource
     */
    protected function createMessage($deal_id = null)
    {
        $message = $this->prepareMessage($deal_id);
        $message_id = $this->source->createMessage($message, $this->direction);
        $this->chat->addMessage($message_id);
        return $message_id;
    }
}
