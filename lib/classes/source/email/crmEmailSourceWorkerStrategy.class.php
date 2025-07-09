<?php

abstract class crmEmailSourceWorkerStrategy
{
    /**
     * @var array
     */
    protected static $models;

    /**
     * @var array
     */
    protected $recipient;

    /**
     * @var crmEmailSource
     */
    protected $source;

    /**
     * @var crmMailMessage
     */
    protected $mail;

    /**
     * @var array
     */
    protected $options;

    protected function __construct(crmEmailSource $source, crmMailMessage $mail, $options = array())
    {
        $this->source = $source;
        $this->mail = $mail;
        $this->options = $options;
    }

    public static function factory(crmEmailSource $source, crmMailMessage $mail)
    {
        $recipient = self::getRecipient($source, $mail);

        if (ifset($recipient, 'full_suffix', '') === '+0') {
            $source->setEmailSuffixSupporting(crmEmailSource::EMAIL_SUFFIX_SUPPORTING_YES);
            return null;
        }

        $contact_email = $mail->getReplyEmail();
        if (!$contact_email) {
            return null;
        }

        $contact = self::findContactByEmail($contact_email);

        $is_user = $contact && $contact['is_user'] > 0;
        $recipients = $mail->getRecipients();
        if ($is_user && count($recipients) > 1) {
            $deal = self::findDealByMagicEmail($source, $mail);
            if ($deal) {
                return new crmEmailSourceWorkerOutcomingStrategy($source, $mail);
            }
        }

        return new crmEmailSourceWorkerIncomingStrategy($source, $mail);
    }

    abstract public function process($params = array());

    /**
     * @param string $hash
     * @return crmContact|null
     */
    protected static function findContactByHash($hash)
    {
        $col = new crmContactsCollection($hash);
        $contacts = $col->getContacts('*', 0, 1);
        if (!$contacts) {
            return null;
        }
        reset($contacts);
        $contact_id = key($contacts);
        return new crmContact($contact_id);

    }

    /**
     * @param $email
     * @return crmContact|null
     */
    protected static function findContactByEmail($email)
    {
        return self::findContactByHash('search/email=' . $email);
    }

    protected function prepareContactDataBeforeCreate($data = array())
    {
        $responsible_contact_id = $this->source->getNormalizedResponsibleContactId();
        if ($responsible_contact_id) {
            $data['crm_user_id'] = $responsible_contact_id;
        }
        $locale = $this->source->getParam('locale');
        if ($locale) {
            $data['locale'] = $locale;
        }
        $data['create_app_id'] = 'crm';
        $data['create_contact_id'] = 0;
        $data['create_method'] = 'source/email';
        if ($this->source->getProvider()) {
            $data['create_method'] .= '/' . $this->source->getProvider();
        }
        return $data;
    }

    protected function createContact($email, $name = '')
    {
        $name = trim($name);
        $data = $this->prepareContactDataBeforeCreate(array(
            'email' => $email,
            'name' => strlen($name) > 0 ? $name : $email
        ));
        $contact = new crmContact();
        $contact->save($data);
        return $contact;
    }

    /**
     * @param array|int $deal
     * @return array|null
     */
    protected function findConversationByDeal($deal)
    {
        $deal_id = null;
        if (wa_is_int($deal)) {
            $deal_id = $deal;
        } elseif (is_array($deal) && isset($deal['id'])) {
            $deal_id = $deal['id'];
        }
        if ($deal_id <= 0) {
            return null;
        }
        $cm = new crmConversationModel();
        return $cm->getByField(array(
            'deal_id' => $deal_id,
            'type' => crmConversationModel::TYPE_EMAIL,
            'is_closed' => 0
        ));
    }

    protected function createConversation(crmContact $contact, $message, $deal = null)
    {
        $summary = ifset($message, 'subject', '');
        $direction = ifset($message, 'direction', crmMessageModel::DIRECTION_IN);

        $crm_user_id = null;
        if ($direction == crmMessageModel::DIRECTION_IN) {
            $crm_user_id = $contact->get('crm_user_id');
        }
        if ($crm_user_id <= 0) {
            $crm_user_id = (!empty($deal) && !empty($deal['user_contact_id']) && $deal['user_contact_id'] > 0) ? 
                $deal['user_contact_id'] : 
                $this->source->getNormalizedResponsibleContactId();
        }

        $data = array(
            'source_id' => $this->source->getId(),
            'contact_id' => $contact->getId(),
            'user_contact_id' => $crm_user_id,
            'summary' => $summary,
            'deal_id' => $deal ? (int)ifset($deal['id']) : null
        );

        $cm = new crmConversationModel();

        return $cm->add($data, crmConversationModel::TYPE_EMAIL);
    }

