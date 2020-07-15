<?php

class crmTwitterPluginImSourceWorker extends crmImSourceWorker
{
    /**
     * @var crmTwitterPluginApi
     */
    protected $api;

    /**
     * @var crmContact
     */
    protected $contact;

    /**
     * @var bool
     */
    protected $is_new_contact = false;

    protected $deal_id;

    public function isWorkToDo(array $process = array())
    {
        $now = time();
        $lock_time = (int)$this->source->getParam('lock_time');
        if ($lock_time > $now) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param array $process
     * @throws waException
     */
    public function doWork(array $process = array())
    {
        $this->api = new crmTwitterPluginApi($this->source->getParams());
        $direct_messages = $this->api->getDirectMessages();
        // If we rest on the limit of requests to API
        if (isset($direct_messages['errors']) && $direct_messages['errors'][0]['code'] == 88) {
            $this->source->setParam('lock_time', time() + 15 * 60); // 15 minutes
            return false;
        }

        $this->processDirect($direct_messages);

        $mention_tweets = $this->api->getMentions(); // $this->tweets; //
        // If we rest on the limit of requests to API
        if (isset($mention_tweets['errors']) && $mention_tweets['errors'][0]['code'] == 88) {
            $this->source->setParam('lock_time', time() + 15 * 60); // 15 minutes
            return false;
        }
        $this->processMentions($mention_tweets);
    }

    /* DIRECT MESSAGE */

    /**
     * @param $direct_messages
     * @throws waException
     */
    protected function processDirect($direct_messages)
    {
        if (!isset($direct_messages['events']) || empty($direct_messages['events'])) {
            return;
        }

        $last_direct_id = $new_last_direct_id = $this->source->getParam('last_direct_id');

        $messages = $direct_messages['events'];

        $mpm = new crmMessageParamsModel();
        foreach ($messages as $message) {
            $this->contact = $this->is_new_contact = $this->deal_id = false;
            if ($message['type'] !== 'message_create' || $message['id'] <= $last_direct_id) {
                continue;
            }

            if ($message['id'] > $new_last_direct_id) {
                $new_last_direct_id = $message['id'];
            }

            if ($mpm->getByField(array('name' => 'twitter_message_id', 'value' => $message['id']))) {
                continue;
            }

            $this->createDirectMessage($message);
        }

        $this->source->setParam('last_direct_id', $new_last_direct_id);
    }

    /**
     * @param array $message
     * @return bool|int|resource
     * @throws waException
     */
    protected function createDirectMessage($message)
    {
        $message = new crmTwitterPluginDirectMessage((array)$message);
        $data = $this->prepareDirectMessage($message);
        $message_id = $this->source->createMessage($data, $data['direction']);
        return $message_id;
    }

    /**
     * @param crmTwitterPluginDirectMessage $message
     * @return array
     * @throws waException
     */
    protected function prepareDirectMessage($message)
    {
        if (!($message instanceof crmTwitterPluginDirectMessage)) {
            $message = null;
        }

        $direction = ($message->getSenderId() == $this->source->getParam('userid')) ? crmMessageModel::DIRECTION_OUT : crmMessageModel::DIRECTION_IN;
        $twitter_user_id = ($direction == crmMessageModel::DIRECTION_IN) ? $message->getSenderId() : $message->getRecipientId();

        $this->contact = $this->findContact($twitter_user_id);

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

        if ($direction == crmMessageModel::DIRECTION_IN) {
            $from = $this->contact->get('socialnetwork.twitter', 'default');
            $to = $this->source->getParam('username');
            $creator_contact_id = $this->contact->getId();
        } else {
            $from = $this->source->getParam('username');
            $to = $this->contact->get('socialnetwork.twitter', 'default');
            $creator_contact_id = 0;
        }

        $data = array(
            'creator_contact_id' => $creator_contact_id,
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'direction'          => $direction,
            'contact_id'         => $this->contact->getId(),
            'deal_id'            => $this->deal_id,
            'subject'            => '',
            'body'               => $message->getText(),
            'from'               => $from,
            'to'                 => $to,
            'params'             => array(
                'message_type'       => 'direct',
                'twitter_message_id' => $message->getId(),
                'twitter_user_id'    => $twitter_user_id,
            )
        );

        return $data;
    }

    /* MENTIONS */

    /**
     * @param $last_mentions
     * @throws waException
     */
    protected function processMentions($last_mentions)
    {
        if (empty($last_mentions) || !isset($last_mentions[0])) {
            return;
        }
        $last_mention_id = $new_last_mention_id = $this->source->getParam('last_mention_id');
        $mpm = new crmMessageParamsModel();
        foreach ($last_mentions as $mention) {
            $this->contact = $this->is_new_contact = $this->deal_id = false;
            if ($mention['id_str'] <= $last_mention_id) {
                continue;
            }
            if ($mention['id_str'] > $new_last_mention_id) {
                $new_last_mention_id = $mention['id_str'];
            }
            if ($this->source->getParam('mentions')) {
                if ($mpm->getByField(array('name' => 'twitter_tweet_id', 'value' => $mention['id_str']))) {
                    continue;
                }
                $this->createMention($mention);
            }
        }
        $this->source->setParam('last_mention_id', $new_last_mention_id);
    }

    /**
     * @param $mention
     * @return int
     * @throws waException
     */
    protected function createMention($mention)
    {
        $mention = new crmTwitterPluginMention((array)$mention);
        // Ignore your own tweets, ok?!
        if ($mention->getUser()->getId() == $this->source->getParam('userid')) {
            return false;
        }
        $data = $this->prepareMention($mention);
        $mention_id = $this->source->createMessage($data, $data['direction']);
        return $mention_id;
    }

    /**
     * @param $mention
     * @return array
     * @throws waException
     */
    protected function prepareMention($mention)
    {
        if (!($mention instanceof crmTwitterPluginMention)) {
            $message = null;
        }

        $twitter_user_id = $mention->getUser()->getId();

        $this->contact = $this->findContact($twitter_user_id);

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

        $data = array(
            'creator_contact_id' => $this->contact->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'direction'          => crmMessageModel::DIRECTION_IN,
            'contact_id'         => $this->contact->getId(),
            'deal_id'            => $this->deal_id,
            'subject'            => '',
            'body'               => $mention->getText(),
            'from'               => $this->contact->get('socialnetwork.twitter', 'default'),
            'to'                 => $this->source->getParam('username'),
            'params'             => array(
                'message_type'     => 'tweet',
                'twitter_tweet_id' => $mention->getId(),
                'twitter_user_id'  => $twitter_user_id,
            )
        );

        return $data;
    }

    // --- !!! --- //

    /**
     * @param int $twitter_user_id
     * @return crmContact|null
     * @throws waException
     */
    protected function findContact($twitter_user_id)
    {
        $this->is_new_contact = false;
        $twitter_user = $this->getTwitterUser($twitter_user_id);
        $contact = $this->findContactByTwitter($twitter_user);
        if (!$contact) {
            $contact = $this->exportContact($twitter_user);
        }
        return $contact;
    }

    /**
     * @param crmTwitterPluginUser $twitter_user
     * @return crmContact|null
     */
    protected function findContactByTwitter($twitter_user)
    {
        $searcher = new crmTwitterPluginContactSearcher($twitter_user);
        return $searcher->findByTwitterIds();
    }

    /**
     * @param crmTwitterPluginUser $twitter_user
     * @return crmContact
     * @throws waException
     */
    protected function exportContact($twitter_user)
    {
        $options = array(
            'crm_user_id' => $this->source->getNormalizedResponsibleContactId(),
        );
        $exporter = new crmTwitterPluginContactExporter($twitter_user, $options);
        $contact = $exporter->exportContact();
        $this->is_new_contact = true;
        return $contact;
    }

    /**
     * @param int $twitter_user_id
     * @return crmTwitterPluginUser
     */
    protected function getTwitterUser($twitter_user_id)
    {
        $contact_data = $this->api->getUser(array('user_id' => $twitter_user_id));
        return new crmTwitterPluginUser($contact_data);
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
        //$message_data = new crmTelegramPluginMessage((array)ifset($this->telegram_message));
        //$description = $message_data->getText() ? $message_data->getText() : $message_data->getCaption();

        $deal = array(
            'name'               => $this->contact->getName(),
            'contact_id'         => $this->contact->getId(),
            'creator_contact_id' => $this->contact->getId(),
            'description'        => 'yep yep', // $description ? $description : null,
        );

        return $this->source->createDeal($deal);
    }
}
