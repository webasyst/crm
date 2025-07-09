<?php

abstract class crmSendEmailController extends crmJsonController
{
    protected function send()
    {
        $data = $this->prepareData();
        $errors = $this->validate($data);
        if ($errors) {
            return array(
                'errors' => $errors
            );
        }
        $email_message = $this->formEmailMessage($data);
        if (empty($email_message['conversation_id'])) {
            $email_message['conversation_id'] = $this->getConversationId();
        }
        $send_result = $this->sendEmailMessage($email_message);
        $id = $send_result['id'];

        if (!$send_result['id']) {
            return array(
                'errors' => array(
                    'common' => $send_result['details']['error_msg']
                )
            );
        }

        $result['id'] = $id;
        $result['email_message'] = $email_message;

        return $result;
    }

    protected function getEmail()
    {
        $contact = $this->getRecipientContact();
        $email = $this->getRecipientEmail();
        $contact_emails = waUtils::getFieldValues($contact['email'], 'value');
        if (!in_array($email, $contact_emails)) {
            $email = $contact->getDefaultEmailValue();
        }
        return $email;
    }

    protected function prepareData()
    {
        $data = [
            'subject'      => $this->getSubject(),
            'email'        => $this->getEmail(),
            'sender_email' => $this->getSenderEmail(),
            'file_id'      => $this->getFileIds(),
            'cc'           => $this->getCc(),
            'hash'         => $this->getHash(),
        ];
        $is_plain_text = (bool)$this->getParameter('is_plain_text', false, waRequest::TYPE_STRING_TRIM);
        $body_raw = (string)$this->getParameter('body', '', waRequest::TYPE_STRING_TRIM);
        $data['is_plain_text'] = $is_plain_text;
        $data['body_raw'] = $body_raw;
        $data['body'] = $is_plain_text ? htmlspecialchars($body_raw, ENT_QUOTES, 'UTF-8') : $body_raw;
        return $data;
    }

