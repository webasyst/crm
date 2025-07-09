<?php

class crmEmailSourceWorkerOutcomingStrategy extends crmEmailSourceWorkerStrategy
{
    /**
     * @var crmContact
     */
    protected $user;

    public function process($params = array())
    {
        $customer = $this->findCustomer();
        if (!$customer) {
            return false;
        }
        $result = $this->processForDeal($customer);
        if ($result) {
            $mm = new crmMessageModel();
            $message = $mm->getMessage($result['message_id']);
            $this->doConversationProcessPart($customer, $message, $result['deal']);
        }
        return $result;
    }

    protected function doConversationProcessPart(crmContact $customer, $message, $deal)
    {
        $conversation = $this->findConversationByDeal($deal);

        if ($conversation) {
            $conversation_id = $conversation['id'];
        } else {
            $conversation_id = $this->createConversation($customer, $message, $deal);
        }
        $mm = new crmMessageModel();
        $mm->addToConversation($message, $conversation_id);

        /*
        if ($conversation) {
            // Если переписка не только что создана, отправляем пуши
            $message['conversation_id'] = $conversation_id;
            (new crmPushService)->notifyAboutMessage($customer, $message, $conversation, $deal);
        } */
    }

    /**
     * @return crmContact|null
     */
    protected function getUser()
    {
        if ($this->user !== null) {
            return $this->user ? $this->user : null;
        }

        $user_email = $this->mail->getReplyEmail();
        if (!$user_email) {
            $this->user = false;
            return null;
        }

        $user = self::findContactByEmail($user_email);
        if (!$user || $user['is_user'] < 1) {
            $this->user = false;
            return null;
        }

        return $this->user = $user;
    }

    protected function processForDeal(crmContact $customer)
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        $deal = self::findDealByMagicEmail($this->source, $this->mail);
        if (!$deal) {
            return false;
        }

        $participant_ids = waUtils::getFieldValues($deal['participants'], 'contact_id');
        if (!in_array($customer->getId(), $participant_ids)) {
            self::getDealModel()->addParticipants($deal['id'], $customer->getId(), crmDealParticipantsModel::ROLE_CLIENT);
            $deal = self::getDealModel()->getDeal($deal['id'], true);
        }

        $attachments = $this->attachFilesToDeal($deal, $this->mail->getAttachments());
        $body = $this->prepareMailBodyToCommit($this->mail->getBody(), $attachments);
        $recipients = $this->prepareMailRecipientsToCommit($this->mail->getRecipients());

        $message_id = $this->saveMail($user, $customer, $deal,
            array(
                'body' => $body,
                'attachments' => $attachments,
                'recipients' => $recipients,
                'to' => $customer->getProperty('email')
            )
        );

        return array(
            'message_id' => $message_id,
            'deal_id' => $deal['id'],
            'deal' => $deal,
            'user_id' => $user->getId(),
            'customer_id' => $customer->getId(),
        );
    }

    protected function findCustomer()
    {
        $source_email = $this->source->getEmail();

        $recipients = array();
        foreach ($this->mail->getDirectRecipients() as $recipient) {
            if ($recipient['clean_email'] !== $source_email) {
                $recipients[] = $recipient;
            }
        }

        $copies = array();
        foreach ($this->mail->getCopies($this->mail) as $copy) {
            if ($copy['clean_email'] !== $source_email) {
                $copies[] = $copy;
            }
        }

        $contact = null;
        foreach (array($recipients, $copies) as $_recipients) {
            foreach (waUtils::getFieldValues($_recipients, 'clean_email') as $email) {
                $contact = $this->findContactByHash('search/email='.$email);
                if ($contact) {
                    $contact->setProperty('email', $email);
                    break 2;
                }
            }
        }
        if ($contact) {
            return $contact;
        }

        // contact (customer), not found, try create from first recipient or copies emails
        $email = '';
        $name = '';
        foreach (array($recipients, $copies) as $_recipients) {
            $recipient = reset($_recipients);
            $email = $recipient['clean_email'];
            $name = $recipient['name'];
            if ($email) {
                break;
            }
        }
        if (!$email) {
            return null;
        }
        $contact = $this->createContact($email, $name);
        $contact->setProperty('email', $email);
        return $contact;
    }
}
