<?php

class crmMessageBodyDialogAction extends crmViewAction
{
    protected $message;

    public function preExecute()
    {
        $message = $this->getMessage();

        // Mark the message as read
        $mrm = new crmMessageReadModel();
        $mrm->setRead($message['id'], wa()->getUser()->getId());

        if ($message['source_id'] > 0 && $message['transport'] != crmMessageModel::TRANSPORT_EMAIL) {
            $source = crmSource::factory($message['source_id']);
            $html = crmSourceMessageViewer::renderSource($source, $message);
            die($html);
        }
    }

    public function execute()
    {
        $message = $this->getMessage();

        /**
         * Collect the ids of all the "participating" contacts, the data from which we need to get.
         * This is the sender, you will receive (if any) and the recipients in the copy (if any)
         */
        $contact_ids = array();
        $contact_ids[] = $message['creator_contact_id'];
        $contact_ids[] = $message['contact_id'];
        $recipients = $this->getRecipientsByMessage();

        foreach ($recipients as $recipient) {
            if (wa_is_int($recipient['contact_id'])) {
                $contact_ids[] = $recipient['contact_id'];
            }
            unset($recipient);
        }

        $collection = array();
        if (ifset($contact_ids)) {
            $collection = new crmContactsCollection('id/'.implode(',', $contact_ids));
            $collection = $collection->getContacts('name,email,photo_url_16');
            foreach ($collection as $contact => $field) {
                if (!is_array($field['email'])) {
                    unset($collection[$contact]);
                }
            }

            if (!ifset($collection[$message['creator_contact_id']])) {
                $collection[$message['creator_contact_id']] = [
                    'name' => 'deleted contact_id='.$message['creator_contact_id'],
                    'photo_url_16' => '',
                ];
            }

            if (!ifset($collection[$message['contact_id']])) {
                $collection[$message['contact_id']] = [
                    'name' => 'deleted contact_id='.$message['contact_id'],
                    'photo_url_16' => '',
                ];
            }

            $collection[$message['contact_id']]['email'] = $message['to'];
        }

        // Add userpic for recipients
        $recipients_with_photos = array();
        foreach ($recipients as $recipient) {
            if (isset($collection[$recipient['contact_id']])) {
                $recipient['photo'] = $collection[$recipient['contact_id']]['photo_url_16'];
            } else {
                $recipient['photo'] = null;
            }
            $recipients_with_photos[] = $recipient;
            unset($recipient);
        }
        $recipients = $recipients_with_photos;
        unset($recipients_with_photos);

        //
        $deal = array();

        if ($message['deal_id']) {
            $dm = new crmDealModel();
            $deal = $dm->getDeal($message['deal_id']);
        }

        if ($message['deal_id'] && empty($deal)) {
            $deal = array(
                'name' => _w('Was deleted'),
            );
        }

        //
        $delete_message_text = sprintf(_w('Delete message from %s'), htmlspecialchars($collection[$message['creator_contact_id']]['name']));

        // Prepare a clean deal, in case the user wants to create a new for this message.
        if (!$message['deal_id'] && empty($deal)) {
            $clean_data = $this->getCleanDealData();
        }

        $to_list = $this->buildToList($recipients, $collection[$message['contact_id']]);
        $copy_recipients = array_filter($recipients, function($el) {
            return $el['type'] !== crmMessageRecipientsModel::TYPE_TO;
        });

        $this->view->assign(array(
            'message'             => $message,
            'from'                => $collection[$message['creator_contact_id']],
            'to_list'             => $to_list,
            'deal'                => $deal,
            'clean_data'          => ifempty($clean_data),
            'funnel'              => $this->getFunnel($deal),
            'contacts'            => $collection,
            'recipients'          => $recipients,
            'copy_recipients'     => $copy_recipients,
            'delete_message_text' => $delete_message_text,
            'is_admin'            => $this->getCrmRights()->isAdmin(),
            'messages_in_conversation' => $this->getMessageModel()->countByField(['conversation_id' => $message['conversation_id']]),
        ));
    }

