<?php

class crmMessageListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;
    const MAX_LIMIT = 500;
    const DEFAULT_LIMIT = 30;

    public function execute()
    {
        $conversation_id = $this->get('conversation_id', true);
        if (!$this->getCrmRights()->canViewConversation($conversation_id)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        $conversation = $this->getConversationModel()->getConversation($conversation_id);
        if (empty($conversation)) {
            throw new waAPIException('not_found', _w('Conversation not found.'), 404);
        }

        $max_id = waRequest::get('max_id', 0, waRequest::TYPE_INT);
        $min_id = waRequest::get('min_id', 0, waRequest::TYPE_INT);
        $limit = waRequest::get('limit', self::DEFAULT_LIMIT, waRequest::TYPE_INT);
        $limit = ($limit > self::MAX_LIMIT || $limit <= 0 ? self::DEFAULT_LIMIT : $limit);

        // Get messages
        $mm = new crmMessageModel();

        $condition = ['1=1'];
        $params = ['limit' => (int)$limit];
        if ($conversation_id) {
            $condition[] = 'conversation_id='.(int)$conversation_id;
            $params['conversation_id'] = (int)$conversation_id;
        }
        $count_condition = $condition;
        if ($max_id) {
            $condition[] = 'id<'.(int)$max_id;
            $params['max_id'] = (int)$max_id;
        }
        if ($min_id) {
            $condition[] = 'id>'.(int)$min_id;
            $params['min_id'] = (int)$min_id;
        }

        $messages = $mm->select('*')->where(implode(' AND ', $condition))->order('id DESC')->limit((int)$limit)->fetchAll();
        array_multisort(array_column($messages, 'id'), $messages);

        $count = (int) $mm->select('COUNT(*)')->where(implode(' AND ', $count_condition))->fetchField();
        $params['total_count'] = $count;
        //$params['count'] = sizeof($messages);

        list($messages, $contacts, $sources) = $this->prepareMessages($messages, $conversation);

        $this->response = [
            'params' => $params,
            'messages' => $messages,
        ];

        if (empty($max_id) && !empty($conversation_id)) {
            $this->getMessageReadModel()->setReadConversation($conversation_id);
        }
    }

    protected function prepareMessages($messages, $conversation)
    {
        $message_ids = array_column($messages, 'id');
        $source_ids = array_column(array_filter($messages, function ($m) {
            return $m['source_id'] > 0;
        }), 'source_id');
        $sources = $this->getSources($source_ids);

        $source_emails = $this->getSourceEmailAddresses($messages);

        $mrm = new crmMessageRecipientsModel();
        $recipients = $mrm->getByField(['message_id' => $message_ids], true);

        $contact_ids = [
            wa()->getUser()->getId(),
            ifset($conversation, 'contact_id', 0),
            ifset($conversation, 'user_contact_id', 0),
            ifset($conversation, 'deal', 'contact_id', 0)
        ];

        $contact_ids = array_filter(
            array_merge(
                $contact_ids,
                array_column($messages, 'contact_id'),
                array_column($messages, 'creator_contact_id'),
                array_column($recipients, 'contact_id')
            ),
            function ($el) {
                return wa_is_int($el);
            }
        );
        $contacts = $this->getContacts($contact_ids);
        $contacts = array_reduce($contacts, function ($result, $el) {
            $result[$el['id']] = $el;
            return $result;
        }, []);

        $recipients = array_reduce($recipients, function($result, $el) use ($contacts) {
            if ($el['type'] == 'FROM' || wa_is_int($el['destination'])) {
                return $result;
            }
            $message_id = $el['message_id'];
            $type = $el['type'];
            if (!isset($result[$message_id])) {
                $result[$el['message_id']] = [];
            }
            if (!isset($result[$message_id][$type])) {
                $result[$message_id][$type] = [];
            }
            if (isset($el['contact_id']) && isset($contacts[$el['contact_id']])) {
                $el['contact'] = $contacts[$el['contact_id']];
            }
            unset($el['contact_id']);
            unset($el['message_id']);
            unset($el['type']);
            $result[$message_id][$type][] = $el;
            return $result;
        }, []);

        $attachments = $this->getAttachments($message_ids);

        $message_params = $this->getMessageParams($message_ids);

        // exclude internal messages
        $messages = array_filter($messages, function ($m) use ($message_params) {
            return !ifset($message_params, $m['id'], 'internal', false);
        });

        $messages = array_map(function ($m) use ($source_emails, $recipients, $contacts, $sources, $attachments, $message_params) {
            $m['recipients'] = ifset($recipients[$m['id']], []);
            $m['params'] = ifset($message_params[$m['id']], []);
            $m['attachments'] = ifset($attachments[$m['id']], []);

            $app_url = wa()->getAppUrl('crm');
            $replace_img_src = array_reduce($m['attachments'], function($res, $el) use ($m, $app_url) {
                if ($m['deal_id'] > 0) {
                    $res[$el['id']] = "{$app_url}deal/{$m['deal_id']}/?module=file&action=download&id={$el['id']}";
                }
                return $res;
            }, []);
            $m['body_sanitized'] = crmHtmlSanitizer::work(
                $m['body'],
                ['replace_img_src' => $replace_img_src, 'hide_verification_links' => true]
            );

            // if message is input and source is of EMAIL type then insert structure in [recipients][to] list
            if ($m['transport'] === crmMessageModel::TRANSPORT_EMAIL &&
                $m['direction'] === crmMessageModel::DIRECTION_IN &&
                isset($source_emails[$m['source_id']])
            ) {
                $source_email = $source_emails[$m['source_id']];
                $m['recipients']['TO'][] = array_merge((new crmMessageRecipientsModel())->getEmptyRow(), [
                    'destination' => $source_email,
                    'name' => $source_email,
                ]);
            }

            if (isset($m['contact_id']) && isset($contacts[$m['contact_id']])) {
                $m['contact'] = $contacts[$m['contact_id']];
            }

            if (isset($m['creator_contact_id']) && isset($contacts[$m['creator_contact_id']])) {
                $m['author'] = $contacts[$m['creator_contact_id']];
            }

            if (isset($m['source_id']) && isset($sources[$m['source_id']])) {
                $m['source'] = $this->prepareSource($sources[$m['source_id']]);
            }

            return $m;
        }, $messages);

        $source_helper = crmSourceHelper::factory(crmSource::factory($conversation['source_id']));
        $res = $source_helper->normalazeMessagesExtras($messages);
        $messages = $res ? $res : $messages;

        $messages = array_map(function ($m) {
            if (ifset($m['extras'])) {
                foreach (['images', 'audios', 'videos', 'stickers'] as $type) {
                    if (ifset($m['extras'][$type])) {
                        $m['extras'][$type] = $this->prepareFiles($m['extras'][$type]);
                    }
                }
            }
            return $m;
        }, $messages);

        $messages = $this->filterData($messages,
            ['id', 'create_datetime', 'creator_contact_id', 'transport', 'direction', 'subject', 'body', 'from', 'to', 'original', 'event', 'recipients', 'attachments', 'extras', 'caption', 'body_sanitized', 'contact', 'author'],
            ['id' => 'integer', 'creator_contact_id' => 'integer', 'original' => 'boolean', 'create_datetime' => 'datetime']
        );

        return [$messages, $contacts, $sources];
    }

    protected function getContacts($ids)
    {
        $collection = new crmContactsCollection('/id/'.join(',', $ids));
        $contacts = $collection->getContacts('name,photo', 0, count($ids));
        return $this->prepareContactsList($contacts, ['id', 'name', 'userpic'], waRequest::get('userpic_size', 32, waRequest::TYPE_INT));
    }

    protected function getSources($source_ids)
    {
        if (empty($source_ids)) {
            return [];
        }

        $sources = $this->getSourceModel()->getByField(['id' => $source_ids], true);

        return array_reduce($sources, function ($result, $el) {
            if ($el['type'] === 'IM' && !empty($el['provider'])) {
                $el['icon_url'] = wa()->getAppStaticUrl('crm/plugins/' . $el['provider'] . '/img', true) . $el['provider'].'.png';
                $plugin = crmSourcePlugin::factory($el['provider'])
                    and $source = $plugin->factorySource($el['id'])
                    and $el += $source->getFontAwesomeBrandIcon();
            }
            $result[$el['id']] = $el;
            return $result;
        }, []);
    }

    protected function getMessageParams($message_ids)
    {
        $message_params = (new crmMessageParamsModel)->getParamsByMessage($message_ids);
        return $message_params;
    }

    protected function getSourceEmailAddresses(array $messages)
    {
        if (empty($messages)) {
            return [];
        }

        // collect source IDs for IN EMAIL messages
        $source_ids = array_column(array_filter($messages, function ($m) {
            return $m['source_id'] > 0
                && $m['transport'] === crmMessageModel::TRANSPORT_EMAIL
                && $m['direction'] === crmMessageModel::DIRECTION_IN;
        }), 'source_id');

        if (empty($source_ids)) {
            return [];
        }

        $email_records = (new crmSourceParamsModel)->getByField([
            'source_id' => $source_ids,
            'name' => 'email'
        ], true);

        return waUtils::getFieldValues($email_records, 'value', 'source_id');
    }
}
