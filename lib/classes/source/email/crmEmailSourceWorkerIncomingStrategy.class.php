<?php

class crmEmailSourceWorkerIncomingStrategy extends crmEmailSourceWorkerStrategy
{
    static protected $LOG_FILE = 'crm/source_email_worker_incoming_strategy.log';

    public function process($params = array())
    {
        $result = array();

        $contact_email = $this->mail->getReplyEmail();
        if (!$contact_email) {
            $result['fail'] = true;
            $result['reason'] = "{$contact_email} not found";
            return $result;
        }

        $contact = self::findContactByEmail($contact_email);
        if ($contact && $contact->get('is_user') == -1) {
            $result['fail'] = true;
            $contact_id = $contact->getId();
            $result['reason'] = "{$contact_id} is banned";
            return $result;
        }

        // trigger anti-spam case
        if (!$contact && $this->source->getParam('antispam')) {
            $res = $this->triggerAntiSpam($contact_email);
            $result['trigger_anti_spam'] = $res;
            return $result;
        }

        if (!$contact) {
            $contact = $this->createContact($contact_email, $this->mail->getReplyName());
            if ($contact) {
                $this->source->addContactsToSegments($contact->getId());
            }
        }

        $res = $this->doProcess($contact);
        $result['do_process'] = $res;

        return $result;
    }

    public function processAntiSpam()
    {
        $contact_email = $this->mail->getReplyEmail();
        if (!$contact_email) {
            return false;
        }

        $contact = self::findContactByEmail($contact_email);

        if ($contact && $contact->get('is_user') == -1) {
            return false;
        }

        if (!$contact) {
            $contact = $this->createContact($contact_email, $this->mail->getReplyName());
            if ($contact) {
                $this->source->addContactsToSegments($contact['id']);
            }
        }
        $contact->setEmailConfirmed($contact_email);

        $result = $this->doProcess($contact);
        return $result;
    }

    protected function doProcess(crmContact $contact)
    {
        $result = array(
            'message_id' => 0,
            'deal_id' => 0,
            'contact' => $contact,
            'deal' => null,
            'deal_just_created' => false
        );

        // Try find deal to work with
        $deal = $this->findDealToProcess($contact);

        // Case when deal is found and deal.id == 0 has special meanings:
        // there is not deal context, so just work with message
        // and skip process part related with deal
        $skip_deal_process_part = $deal && $deal['id'] === 0;

        if (!$skip_deal_process_part) {
            $res = $this->doDealProcessPart($contact, $deal);
            $result['deal_just_created'] = $res['just_created'];
            $result['deal'] = $res['deal'];
            $deal = $result['deal'];
            $result['deal_id'] = $deal ? $deal['id'] : 0;
        }

        $result['message_id'] = $this->doMessageSavingProcessPart($contact, $deal);
        $result = $this->doFinishProcessPart($result, $deal);

        return $result;
    }

    /**
     * @param $result
     * @param $deal
     * @return mixed
     */
    protected function doFinishProcessPart($result, $deal = null)
    {
        $mm = new crmMessageModel();
        $message = $mm->getMessage($result['message_id']);

        /**
         * @var crmContact $contact
         */
        $contact = ifset($result['contact']);

        $this->doConversationProcessPart($contact, $message, $deal);

        if (!ifset($result['deal_just_created'])) {
            return $result;
        }

        // body may be modified, so update description
        if ($deal && $deal['description'] !== $message['body']) {
            self::getDealModel()->updateById($result['deal_id'], array('description' => $message['body']));
        }

        $messages = $this->source->getMessages();
        $this->sendMessages($messages, $contact, $deal);

        return $result;
    }

    protected function doConversationProcessPart(crmContact $contact, $message, $deal = null)
    {
        $conversation = $this->findConversation($contact, $deal);
        if ($conversation) {
            $conversation_id = $conversation['id'];
        } else {
            $conversation_id = $this->createConversation($contact, $message, $deal);
        }
        $mm = new crmMessageModel();
        $mm->addToConversation($message, $conversation_id);
        
        $message['conversation_id'] = $conversation_id;
        (new crmPushService)->notifyAboutMessage($contact, $message, $conversation, $deal);
    }

    protected function findConversation(crmContact $contact, $deal)
    {
        if ($deal && $deal['id'] === 0) {
            return $this->findConversationByContact($contact);
        }
        if ($deal) {
            return $this->findConversationByDeal($deal);
        }
        $create_deal_mode_off = !$this->source->getParam('create_deal');
        if ($create_deal_mode_off) {
            return $this->findConversationByContact($contact);
        }
        return null;
    }