    /**
     * @param crmContact $user
     * @param crmContact $customer
     * @param array $deal|null
     * @param array $default
     * @return array
     */
    protected function prepareMailToSave(crmContact $user, crmContact $customer, $deal = null, $default = array())
    {
        $deal_id = $deal ? ifset($deal['id']) : null;
        $message = array_merge($default, array(
            'creator_contact_id' => $user->getId(),
            'transport' => crmMessageModel::TRANSPORT_EMAIL,
            'direction' => null,
            'contact_id' => $customer->getId(),
            'subject' => $this->mail->getSubject(),
            'from' => $this->mail->getReplyEmail(),
            'original' => 1,
            'deal_id' => $deal_id,
            'source_id' => $this->source->getId()
        ));
        return $message;
    }

    /**
     * @param crmContact $user
     * @param crmContact $customer
     * @param array $deal|null
     * @param array $mail
     *        string $mail['body']
     *        array|null $mail['attachments']
     *
     * @return int|null if message has saved successfully return id of message, otherwise null
     */
    protected function saveMail(crmContact $user, crmContact $customer, $deal, $mail = array())
    {
        $mm = new crmMessageModel();

        $direction = crmMessageModel::DIRECTION_IN;
        if ($this instanceof crmEmailSourceWorkerOutcomingStrategy) {
            $direction = crmMessageModel::DIRECTION_OUT;
        }

        $attachments = (array)ifset($mail['attachments']);
        unset($mail['attachments']);
        $recipients = (array)ifset($mail['recipients']);
        unset($mail['attachments']);

        $mail['body'] = (string)ifset($mail['body']);
        $mail['direction'] = $direction;

        $message = $this->prepareMailToSave($user, $customer, $deal, $mail);

        // attach files to message
        $file_ids = array();
        if ($attachments !== null) {
            $file_ids = array_merge($file_ids, waUtils::getFieldValues($attachments, 'file_id'));
            $file_ids = array_unique($file_ids);
        }
        $message['attachments'] = $file_ids;

        $message_id = $this->source->createMessage($message, $direction);
        $mm->saveEmailSource($message_id, $this->mail->getSourcePath());
        $mm->setEmailRecipients($message_id, $recipients);

        return $message_id;
    }

    /**
     * Prepare mail body to commit in DB as crm_message.body or crm_deal.description or something else
     * Workup content,
     * For example add special data-crm-file-id attribute for img tags
     *
     * @param string $body
     * @param array $attachments Must have this fields: inline, content-id, file_id
     * @return string
     */
    protected function prepareMailBodyToCommit($body, $attachments)
    {
        $inline_attachments = array();
        foreach ($attachments as $attachment) {
            if (empty($attachment['inline']) || empty($attachment['content-id']) || empty($attachment['file_id'])) {
                continue;
            }
            $inline_attachments[$attachment['content-id']] = $attachment;
        }

        if (!$inline_attachments) {
            return $body;
        }

        if (!preg_match_all('!<img\s.*?>!smui', $body, $match)) {
            return $body;
        }

        foreach ($match[0] as $tag) {
            $pattern = '!<img(.*?src=(["\'])cid:(.*?)["\'].*?)>!smui';
            if (!preg_match($pattern, $tag, $m)) {
                continue;
            }
            $cid = $m[3];
            if (!isset($inline_attachments[$cid])) {
                continue;
            }
            $attachment = $inline_attachments[$cid];
            $file_id = $attachment['file_id'];
            $original = $m[0];
            $inside = $m[1];
            $quote = $m[2];
            $tag = "<img data-crm-file-id={$quote}{$file_id}{$quote}{$inside}>";
            $body = str_replace($original, $tag, $body);
        }

        return $body;
    }

