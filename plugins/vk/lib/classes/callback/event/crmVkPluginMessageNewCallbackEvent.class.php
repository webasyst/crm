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

    public function __construct(array $event, crmVkPluginImSource $source, array $options = array())
    {
        parent::__construct($event, $source, $options);
        $object = (array)ifset($this->event['object']);

        $user_id = $object['user_id'];
        $this->vk_user = new crmVkPluginVkUser($user_id, $this->source->getApiParams());

        $group_id = $this->source->getGroupId();
        $this->vk_group = new crmVkPluginVkGroup($group_id, $this->source->getApiParams());

        $principal_participant = crmVkPluginChatParticipant::tieWith($this->vk_group);
        $participant = crmVkPluginChatParticipant::tieWith($this->vk_user);
        $this->chat = crmVkPluginChat::tieWith($principal_participant, $participant);
    }

    /**
     * @return string
     */
    public function execute()
    {
        if ($this->source->isDisabled()) {
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
            $vk_message = new crmVkPluginVkMessage($object['id'], array(
                'access_token' => $this->source->getAccessToken()
            ));
        } else {
            $vk_message = new crmVkPluginVkMessage($object);
        }
        $message = $this->chat->prepareMessageData(crmMessageModel::DIRECTION_IN, $vk_message);
        $message['deal_id'] = (int)$deal_id;
        return $message;
    }

    protected function needLoadMessage($object)
    {
        $attachments = isset($object['attachments']) && is_array($object['attachments']) ? $object['attachments'] : array();
        foreach ($attachments as $attachment) {
            if ($attachment['type'] === 'link') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int|null $deal_id
     * @return bool|int|resource
     */
    protected function createMessage($deal_id = null)
    {
        $message = $this->prepareMessage($deal_id);
        $message_id = $this->source->createMessage($message);
        $this->chat->addMessage($message_id);
        return $message_id;
    }
}