    /**
     * @param array $recipients - array of crm_message_recipients records
     * @param $contact - array with keys: 'id', 'email', 'name', 'photo_url_16'
     * @return array $result
     *      Array of structures:
     *          Fields of crm_message_recipients:
     *              - message_id
     *              - destination
     *              - type
     *              - name
     *              - contact_id
     *
     *          Plus if contact_id > 0
     *              - photo_url_16
     * @throws waDbException
     * @throws waException
     */
    protected function buildToList(array $recipients, $contact)
    {
        //
        $to_list = [];
        foreach ($recipients as $idx => $recipient) {
            if ($recipient['type'] === crmMessageRecipientsModel::TYPE_TO) {
                $to_list[] = $recipient;
                unset($recipients[$idx]);
            }
        }

        $to_list[] = array_merge((new crmMessageRecipientsModel())->getEmptyRow(), [
            'destination' => $contact['email'],
            'name' => $contact['name'],
            'photo_url_16' => $contact['photo_url_16'],
        ]);

        return $to_list;
    }

    protected function getFunnel($deal)
    {
        $funnels = array();
        if (isset($deal['funnel_id'])) {
            if ($deal['funnel_id'] && empty($funnels[$deal['funnel_id']])) {
                $funnel = $this->getFunnelModel()->getById($deal['funnel_id']);
                $funnel['stages'] = $this->getFunnelStageModel()->getStagesByFunnel($funnel);
                $funnels[$deal['funnel_id']] = $funnel;
            }
        }
        return $funnels;
    }

    protected function getRecipientsByMessage()
    {
        $message = $this->getMessage();
        $mrm = new crmMessageRecipientsModel();
        $all_recipients = [];

        if ($message['transport'] === crmMessageModel::TRANSPORT_EMAIL && $message['direction'] === crmMessageModel::DIRECTION_IN && $message['source_id'] > 0) {
            try {
                $source = crmEmailSource::factory($message['source_id']);
                $source_email = $source->getEmail();
                $all_recipients[$source_email] = array_merge($mrm->getEmptyRow(), [
                    'destination' => $source_email,
                    'name' => $source_email,
                    'type' => crmMessageRecipientsModel::TYPE_TO
                ]);
            } catch (crmSourceException $ex) {}
        }

        $recipients = $mrm->getRecipients($message['id']);
        foreach ($recipients as $recipient) {
            if ($recipient['destination'] == $recipient['contact_id']) {
                unset($recipients[$recipient['destination']]);
                continue;
            }

            if ($recipient['type'] == "TO" || $recipient['type'] == "FROM") {
                unset($recipients[$recipient['destination']]);
            }
        }
        unset($recipients[$message['to']]);

        return array_merge($all_recipients, $recipients);
    }

    /**
     * @return array|null
     * @throws crmAccessDeniedException
     */
    protected function getMessage()
    {
        if ($this->message) {
            return $this->message;
        }
        $id = (int)$this->getParameter('message_id');
        if ($id <= 0) {
            $this->messageNotFound();
        }

        $message = $this->getMessageModel()->getMessage($id);

        // Check rights
        $has_access = $this->getCrmRights()->canViewMessage($message);
        if (!$has_access) {
            $this->accessDenied();
        }

        return $this->message = $message;
    }

    protected function getCleanDealData()
    {
        $funnel = $this->getFunnelModel()->getAvailableFunnel();
        if (!$funnel) {
            return null;
        }

        $stage_id = $this->getFunnelStageModel()->select('id')->where(
            'funnel_id = '.(int)$funnel['id']
        )->order('number')->limit(1)->fetchField('id');

        // Just empty deal, for new message
        $now = date('Y-m-d H:i:s');
        $deal = $this->getDealModel()->getEmptyDeal();
        $deal = array_merge($deal, array(
            'creator_contact_id' => wa()->getUser()->getId(),
            'create_datetime'    => $now,
            'update_datetime'    => $now,
            'funnel_id'          => $funnel['id'],
            'stage_id'           => $stage_id,
        ));

        $funnels = $this->getFunnelModel()->getAllFunnels(true);
        if (empty($funnels[$deal['funnel_id']])) {
            return null;
        }

        $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnels[$deal['funnel_id']]);

        return array(
            'deal'    => $deal,
            'funnels' => $funnels,
            'stages'  => $stages,
        );
    }

    private function messageNotFound()
    {
        $this->notFound(_w('Message not found'));
    }
}
