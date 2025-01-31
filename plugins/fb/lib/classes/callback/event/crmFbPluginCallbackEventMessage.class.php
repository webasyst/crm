<?php

class crmFbPluginCallbackEventMessage extends crmFbPluginCallbackEvent
{
    /** @var array */
    protected $entry;

    /** @var array */
    protected $object_type;

    /**
     * @var bool
     */
    protected $is_new_contact;

    public function __construct(array $event, crmFbPluginImSource $source, $options = array())
    {
        parent::__construct($event, $source, $options);
        $this->entry = !empty($this->event['entry']) ? $this->event['entry'] : array();
    }

    public function execute()
    {
        if (empty($this->entry) || $this->source->isDisabled()) {
            return;
        }

        foreach ($this->entry as $entry) {
            if (empty($entry['messaging'])) {
                continue;
            }

            foreach ($entry['messaging'] as $messaging) {
                $message = new crmFbPluginEntryMessage($messaging);
                $this->createMessage($message);
            }
        }
    }

    protected function createMessage(crmFbPluginEntryMessage $message)
    {
        $data = $this->prepareMessage($message);
        $message_id = $this->source->createMessage($data);
        return $message_id;
    }

    protected function prepareMessage(crmFbPluginEntryMessage $message)
    {
        $marker_token = $this->source->getMarkerToken();
        $sender = new crmFbPluginFbUser($message->getSenderId(), $marker_token);
        try {
            $sender_info = $sender->getInfo();
        } catch (waException $e) {
            crmFbPlugin::sendError($e->getMessage());
            return;
        }
        if (!$sender_info) {
            crmFbPlugin::sendError('Empty sender info');
            return;
        }
        try {
            $contact = $this->findContact($sender_info);
        } catch (waException $e) {
            crmFbPlugin::sendError($e->getMessage());
            return;
        }
        if (!$contact) {
            crmFbPlugin::sendError(_w('Contact not found'));
            return;
        }
        // Ignore blocked users
        if ($contact['is_user'] == -1) {
            return;
        }

        if ($this->is_new_contact) {

            // add contacts to segments
            $this->source->addContactsToSegments($contact->getId());

            // set local
            $locale = $this->source->getParam('locale');
            if ($locale) {
                $contact->save(array('locale' => $locale));
            }
        }

        $deal_id = $this->findDeal($message, $contact);
        $data = $this->prepareMessageData($message, $contact, $deal_id);
        return $data;
    }

    protected function prepareMessageData(crmFbPluginEntryMessage $message, crmContact $contact, $deal_id)
    {
        if (!($message instanceof crmFbPluginEntryMessage)) {
            $message = null;
        }

        $data = array(
            'creator_contact_id' => $contact->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $contact->getId(),
            'deal_id'            => $deal_id,
            'subject'            => '',
            'body'               => $message->getText(),
            'from'               => $contact->getName(),
            'to'                 => $this->source->getId(),
            'params'             => array(
                'fb_message_id' => $message->getMessageId(),
                'fb_contact_id' => $contact->get('fb_source_id'),
            )
        );

        // Save message attachments
        if ($message->getAttachments()) {
            $message_attachments = $message->getAttachments();
            $attachments = $fb_attachments = array();
            $downloader = new crmFbPluginDownloader($contact->getId(), $deal_id, $contact->getId());
            foreach ($message_attachments as $type => $files) {
                foreach ($files as $file_url) {
                    $crm_file_id = $downloader->downloadFile($file_url);
                    if ($type !== 'file') {
                        $fb_attachments[$type][] = $crm_file_id;
                    } else {
                        $attachments[] = $crm_file_id;
                    }
                }
            }

            if (!empty($attachments)) {
                $data['attachments'] = $attachments;
            }

            // Inline Facebook attachments
            if (!empty($fb_attachments)) {
                $data['params']['attachments'] = json_encode($fb_attachments);
            }
        }

        return $data;
    }

    /**
     * @param array $fb_user
     * @return crmContact
     * @throws waException
     */
    protected function findContact($fb_user)
    {
        $this->is_new_contact = false;
        $contact = $this->findContactByFbIds($fb_user);
        if (!$contact) {
            $contact = $this->exportContact($fb_user);
        }
        return $contact;
    }

    /**
     * @param array $fb_user
     * @return crmContact|null
     */
    protected function findContactByFbIds($fb_user)
    {
        $searcher = new crmFbPluginContactSearcher($fb_user);
        return $searcher->findByFbId();
    }

    /**
     * @param array $fb_user
     * @return crmContact
     * @throws waException
     */
    protected function exportContact($fb_user)
    {
        $responsible_contact_id = $this->source->getNormalizedResponsibleContactId();
        $options = [];
        if ($responsible_contact_id > 0) {
            $options['crm_user_id'] = $responsible_contact_id;
        }
        $exporter = new crmFbPluginContactExporter($fb_user, $options);
        $contact = $exporter->export();
        $this->is_new_contact = true;
        return $contact;
    }

    protected function findDeal(crmFbPluginEntryMessage $message, crmContact $contact)
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

    protected function createDeal(crmFbPluginEntryMessage $message, crmContact $contact)
    {
        $description = $message->getText();
        $deal = array(
            'name'               => $contact->getName(),
            'contact_id'         => $contact->getId(),
            'creator_contact_id' => $contact->getId(),
            'description'        => $description ? $description : null,
        );
        return $this->source->createDeal($deal);
    }
}