    /**
     * @param array $recipients
     * @return array
     */
    protected function prepareMailRecipientsToCommit($recipients)
    {
        $email = $this->source->getEmail();

        $emails = array();
        foreach ($recipients as $index => $recipient) {
            if ($recipient['clean_email'] === $email) {
                unset($recipients[$index]);
                continue;
            }
            $emails[] = $recipient['clean_email'];
        }

        $recipients = array_values($recipients);
        if (!$emails) {
            return $recipients;
        }

        $cem = new waContactEmailsModel();
        $email_contact_id_map =
            $cem->select('contact_id, email')
                ->where('email IN (:emails)', array('emails' => $emails))
                ->fetchAll('email', true);

        foreach ($recipients as $index => &$recipient) {
            if (isset($email_contact_id_map[$recipient['clean_email']])) {
                $recipient['contact_id'] = $email_contact_id_map[$recipient['clean_email']];
            }
        }
        unset($recipient);

        return $recipients;
    }

    /**
     * Attach files to deal and bind each file_id to attach item
     * @param array $deal
     * @param array $attachments
     * @return array
     */
    protected function attachFilesToDeal($deal, $attachments)
    {
        $files = waUtils::getFieldValues($attachments, 'request_file');
        $file_ids = self::getDealModel()->attachFiles($deal['id'], $files);
        foreach ($file_ids as $index => $file_id) {
            $attachments[$index]['file_id'] = $file_id;
        }
        return $attachments;
    }

    /**
     * Attach files to contact and bind each file_id to attach item
     * @param int $contact_id
     * @param array $attachments
     * @return array
     */
    protected function attachFilesToContact($contact_id, $attachments)
    {
        $contact_id = (int)$contact_id;
        if ($contact_id <= 0 || !$attachments) {
            return array();
        }

        $creator_contact_id = wa()->getUser()->getId();
        if ($this instanceof crmEmailSourceWorkerIncomingStrategy) {
            $creator_contact_id = $contact_id;
        }
        $files = waUtils::getFieldValues($attachments, 'request_file');

        // attach files
        $file_ids = array();
        $fm = new crmFileModel();
        foreach ($files as $index => $file) {
            if ($file instanceof waRequestFile) {
                $file_id = $fm->add([
                    'creator_contact_id' => $creator_contact_id,
                    'contact_id' => $contact_id,
                    'source_type' => crmFileModel::SOURCE_TYPE_MESSAGE,
                ], $file);
                if ($file_id > 0) {
                    $file_ids[$index] = $file_id;
                }
            }
        }

        foreach ($file_ids as $index => $file_id) {
            $attachments[$index]['file_id'] = $file_id;
        }
        return $attachments;
    }

    /**
     * @return null|array
     */
    protected function getCurrentRecipient()
    {
        if ($this->recipient === null) {
            $recipient = self::getRecipient($this->source, $this->mail);
            $this->recipient = $recipient ? $recipient : array();
        }
        return $this->recipient ? $this->recipient : null;
    }

    /**
     * @param crmEmailSource $source
     * @param crmMailMessage $mail
     * @return null
     */
    protected static function getRecipient(crmEmailSource $source, crmMailMessage $mail)
    {
        $email = $source->getEmail();
        foreach (array($mail->getRecipients(), $mail->getEnvelopeTo()) as $recipients) {
            foreach ($recipients as $recipient) {
                if ($recipient['clean_email'] === $email) {
                    return $recipient;
                }
            }
        }
        return null;
    }

    /**
     * @param crmContact $contact
     * @return array|null
     */
    protected function findDealToProcess(crmContact $contact)
    {
        // priority
        $find_deal_rule = array(
            'email_plus_part',
            'in_reply_to',
            'references',
            'exclusive_opened'
        );

        $deal = null;
        foreach ($find_deal_rule as $rule) {
            if ($deal) {
                break;
            }
            if ($rule == 'email_plus_part') {
                $deal = self::findDealByMagicEmail($this->source, $this->mail);
            } elseif ($rule === 'in_reply_to') {
                $deal = $this->findDealByInReplyToHeader();
            } elseif ($rule === 'references') {
                $deal = $this->findDealByReferencesHeader();
            } elseif ($rule == 'exclusive_opened') {
                $deal = $this->findExclusiveOpenedDeal($contact);
            }
        }
        return $deal;
    }