    protected function findConversationByContact(crmContact $contact)
    {
        $cm = new crmConversationModel();
        return $cm->getByField(array(
            'contact_id' => $contact->getId(),
            'deal_id'    => null,
            'type'       => crmConversationModel::TYPE_EMAIL,
            'is_closed'  => 0
        ));
    }

    /**
     * @param crmContact $contact
     * @param array|null $deal
     * @return array
     */
    protected function doDealProcessPart(crmContact $contact, $deal = null)
    {
        $create_deal_mode_on = $this->source->getParam('create_deal');

        $just_created = false;
        if ($create_deal_mode_on && !$deal) {
            $deal = $this->createDeal($contact);
            $just_created = !!$deal;
        }

        if ($deal) {
            $participant_ids = waUtils::getFieldValues($deal['participants'], 'contact_id');
            if (!in_array($contact->getId(), $participant_ids)) {
                self::getDealModel()->addParticipants($deal['id'], $contact->getId(), crmDealParticipantsModel::ROLE_CLIENT);
                $deal = self::getDealModel()->getDeal($deal['id'], true);
            }
        }

        return array(
            'deal' => $deal,
            'just_created' => $just_created
        );
    }

    /**
     * @param crmContact $contact
     * @param array|null $deal
     * @return int|null
     */
    protected function doMessageSavingProcessPart(crmContact $contact, $deal = null)
    {
        $mail_attachments = $this->mail->getAttachments();

        if ($deal && $deal['id'] === 0) {
            $deal = null;
        }

        if ($deal) {
            $attachments = $this->attachFilesToDeal($deal, $mail_attachments);
        } else {
            $attachments = $this->attachFilesToContact($contact['id'], $mail_attachments);
        }

        $body = $this->prepareMailBodyToCommit($this->mail->getBody(), $attachments);
        $recipients = $this->prepareMailRecipientsToCommit($this->mail->getRecipients());

        // find TO
        $to = null;
        foreach ($this->mail->getDirectRecipients() as $recipient) {
            if ($recipient['clean_email'] !== $this->source->getEmail()) {
                $to = $recipient['clean_email'];
                break;
            }
        }

        $message_id = $this->saveMail(
            $contact,
            $contact,
            $deal,
            array(
                'body'        => $body,
                'attachments' => $attachments,
                'recipients'  => $recipients,
                'to'          => $to
            )
        );

        return $message_id;
    }

    /**
     * @param $messages
     * @param crmContact $contact
     * @param $deal
     */
    protected function sendMessages($messages, crmContact $contact, $deal)
    {
        $vars = null;
        $assign = null;

        foreach ($messages as $message) {

            if (empty($message['is_smarty_tmpl'])) {
                if ($vars === null) {
                    $vars = array_merge(array(
                        '{ORIGINAL_SUBJECT}' => $this->mail->getSubject(),
                        '{ORIGINAL_TEXT}' => $this->mail->getBody(),
                        '{COMPANY_NAME}' => htmlspecialchars(wa()->accountName())
                    ), $this->getContactVars($contact));
                }

                $compiled = $this->compilePlainMailTemplate($message['tmpl'], $vars);
            } else {
                if ($assign === null) {
                    $assign = [
                        'original_subject' => $this->mail->getSubject(),
                        'original_text' => $this->mail->getBody(),
                        'company_name' => wa()->accountName(),
                        'customer' => $contact
                    ];
                }
                $compiled = $this->compileSmartyMailTemplate($message['tmpl'], $assign);
            }

            $body = $compiled['body'];
            $subject = $compiled['subject'];

            foreach ((array) ifset($message['to']) as $to => $on) {
                if (!$on) {
                    continue;
                }

                $to_contact = null;
                if ($to == crmEmailSource::MESSAGE_TO_VARIANT_CLIENT) {
                    $to_contact = $contact;
                } elseif ($to === crmEmailSource::MESSAGE_TO_VARIANT_RESPONSIBLE_USER) {
                    $to_contact = new crmContact($deal['user_contact_id']);
                } elseif (wa_is_int($to)) {
                    $to_contact = new crmContact($to);
                }

                if (!$to_contact) {
                    continue;
                }

                $email = $to_contact->getDefaultEmailValue();
                if (!$email) {
                    continue;
                }

                $to = array($email => $to_contact->getName());
                $from = waMail::getDefaultFrom();

                $this->sendEmail($subject, $body, $from, $to);
            }
        }
    }

    protected function getContactVars(crmContact $contact)
    {
        $vars = array();
        foreach ($contact->load() as $fld_name => $value) {
            $var_name = strtoupper('{CUSTOMER_'.$fld_name.'}');
            if (is_array($value)) {
                $vars[$var_name] = $contact->get($fld_name, 'default');
                if (is_array($vars[$var_name]) || is_object($vars[$var_name])) {
                    unset($vars[$var_name]);
                }
            } else {
                $vars[$var_name] = $value;
            }
        }

        $vars['{CUSTOMER_ID}'] = $contact->getId();
        $vars['{CUSTOMER_NAME}'] = htmlspecialchars($contact->getName());

        return $vars;
    }

