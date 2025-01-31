<?php

class crmConversationInfoMethod extends crmMessageListMethod
{
    protected $method = self::METHOD_GET;
    const MAX_LIMIT = 500;
    const DEFAULT_LIMIT = 30;

    public function execute()
    {
        $conversation_id = $this->get('id', true);

        $conversation = $this->getConversationModel()->getConversation($conversation_id);
        if (empty($conversation)) {
            throw new waAPIException('not_found', _w('Conversation not found.'), 404);
        }

        if (!$this->getCrmRights()->canViewConversation($conversation)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        // Get messages
        $limit = waRequest::get('limit', self::DEFAULT_LIMIT, waRequest::TYPE_INT);
        $limit = ($limit > self::MAX_LIMIT || $limit <= 0 ? self::DEFAULT_LIMIT : $limit);

        $mm = new crmMessageModel();
        $messages = $mm->select('SQL_CALC_FOUND_ROWS *')->where('conversation_id='.(int)$conversation_id)->order('id DESC')->limit((int)$limit)->fetchAll();
        $count = (int) $mm->query('SELECT FOUND_ROWS()')->fetchField();
        array_multisort(array_column($messages, 'id'), $messages);
        list($messages, $contacts, $sources) = $this->prepareMessages($messages, $conversation);

        $conversation = $this->workupConversation($conversation, $messages, $contacts, $sources);
        $conversation = $this->filterFields(
            $conversation,
            ['id', 'create_datetime', 'update_datetime', 'source', 'supported_features', 'type', 'contact', 'user', 'deal', 'summary', 'last_message_id', 'count', 'is_closed', 'read', 'icon_url', 'icon', 'icon_color', 'icon_fab', 'transport_name'],
            ['id' => 'integer', 'count' => 'integer', 'last_message_id' => 'integer', 'is_closed' => 'boolean', 'read' => 'boolean', 'create_datetime' => 'datetime', 'update_datetime' => 'datetime']
        );

        if (!empty($conversation['summary'])) {
            if (mb_strpos($conversation['summary'], '[image]') === 0) {
                $conversation['summary'] = _w('Image').mb_substr($conversation['summary'], mb_strlen('[image]'));
            } elseif (mb_strpos($conversation['summary'], '[video]') === 0) {
                $conversation['summary'] = _w('Video').mb_substr($conversation['summary'], mb_strlen('[video]'));
            } elseif (mb_strpos($conversation['summary'], '[audio]') === 0) {
                $conversation['summary'] = _w('Audio').mb_substr($conversation['summary'], mb_strlen('[audio]'));
            } elseif (mb_strpos($conversation['summary'], '[file]') === 0) {
                $conversation['summary'] = _w('File').mb_substr($conversation['summary'], mb_strlen('[file]'));
            } elseif (mb_strpos($conversation['summary'], '[geolocation]') === 0) {
                $conversation['summary'] = _w('Geolocation').mb_substr($conversation['summary'], mb_strlen('[geolocation]'));
            } elseif (mb_strpos($conversation['summary'], '[sticker]') === 0) {
                $conversation['summary'] = _w('Sticker').mb_substr($conversation['summary'], mb_strlen('[sticker]'));
            } elseif ($conversation['summary'] === '[unsupported]') {
                $conversation['summary'] = _w('Unsupported message');
            } elseif ($conversation['summary'] === '[error]') {
                $conversation['summary'] = _w('Error');
            } elseif ($conversation['summary'] === '[empty]') {
                $conversation['summary'] = _w('Empty message');
            }
        }

        $this->response = [
            'conversation' => $conversation,
            'params' => [
                'limit' => $limit,
                'total_count' => $count,
            ],
            'messages' => $messages,
            'can_edit' => $this->getCrmRights()->canEditConversation($conversation),
        ];

        $this->getMessageReadModel()->setReadConversation($conversation['id']);
    }

    protected function workupConversation($conversation, $messages, $contacts, $sources)
    {
        $message_ids = array_column($messages, 'id');
        if (!empty($message_ids)) {
            $conversation['last_message_id'] = intval(end($message_ids));
        }

        if (isset($conversation['contact_id']) && isset($contacts[$conversation['contact_id']])) {
            $conversation['contact'] = $contacts[$conversation['contact_id']];
        }
        if (isset($conversation['user_contact_id']) && isset($contacts[$conversation['user_contact_id']])) {
            $conversation['user'] = $contacts[$conversation['user_contact_id']];
        }
        if (isset($conversation['source_id']) && isset($sources[$conversation['source_id']])) {
            $conversation['source'] = $this->prepareSource($sources[$conversation['source_id']]);
        }
        if (isset($conversation['deal']['contact_id'], $contacts[$conversation['deal']['contact_id']])) {
            $conversation['deal']['contact'] = $contacts[$conversation['deal']['contact_id']];
            $conversation['deal'] = $this->prepareDealShort($conversation['deal']);
        }

        $conversation['icon_url'] = null;
        $conversation['icon'] = 'exclamation-circle';
        $conversation['transport_name'] = _w('Unknown');
        $conversation['icon_color'] = '#BB64FF';
        $conversation['supported_features'] = [
            'html' => false,
            'attachments' => false,
            'images' => false,
        ];

        if ($conversation['type'] == crmMessageModel::TRANSPORT_EMAIL) {
            $conversation['icon'] = 'envelope';
            $conversation['transport_name'] = 'Email';
            $conversation['supported_features'] = [
                'html' => true,
                'attachments' => true,
                'images' => false,
            ];
        } elseif ($conversation['type'] == crmMessageModel::TRANSPORT_SMS) {
            $conversation['icon'] = 'mobile';
            $conversation['transport_name'] = 'SMS';
        }
        if ($conversation['source_id']) {
            $source_helper = crmSourceHelper::factory(crmSource::factory($conversation['source_id']));
            $res = $source_helper->workupConversationInList($conversation);
            $conversation = $res ? $res : $conversation;
            $conversation['supported_features'] = $source_helper->getFeatures();
        }

        if ($conversation['deal_id']) {
            $deal = $this->getDealModel()->getById($conversation['deal_id']);
            if (!empty($deal)) {
                $deal['funnel'] = $this->getFunnelModel()->getById($deal['funnel_id']);
                $deal['stage'] = $this->getFunnelStageModel()->getById($deal['stage_id']);
                $conversation['deal'] = $this->prepareDealShort($deal);
            }
        }

        return $conversation;
    }
}
