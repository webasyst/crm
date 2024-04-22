<?php

class crmMessageInfoMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $messgae_id = waRequest::get('id', 0, waRequest::TYPE_INT);
        $userpic_size = waRequest::get('userpic_size', 32, waRequest::TYPE_INT);

        if ($messgae_id < 1) {
            throw new waAPIException('not_found', _w('Message not found'), 404);
        }

        $message = $this->getMessageModel()->getById($messgae_id);
        if (!$message) {
            throw new waAPIException('not_found', _w('Message not found'), 404);
        } else if (empty($message['contact_id'])) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } else if (!$this->getCrmRights()->canViewMessage($message)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        $message['id'] = (int) $message['id'];
        $message['original'] = boolval($message['original']);
        $message['conversation_id'] = (int) $message['conversation_id'];
        $message['creator_contact_id'] = (int) $message['creator_contact_id'];
        $message['create_datetime'] = $this->formatDatetimeToISO8601($message['create_datetime']);
        $message['params'] = $this->getParams($message['id']);
        $message['attachments'] = $this->getAttachments($message['id']);
        $message['source'] = $this->getSource($message['source_id']);
        $message['body_sanitized'] = $this->bodySanitizer($message);

        $source_helper = crmSourceHelper::factory(crmSource::factory($message['source_id']));
        $res = $source_helper->normalazeMessagesExtras([$message]);
        $message = $res ? reset($res) : $message;

        $contact_ids = [
            $message['contact_id'],
            $message['creator_contact_id']
        ];
        if ($message['transport'] === crmMessageModel::TRANSPORT_EMAIL) {
            $recipients = $this->getRecipients($message['id']);
            $contact_ids = array_merge($contact_ids, array_column($recipients, 'contact_id'));
        }
        if ($message['deal_id']) {
            $deal = $this->getDeal($message['deal_id']);
            $contact_ids = array_merge($contact_ids, [$deal['contact_id']]);
        }
        $contact_ids = array_unique($contact_ids);
        $contacts = $this->getContacts($contact_ids, $userpic_size);
        $message['contact'] = ifempty($contacts, $message['contact_id'], 0);
        $message['author'] = ifempty($contacts, $message['creator_contact_id'], 0);
        if (!empty($recipients)) {
            $message['recipients'] = $this->getRecipientsFormat($recipients, $contacts);
        }
        if (!empty($deal)) {
            $deal['contact'] = ifset($contacts, $deal['contact_id'], []);
            $message['deal'] = $this->prepareDealShort($deal);
        }

        unset(
            $message['contact_id'],
            $message['deal_id'],
            $message['source_id'],
            $message['event'],
            $message['params']
        );

        $this->response = $message;
    }

    private function getContacts($contact_ids, $userpic_size = 32)
    {
        $result = [];
        if (!empty($contact_ids)) {
            $contacts = $this->getContactsMicrolist($contact_ids, ['id', 'name', 'userpic'], $userpic_size);
            foreach ($contacts as $contact) {
                $result[$contact['id']] = $contact;
            }
        }

        return $result;
    }

    private function getRecipients($message_id)
    {
        if ($message_id < 1) {
            return null;
        }
        $mrm = new crmMessageRecipientsModel();
        return $mrm->getByField(['message_id' => $message_id], true);
    }

    private function getRecipientsFormat($recipients, $contacts)
    {
        $result = [];
        foreach ($recipients as $recipient) {
            switch ($recipient['type']) {
                case crmMessageRecipientsModel::TYPE_TO:
                    $result[crmMessageRecipientsModel::TYPE_TO][] = [
                        'name' => ifset($recipient, 'name', ''),
                        'contact' => ifempty($contacts, $recipient['contact_id'], 0),
                        'destination' => ifset($recipient, 'destination', ''),
                    ];
                    break;
                case crmMessageRecipientsModel::TYPE_CC:
                    $result[crmMessageRecipientsModel::TYPE_CC][] = [
                        'name' => ifset($recipient, 'name', ''),
                        'contact' => ifempty($contacts, $recipient['contact_id'], 0),
                        'destination' => ifset($recipient, 'destination', ''),
                    ];
                    break;
                case crmMessageRecipientsModel::TYPE_BCC:
                    $result[crmMessageRecipientsModel::TYPE_BCC][] = [
                        'name' => ifset($recipient, 'name', ''),
                        'contact' => ifempty($contacts, $recipient['contact_id'], 0),
                        'destination' => ifset($recipient, 'destination', ''),
                    ];
                    break;
            }
        }

        return (empty($result) ? null : $result);
    }

    private function getParams($message_id)
    {
        if ($message_id < 1) {
            return [];
        }
        $message_params = $this->getMessageParamsModel()->getByField(['message_id' => $message_id], true);
        foreach ($message_params as &$message_param) {
            $message_param = [
                'name'  => ifset($message_param, 'name', ''),
                'value' => ifset($message_param, 'value', '')
            ];
        }

        return $message_params;
    }

    protected function getAttachments($message_id)
    {
        if ($message_id < 1) {
            return [];
        }
        $attachments = parent::getAttachments([$message_id]);

        return (empty($attachments) ? [] : reset($attachments));
    }

    private function getSource($source_id)
    {
        $source_id = (int) ifempty($source_id, 0);
        if ($source_id < 1) {
            return null;
        }
        $sources = $this->getSourceModel()->getByField(['id' => $source_id], true);
        $source = reset($sources);
        $provider = ifset($source, 'provider', '');

        return [
            'id'       => (int) ifset($source, 'id', 0),
            'type'     => ifset($source, 'type', ''),
            'name'     => ifset($source, 'name', ''),
            'provider' => $provider,
            'icon_url' => wa()->getAppStaticUrl('crm/plugins/'.$provider.'/img', true).$provider.'.png'
        ];
    }

    private function bodySanitizer($message)
    {
        $domain = wa()->getUrl(true);
        $replace_img_src = array_reduce($message['attachments'], function ($res, $el) use ($message, $domain) {
            $res[$el['id']] = $domain.'api.php/crm.file.download?id='.$el['id'];
            return $res;
        }, []);

        return crmHtmlSanitizer::work($message['body'], [
            'replace_img_src' => $replace_img_src,
        ]);
    }

    private function getDeal($deal_id)
    {
        if ($deal_id < 1) {
            return null;
        }

        $deal = $this->getDealModel()->getById($deal_id);
        if ($deal) {
            $funnel = $this->getFunnelModel()->getById($deal['funnel_id']);
            $deal['funnel'] = [
                'id'    => (int) $funnel['id'],
                'name'  => $funnel['name'],
                'color' => $funnel['color']
            ];
            $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnel);
            foreach ($stages as $_stage) {
                if ($_stage['id'] == $deal['stage_id']) {
                    $stage = $_stage;
                    break;
                }
            }
            if (!empty($stage)) {
                $deal['stage'] = [
                    'id'    => (int) $stage['id'],
                    'name'  => $stage['name'],
                    'color' => $stage['color']
                ];
            }

            return $deal;
        }

        return null;
    }
}