    protected function logCreateDeal($deal)
    {
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        return $log_model->add(crmDealModel::LOG_ACTION_ADD, array('deal_id' => $deal['id']), $deal['creator_contact_id']);
    }

    /**
     * @param crmContact $contact
     * @return null|array
     */
    protected function createDeal(crmContact $contact)
    {
        $deal = $this->prepareDataForCreate($contact);
        $deal_id = (int)$this->source->createDeal($deal);

        if (!$deal_id) {
            $message = "Couldn't create deal for contact %s, line %s";
            $contact_id_str = $contact->exists() ? $contact->getId() : 'NULL';
            $message = sprintf($message, $contact_id_str, __LINE__);
            waLog::log($message, self::$LOG_FILE);
            return null;
        }

        $deal = self::getDealModel()->getDeal($deal_id, true);
        $this->logCreateDeal($deal);

        return $deal;
    }

    protected function prepareDataForCreate(crmContact $contact)
    {
        $deal = array(
            'name' => $this->mail->getSubject(),
            'contact_id' => $contact->getId(),
            'creator_contact_id' => $contact->getId(),
            'description' => $this->mail->getBody()
        );
        $deal['files'] = $this->mail->getAttachments();
        return $deal;
    }

    /**
     * @param string $to_email
     * @return bool
     */
    protected function triggerAntiSpam($to_email)
    {
        $confirmation_hash = md5(mt_rand() . uniqid(__METHOD__ . $this->source->getId(), true));
        $confirm_url = wa()->getRouteUrl('crm/frontend/confirmEmail', array('hash' => $confirmation_hash), true);
        $template = (string)$this->source->getParam('antispam_mail_template');

        $compiled = $this->compilePlainMailTemplate($template, array(
            '{CONFIRM_URL}' => $confirm_url,
            '{ORIGINAL_SUBJECT}' => $this->mail->getSubject(),
            '{ORIGINAL_TEXT}' => $this->mail->getBody()
        ));

        $body = $compiled['body'];
        $subject = $compiled['subject'];

        $to = array($to_email => "");
        $from = waMail::getDefaultFrom();

        if (!$this->sendEmail($subject, $body, $from, $to)) {
            return false;
        }

        $this->saveAntiSpamTempData($confirmation_hash, array(
            'source_id' => $this->source->getId(),
            'mail' => file_get_contents($this->mail->getSourcePath())
        ));

        return true;
    }

    protected function saveAntiSpamTempData($hash, $temp_data)
    {
        $cst = new crmTempModel();
        $cst->save($hash, $temp_data);
        return true;
    }

    protected function resolveVars($message, $vars = array())
    {
        foreach ($vars as $var => $val) {
            $message = str_replace($var, $val, $message);
        }
        return $message;
    }

    protected function compilePlainMailTemplate($template, $vars = array())
    {
        $parts = explode('{SEPARATOR}', $template, 3);
        $body = array_pop($parts);
        $subject = array_pop($parts);
        $from = array_pop($parts);
        $subject = $this->resolveVars($subject, $vars);
        $body = $this->resolveVars($body, $vars);
        return array(
            'from' => $from,
            'subject' => $subject,
            'body' => $body
        );
    }

    protected function compileSmartyMailTemplate($template, $assign = [])
    {
        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign($assign);
        $result = $view->fetch('string:'.$template);
        $view->clearAllAssign();
        $view->assign($old_vars);
        $parts = explode('{SEPARATOR}', $result, 3);
        $body = array_pop($parts);
        $subject = array_pop($parts);
        $from = array_pop($parts);
        return array(
            'from' => $from,
            'subject' => $subject,
            'body' => $body
        );
    }

    protected function sendEmail($subject, $body, $from, $to)
    {
        try {
            $m = new waMailMessage(htmlspecialchars_decode($subject), $body);
            $m->setTo($to)->setFrom($from);
            $sent = $m->send();
            $reason = 'waMailMessage->send() returned FALSE';
        } catch (Exception $e) {
            $sent = false;
            $reason = $e->getMessage();
        }
        if (!$sent) {
            if (is_array($to)) {
                $to = var_export($to, true);
            }
            if (is_array($from)) {
                $from = var_export($from, true);
            }
            waLog::log('Unable to send email from '.$from.' to '.$to.' ('.$subject.'): '.$reason, self::$LOG_FILE);
            return false;
        }
        return true;
    }
}
