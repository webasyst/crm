<?php

class crmMessageWriteReplyEmailDialogAction extends crmSendEmailDialogAction
{
    public function preExecute()
    {
        $message = $this->getMessage();
        if ($message['source_id'] > 0 && $message['transport'] != crmMessageModel::TRANSPORT_EMAIL) {
            $source = crmSource::factory($message['source_id']);
            $html = crmSourceMessageSender::renderSender($source, $message, array(
                'assign' => array(
                    'send_action_url' => $this->getSendActionUrl()
                )
            ));
            die($html);
        }
    }

    public function execute()
    {
        $dm = new crmDealModel();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        $participants_ids = array();
        $deal = $this->getDeal();
        if ($deal) {
            $participants_ids = $this->getParticipantsIds($deal['participants']);
        } else {
            $funnel = $fm->getAvailableFunnel();
            if (!$funnel) {
                throw new waRightsException();
            }
            $stage_id = $fsm->select('id')->where(
                'funnel_id = '.(int)$funnel['id']
            )->order('number')->limit(1)->fetchField('id');

            // Just empty deal, for new message
            $now = date('Y-m-d H:i:s');
            $deal = $dm->getEmptyDeal();
            $deal = array_merge($deal, array(
                'creator_contact_id' => wa()->getUser()->getId(),
                'create_datetime'    => $now,
                'update_datetime'    => $now,
                'funnel_id'          => $funnel['id'],
                'stage_id'           => $stage_id,
            ));
        }

        $funnels = $fm->getAllFunnels(true);
        if (empty($funnels[$deal['funnel_id']])) {
            throw new waException('Funnel not found');
        }
        $stages = $fsm->getStagesByFunnel($funnels[$deal['funnel_id']]);

        $this->view->assign(array(
            'deal'            => $deal,
            'stages'          => $stages,
            'funnels'         => $funnels,
            'participants'    => $this->getParticipantsData($participants_ids),
            'recipients'      => $this->getRecipients(),
            'files'           => $this->getFiles(),
            'subject'         => $this->getSubject(),
            'hidden_params'   => $this->getHiddenParams(),
            'send_action_url' => $this->getSendActionUrl(),
            'action'          => self::ACT_REPLY_MESSAGE,
        ));
    }

    protected function getSendActionUrl()
    {
        return wa()->getAppUrl('crm') . '?module=message&action=sendReply';
    }

    /**
     * @return string
     */
    protected function getSubject()
    {
        $message = $this->getMessage();
        $prefix = substr($message['subject'], 0, 3);
        if (strtolower($prefix) === 're:') {
            return $message['subject'];
        }
        return 'Re: ' . $message['subject'];
    }

    /**
     * @return string
     */
    protected function getBody()
    {
        $message = $this->getMessage();
        $create_datetime = $message['create_datetime'];
        $body = $message['body_sanitized'];
        $contact = new crmContact($message['creator_contact_id']);
        $name = htmlspecialchars($contact->getName());
        $text = _w('<p><br></p></b><br><section data-role="c-email-signature">:SIGNATURE:</section><p><br></p><p>:MESSAGE_TIME:, :CLIENT: wrote:</p><blockquote>:BODY:</blockquote>');
        $text = str_replace(':MESSAGE_TIME:', wa_date('datetime', $create_datetime), $text);
        $text = str_replace(':CLIENT:', $name, $text);
        $text = str_replace(':BODY:', $body, $text);
        $text = str_replace(':SIGNATURE:', $this->getUserContact()->getEmailSignature(), $text);
        return $text;
    }

    protected function getHiddenParams()
    {
        $message = $this->getMessage();
        return array(
            'message_id' => $message['id']
        );
    }

    protected function getRecipients()
    {
        $message_id = (int)$this->getParameter('id');
        $mrm = new crmMessageRecipientsModel();

        $recipients = $mrm->getRecipients($message_id);

        /**
         * TODO: Refactor it
         * @deprecated 'email' field
         * Not deprecated until version 1.2
         */
        foreach ($recipients as &$recipient) {
            $recipient['email'] = $recipient['destination'];
            //
            if ($recipient['destination'] == $recipient['contact_id']) {
                unset($recipients[$recipient['destination']]);
                continue;
            }
            if ($recipient['contact_id'] == wa()->getUser()->getId()) {
                unset($recipients[$recipient['destination']]);
                continue;
            }
        }
        unset($recipient);

        $recipient_contact = $this->getRecipientContact();

        // Delete the main recipent contact (by email)..
        $main_recipent_email = $recipient_contact->get('email', 'default');
        if (isset($recipients[$main_recipent_email])) {
            unset($recipients[$main_recipent_email]);
        }

        // Photos of recipients
        $recipients_ids = array();
        foreach ($recipients as $recipient) {
            $recipients_ids[] = $recipient['contact_id'];
            unset($recipient);
        }

        $photos_recipients = $this->getPhotosOfRecipients($recipients_ids);

        $recipients_with_photos = array();

        foreach ($recipients as $recipient) {
            if (isset($photos_recipients[$recipient['contact_id']])) {
                $recipient['photo'] = $photos_recipients[$recipient['contact_id']]['photo_url_16'];
            } else {
                $recipient['photo'] = null;
            }
            $recipients_with_photos[] = $recipient;
            unset($recipient);
        }
        $recipient = $recipients_with_photos;

        return $recipient;
    }

    protected function getPhotosOfRecipients($recipients_ids)
    {
        if (!$recipients_ids) {
            return array();
        }
        $collection = new crmContactsCollection('id/' . implode(',', $recipients_ids));
        return $collection->getContacts('photo_url_16');
    }
}
