<?php

class crmConversationListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $offset = max(0, (int) waRequest::get('offset', 0, waRequest::TYPE_INT));
        $limit = waRequest::get('limit', crmConfig::ROWS_PER_PAGE, waRequest::TYPE_INT);
        $transport_filter = waRequest::get('transport', null, waRequest::TYPE_STRING_TRIM);
        $responsible_filter = waRequest::get('user_id', null, waRequest::TYPE_INT);
        $contact_filter = waRequest::get('contact_id', null, waRequest::TYPE_INT);
        $userpic_size = waRequest::get('userpic_size', self::USERPIC_SIZE, waRequest::TYPE_INT);

        if ($transport_filter && !in_array($transport_filter, ['email', 'im'])) {
            throw new waAPIException('invalid_transport', _w('Unknown transport.'), 400);
        }

        $list_params = [
            'check_rights' => true,
            'limit' => $limit,
            'offset' => $offset,
        ];
        if (!empty($transport_filter)) {
            $list_params['transport'] = $transport_filter;
        }

        $user_ids = array_keys($this->getConversationModel()->select('DISTINCT(user_contact_id) id')->where('user_contact_id IS NOT NULL AND user_contact_id <> 0')->fetchAll('id'));

        if (isset($responsible_filter)) {
            $list_params['responsible'] = $responsible_filter;
        }
        if (isset($contact_filter)) {
            $list_params['contact_id'] = $contact_filter;
        }

        $total_count = 0;
        $conversations = $this->getConversationModel()->getList($list_params, $total_count);
        $contact_ids = array_merge(array_column($conversations, 'contact_id'), $user_ids);
        $contacts = $this->getContacts($contact_ids, $userpic_size);
        $allowed = $this->getCrmRights()->dropUnallowedConversations($conversations);
        $active_sources = $this->getSourceModel()->getByField(['type' => [crmSourceModel::TYPE_EMAIL, crmSourceModel::TYPE_IM]], true);

        $deals = $this->getDealModel()->getByField(['id' => array_column($conversations, 'deal_id')], true);
        $deal_contact_ids = array_diff(array_column($deals, 'contact_id'), $contact_ids);
        if ($deal_contact_ids) {
            $contacts += $this->getContacts($deal_contact_ids, $userpic_size);
        }
        $funnels = $this->getFunnelModel()->getAllFunnels(true);
        $funnels = $this->getFunnelStageModel()->withStages($funnels);

        $conversations = array_values(array_map(function ($el) use ($allowed, $active_sources, $contacts, $deals, $funnels) {
            if (ifset($el['icon_fa'])) {
                $el['icon'] = $el['icon_fa'];
            }
            $el = $this->filterFields(
                $el,
                ['id', 'create_datetime', 'update_datetime', 'source_id', 'type', 'contact_id', 'user_contact_id', 'deal_id', 'summary', 'last_message_id', 'count', 'is_closed', 'read', 'icon_url', 'icon', 'icon_color', 'icon_fab', 'transport_name'],
                ['id' => 'integer', 'count' => 'integer', 'last_message_id' => 'integer', 'is_closed' => 'boolean', 'read' => 'boolean', 'create_datetime' => 'datetime', 'update_datetime' => 'datetime']
            );
            $el['can_view'] = !empty($allowed[$el['id']]);
            if (ifset($el['source_id'])) {
                $source_idx = array_search($el['source_id'], array_column($active_sources, 'id'));
                if ($source_idx !== false) {
                    $source = $this->prepareSource($active_sources[$source_idx]);
                    if (isset($el['icon_url'])) {
                        $source['icon_url'] = $el['icon_url'];
                    }
                    $plugin = crmSourcePlugin::factory($source['provider'])
                        and $source_obj = $plugin->factorySource($el['source_id'])
                        and $source += $source_obj->getFontAwesomeBrandIcon();
                    $el['source'] = $source;
                }
            }
            unset($el['source_id']);
            if (isset($el['contact_id'], $contacts[$el['contact_id']])) {
                $el['contact'] = $contacts[$el['contact_id']];
            }
            unset($el['contact_id']);
            if (isset($el['user_contact_id'], $contacts[$el['user_contact_id']])) {
                $el['user'] = $contacts[$el['user_contact_id']];
            }
            unset($el['user_contact_id']);
            if (ifset($el['deal_id'])) {
                $deal_idx = array_search($el['deal_id'], array_column($deals, 'id'));
                if ($deal_idx !== false) {
                    $deal = $deals[$deal_idx];
                    $deal['funnel']  = ifset($funnels, $deal['funnel_id'], null);
                    $deal['stage']   = ifset($funnels, $deal['funnel_id'], 'stages', $deal['stage_id'], null);
                    $deal['contact'] = ifset($contacts, $deal['contact_id'], null);
                    $el['deal'] = $this->prepareDealShort($deal);
                }
            }
            unset($el['deal_id']);
            if (!empty($el['summary'])) {
                if (mb_strpos($el['summary'], '[image]') === 0) {
                    $el['summary'] = _w('Image').mb_substr($el['summary'], mb_strlen('[image]'));
                } elseif (mb_strpos($el['summary'], '[video]') === 0) {
                    $el['summary'] = _w('Video').mb_substr($el['summary'], mb_strlen('[video]'));
                } elseif (mb_strpos($el['summary'], '[audio]') === 0) {
                    $el['summary'] = _w('Audio').mb_substr($el['summary'], mb_strlen('[audio]'));
                } elseif (mb_strpos($el['summary'], '[file]') === 0) {
                    $el['summary'] = _w('File').mb_substr($el['summary'], mb_strlen('[file]'));
                } elseif (mb_strpos($el['summary'], '[geolocation]') === 0) {
                    $el['summary'] = _w('Geolocation').mb_substr($el['summary'], mb_strlen('[geolocation]'));
                } elseif (mb_strpos($el['summary'], '[sticker]') === 0) {
                    $el['summary'] = _w('Sticker').mb_substr($el['summary'], mb_strlen('[sticker]'));
                } elseif ($el['summary'] === '[unsupported]') {
                    $el['summary'] = _w('Unsupported message');
                } elseif ($el['summary'] === '[error]') {
                    $el['summary'] = _w('Error');
                } elseif ($el['summary'] === '[empty]') {
                    $el['summary'] = _w('Empty message');
                }
            }
            return $el;
        }, $conversations));

        $this->response = [
            'params' => [
                'total_count' => intval($total_count),
                'limit' => intval($limit),
                'offset' => intval($offset),
                'filter' => [
                    'transport' => $transport_filter,
                    'user_id' => $responsible_filter,
                    'contact_id' => $contact_filter,
                ]
            ],
            'data' => $conversations,
        ];
    }

    private function getContacts($contact_ids, $userpic_size)
    {
        $contacts = $this->getContactsMicrolist(
            $contact_ids,
            ['id', 'name', 'userpic'],
            $userpic_size
        );
        $contacts = array_reduce($contacts, function ($result, $el) {
            $result[$el['id']] = $el;
            return $result;
        }, []);

        return $contacts;
    }
}