    /**
     * @param $msg
     * @return array
     *   - int 'id' - id of crm_message record. If success, id > 0, otherwise 0
     *   - array 'details' - If success empty array, otherwise has keys
     *      - string 'error_msg' - message about reason of sending failure
     * @throws waException
     */
    protected function sendEmailMessage($msg)
    {
        $contact = ifset($msg['contact']);
        if (wa_is_int($contact) && $contact > 0) {
            $contact = new waContact($contact);
        } elseif (is_array($contact)) {
            $contact = new waContact($contact);
        }
        if (!($contact instanceof waContact)) {
            $contact_id = (int)ifset($msg['contact_id']);
            if ($contact_id > 0) {
                $contact = new waContact($contact_id);
            }
        }
        if (!($contact instanceof waContact)) {
            $contact = null;
        }
        $to = null;
        if (!empty($msg['email'])) {
            $to = $msg['email'];
        } elseif ($contact) {
            $to = $contact->get('email', 'default');
        }

        if (!$to) {
            return array(
                'id' => 0,
                'details' => array(
                    'error_msg' => _w('Recipient email is not defined')
                )
            );
        }
        $from = waMail::getDefaultFrom();
        if (!empty($msg['from'])) {
            $from = $msg['from'];
        }

        try {
            $m = new waMailMessage($msg['subject'], $msg['body']);

            // Reply-To
            foreach ((array)ifset($msg['reply_to']) as $reply_to_email => $reply_to_name) {
                $m->addReplyTo($reply_to_email, $reply_to_name);
            }
            // Attachments
            foreach ((array)ifset($msg['attachments']) as $file) {
                $m->addAttachment($file['path'], $file['name']);
            }

            // From
            $m->setFrom($from);

            // Sender
            $sender = ifset($msg['sender']);
            if (!$sender) {
                $sender = waMail::getDefaultFrom();
            }
            if ($this->needSpecifySender($from, $sender)) {
                $m->setSender($msg['sender']);
            }

            // To
            $m->setTo($to, $contact->getName());

            // Cc
            $msg['cc'] = (array)ifset($msg['cc']);
            foreach ($msg['cc'] as $cc) {
                $m->addCc($cc['email'], $cc['name']);
            }

            // Message-ID
            if (!empty($msg['message_id'])) {
                $m->setId($msg['message_id']);
            } else {
                $m->generateId();
            }
            // In-reply-to
            if (!empty($msg['in_reply_to'])) {
                try {
                    $m->getHeaders()->addIdHeader('In-Reply-To', $msg['in_reply_to']);
                } catch (Exception $e) {
                    // do nothing
                }
            }
            // References
            if (!empty($msg['references']) && is_array($msg['references'])) {
                try {
                    $m->getHeaders()->addIdHeader('References', $msg['references']);
                } catch (Exception $e) {
                    // do nothing
                }
            }
            $success = (bool) $m->send();
            if (!$success) {
                return array(
                    'id' => 0,
                    'details' => array(
                        'error_msg' => 'Unknown error. Explore mail.log'   // unknown and unreachable from this point error :( Explore mail.log
                    )
                );
            }

            $from = $m->getFrom();
        } catch (Exception $e) {

            $message = join(PHP_EOL, array(
                'Exception',
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            waLog::log($message, 'crm/email_send.log');

            return array(
                'id' => 0,
                'details' => array(
                    'error_msg' => $e->getMessage()
                )
            );
        }

        $mm = new crmMessageModel();
        $id = $mm->fix(array(
            'transport'  => crmMessageModel::TRANSPORT_EMAIL,
            'direction'  => crmMessageModel::DIRECTION_OUT,
            'contact_id' => $contact->getId(),
            'subject'    => $msg['subject'],
            'body'       => $msg['body'],
            'from'       => $from,
            'to'         => $to,
            'deal_id'    => ifset($msg['deal_id']),
            'event'      => ifset($msg['event']),
        ), array(
            'wa_log' => ifset($msg['wa_log'])
        ));

        $creator = array(
            'contact_id' => wa()->getUser()->getId(),
            'email'      => key($from),
            'name'       => wa()->getUser()->getName(),
        );

        $recipient = array(
            'contact_id' => $contact->getId(),
            'email'      => $to,
            'name'       => $contact->getName(),
        );

        $this->getMessageRecipientsModel()->setEmailRecipient($id, $creator, crmMessageRecipientsModel::TYPE_FROM);
        $this->getMessageRecipientsModel()->setEmailRecipient($id, $recipient, crmMessageRecipientsModel::TYPE_TO);
        $this->getMessageRecipientsModel()->setEmailRecipients($id, $msg['cc'], crmMessageRecipientsModel::TYPE_CC);

        // Add conversation
        $this->addConversation($id, ifset($msg, 'conversation_id', null));

        return array(
            'id' => $id,
            'details' => array()
        );
    }

    /**
     * Need specify sender in outgoing email message?
     *
     * Scan mail config transports by this <from>, if found specific transport settings, SENDER IS NOT NEEDED
     * In the same time from and sender MUST BE differ
     *
     * @param string|array $from - Type corresponding waMailMessage (waMail) format of form/sender: string or as array of format [<email> => <name>]
     * @param string|array $sender - Type corresponding waMailMessage (waMail) format of form/sender: string or as array of format [<email> => <name>]
     * @return bool
     */
    protected function needSpecifySender($from, $sender)
    {
        if (is_array($from)) {
            $from = key($from);   // extract <email> part of <from>
        }

        $mail_config = wa()->getConfig()->getMail();

        // specific transport settings for this email found, no need Sender (only from)
        if (isset($mail_config[$from])) {
            return false;
        }

        $email_parts = explode('@', $from);
        if (isset($email_parts[1]) && isset($mail_config[$email_parts[1]])) {
            // specific transport settings for this domain found, no need sender (only from)
            if (isset($mail_config[$from])) {
                return false;
            }
        }

        if (is_array($sender)) {
            $sender = reset($sender);   // extract <email> part of <sender>
        }

        return trim($from) !== $sender;
    }

    protected function validate($data)
    {
        $errors = array();

        $required = array('subject', 'body', 'email');
        foreach ($required as $r) {
            if (empty($data[$r])) {
                $errors[$r] = _w('This field is required');
            }
        }


        if (!$this->isValidEmail($data['email'])) {
            $errors['email'] = _w('Invalid email');
        }

        return $errors;
    }

    protected function isValidEmail($email)
    {
        $email = (string)$email;
        if (strlen($email) <= 0) {
            return false;
        }
        $validator = new waEmailValidator();
        return $validator->isValid($email);
    }

    /**
     * @param $data
     * @return array
     */
    abstract protected function formEmailMessage($data);

    protected function getUploadedFiles()
    {
        $hash = $this->getHash();
        $file_paths = array();
        $temp_path = wa('crm')->getTempPath('mail', 'crm');
        $mail_dir = $temp_path . '/' . $hash;
        foreach (waFiles::listdir($mail_dir) as $file_path) {
            $full_file_path = $mail_dir . '/' . $file_path;
            $file_paths[] = $full_file_path;
        }
        return $file_paths;
    }

    /**
     * @return string
     */
    protected function getHash()
    {
        return trim((string)$this->getParameter('hash'));
    }

    /**
     * @return string
     */
    protected function getSubject()
    {
        return trim((string)$this->getParameter('subject'));
    }

    /**
     * @return string
     */
    protected function getSenderEmail()
    {
        return trim((string)$this->getParameter('sender_email'));
    }

    /**
     * @return array
     */
    protected function getCc()
    {
        $copies = (array)$this->getParameter('cc');
        $contact_ids = waUtils::getFieldValues($copies, 'id');
        $contact_names = $this->getContactNames($contact_ids);

        $cc = array();
        foreach ($copies as $copy) {

            $copy['email'] = !empty($copy['email']) ? $copy['email'] : '';
            if (!$this->isValidEmail($copy['email'])) {
                continue;
            }

            if (strpos($copy['email'], '+') !== false) {
                continue;
            }

            $contact_id = (int)$copy['id'];

            // Get name for not crm contact
            $name = ifempty($copy['name'], ifset($contact_names[$copy['id']]));

            $cc[] = array(
                'contact_id' => $contact_id > 0 ? $contact_id : null,
                'email' => $copy['email'],
                'name' => $name,
            );
        }

        return $cc;
    }

    /**
     * @return int|null
     */
    protected function getConversationId()
    {
        $conversation_id = $this->getParameter('conversation_id');
        if (!$conversation_id && $message_id = (int) $this->getParameter('message_id')) {
            $_message = $this->getMessageModel()->getById($message_id);
            $conversation_id = (int) ifset($_message, 'conversation_id', $conversation_id);
        }

        return $conversation_id;
    }

    protected function getContactNames($contact_ids)
    {
        $contact_ids = crmHelper::toIntArray($contact_ids);
        if (!$contact_ids) {
            return array();
        }
        $collection = new waContactsCollection('id/'.join(',', $contact_ids));
        return waUtils::getFieldValues($collection->getContacts('name'), 'name', 'id');
    }

    /**
     * @return array
     */
    protected function getFileIds()
    {
        return crmHelper::toIntArray($this->getParameter('file_id'));
    }

    protected function getRecipientEmail()
    {
        return trim((string)$this->getParameter('email'));
    }

    protected function getRecipientContact()
    {
        $id = (int)$this->getParameter('contact_id');
        return new crmContact($id);
    }


    /**
     * The method returns a valid email source that does not create a deal.
     * @return bool|array
     */
    protected function emailSource()
    {
        $source = $this->getSourceModel()->getActiveEmailSource();
        if (empty($source)) {
            return false;
        }
        return ifset($source['email'], false);
    }

    protected function addConversation($message_id, $conversation_id = null)
    {
        $message = $this->getMessageModel()->getMessage($message_id);
        if (!$message) {
            return null;
        }

        // Find message deal (for search Conversation and Source):
        $deal = false;
        if ($message['deal_id']) {
            $deal = $this->getDealModel()->getDeal($message['deal_id']);
        }

        if (empty($conversation_id)) {
            // If there is a deal - look for it
            if ($deal && $message['deal_id']) {
                $conversation = $this->getConversationModel()->getByField(array(
                    'deal_id'   => (int)$deal['id'],
                    'type'      => crmConversationModel::TYPE_EMAIL,
                    'is_closed' => 0,
                ));
            } else {
                $conversation = $this->getConversationModel()->getByField(array(
                    'contact_id' => (int)$message['contact_id'],
                    'deal_id'    => null, // We are looking for conversation without a deal!
                    'type'       => crmConversationModel::TYPE_EMAIL,
                    'is_closed'  => 0,
                ));
            }
            $conversation_id = ifset($conversation, 'id', null);
        }

        if (empty($conversation_id)) {
            // Find source ID (of EMAIL type)
            $source_id = 0;
            if ($deal && !empty($deal['source_id'])) {
                if ((int)$deal['source_id'] > 0) {
                    $source_id = (int)$deal['source_id'];
                    // must be EMAIL type, otherwise conversation will be with broken relation invariants in DB
                    $is_email_source = !!$this->getSourceModel()->getByField(array(
                        'id' => $source_id,
                        'type' => crmSourceModel::TYPE_EMAIL
                    ));
                    if (!$is_email_source) {
                        $source_id = 0;
                    }
                }
            }
            $data = array(
                'source_id'       => $source_id,
                'contact_id'      => $message['contact_id'],
                'deal_id'         => ifempty($message['deal_id']),
                'summary'         => ifset($message['subject']),
                'last_message_id' => $message['id'],
                'count'           => 1,
            );
            $conversation_id = $this->getConversationModel()->add($data, crmConversationModel::TYPE_EMAIL);
        } else {
            $this->getConversationModel()->update($conversation_id, [
                'last_message_id' => $message['id'],
                'count' => '+1',
            ]);

            $this->getMessageModel()->updateById($message['id'], array('conversation_id' => $conversation_id));
            $this->getMessageReadModel()->setRead($message['id'], $message['creator_contact_id']);

            //$message['conversation_id'] = $conversation_id;
            //(new crmPushService)->notifyAboutMessage(null, $message, ifset($conversation));
            return;
        }

        $this->getMessageModel()->updateById($message['id'], array('conversation_id' => $conversation_id));
        $this->getMessageReadModel()->setRead($message['id'], $message['creator_contact_id']);
    }
}