    /**
     * @param crmEmailSource $source
     * @param crmMailMessage $mail
     * @return array|null
     */
    protected static function findDealByMagicEmail(crmEmailSource $source, crmMailMessage $mail)
    {
        $recipient = self::getRecipient($source, $mail);
        if (!$recipient) {
            return null;
        }

        $suffix = $recipient['suffix'];
        $deal_id = self::getDealIdByMagicEmailSuffix($suffix);
        if ($deal_id <= 0) {
            return null;
        }
        $deal = self::getOpenedDeal($deal_id);
        if (!$deal) {
            return null;
        }

        // suffixes must be equal
        $magic_suffix = self::buildMagicEmailSuffix($deal);
        if ($suffix !== $magic_suffix) {
            return null;
        }

        return $deal;
    }

    protected function findDealByInReplyToHeader()
    {
        $in_reply_to = $this->mail->getInReplyTo();
        if (strlen($in_reply_to) <= 0) {
            return null;
        }
        $deal_id = self::parseMessageId($in_reply_to);
        if ($deal_id === 0) {
            $deal = array(
                'id' => 0,
            );
            return $deal;
        }
        if ($deal_id <= 0) {
            return null;
        }
        $deal = self::getOpenedDeal($deal_id);
        return $deal;
    }

    protected function findDealByReferencesHeader()
    {
        $references = $this->mail->getReferences();
        foreach ($references as $reference) {
            if (strlen($reference) <= 0) {
                continue;
            }
            $deal_id = self::parseMessageId($reference);

            if ($deal_id === 0) {
                $deal = array(
                    'id' => 0,
                );
                return $deal;
            }

            if ($deal_id <= 0) {
                continue;
            }
            $deal = self::getOpenedDeal($deal_id);
            if (!$deal) {
                continue;
            }
            return $deal;
        }
        return null;
    }

    protected function findExclusiveOpenedDeal(crmContact $contact)
    {
        $where = array(
            'contact_id' => $contact->getId(),
            'status_id' => crmDealModel::STATUS_OPEN
        );
        $count = $this->getDealModel()->countByField($where);
        if ($count != 1) {
            return null;
        }
        $deal = $this->getDealModel()->getByField($where);
        $participants = $this->getDealModel()->getParticipantsModel()->getParticipants($deal['id']);
        $deal['participants'] = $participants;
        return $deal;
    }

    /**
     * @param $id
     * @return array|null
     */
    protected static function getOpenedDeal($id)
    {
        $deal = self::getDealModel()->getDeal($id, true);
        return $deal && $deal['status_id'] === crmDealModel::STATUS_OPEN ? $deal : null;
    }

    /**
     * @return crmDealModel
     */
    protected static function getDealModel()
    {
        return !empty(self::$models['deal']) ? self::$models['deal'] : (self::$models['deal'] = new crmDealModel());
    }

    /**
     * @param string $suffix
     * @return int
     */
    protected static function getDealIdByMagicEmailSuffix($suffix)
    {
        return strlen($suffix) > 4 ? (int)substr((string)$suffix, 0, -4) : 0;
    }

    protected static function parseMessageId($message_id)
    {
        $parts = explode('@', $message_id, 2);
        $middle = substr($parts[0], 16, -16);
        if (empty($middle) || $middle[0] != '.' || substr($middle, -1) != '.') {
            return null;
        }
        return (int) substr($middle, 1, -1);
    }

    /**
     * TODO: redesign it, make it like $source->getMagicEmail or something like that
     * @param crmEmailSource $source
     * @param array $deal
     * @return string
     */
    public static function buildMagicEmail($source, $deal)
    {
        $suffix = self::buildMagicEmailSuffix($deal);
        return $suffix ? $source->getEmail($suffix) : '';
    }

    /**
     * @param array $deal
     * @return string
     */
    protected static function buildMagicEmailSuffix($deal)
    {
        $deal_id = (int)ifset($deal['id']);
        if ($deal_id <= 0) {
            return '';
        }
        $create_time = strtotime((string)ifset($deal['create_datetime']));
        $suffix = $deal_id . date('Hi', $create_time);
        return strlen($suffix) > 4 ? $suffix : '';
    }

}
