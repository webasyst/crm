<?php

class crmDealRecentMethod extends crmApiAbstractMethod
{
    const RECENT_LIMIT = 10;

    public function execute()
    {
        $recent = [];
        $pinned = [];
        $userpic_size = waRequest::get('userpic_size', self::USERPIC_SIZE, waRequest::TYPE_INT);
        $recent_limit = waRequest::get('limit', self::RECENT_LIMIT, waRequest::TYPE_INT);
        $userpic_size = abs(ifempty($userpic_size, self::USERPIC_SIZE));
        $recent_limit = abs(ifempty($recent_limit, self::RECENT_LIMIT));

        $all_recent = $this->getRecentModel()->select('user_contact_id, contact_id, is_pinned')
            ->where('user_contact_id = ?', $this->getUser()->getId())
            ->where('contact_id < 0')
            ->order('is_pinned DESC, view_datetime DESC')
            ->fetchAll('contact_id');

        if (!empty($all_recent)) {
            $deal_ids = array_map('abs', array_keys($all_recent));
            $deals = $this->getDealModel()->getById($deal_ids);
            $contacts = $this->getContactsMicrolist(
                array_column($deals, 'contact_id'),
                ['id', 'name', 'userpic'],
                $userpic_size
            );
            foreach ($all_recent as $_id => $_deal) {
                $_id = abs($_id);
                if (empty($deals[$_id])) {
                    continue;
                }
                $contact_client_id = ifset($deals, $_id, 'contact_id', null);
                $sort_id = array_search($contact_client_id, array_column($contacts, 'id'));
                $_recent = $deals[$_id] + ['contact' => ($sort_id !== false ? $contacts[$sort_id] : [])];
                if (!empty($_deal['is_pinned'])) {
                    $pinned[] = $_recent;
                    continue;
                } elseif ($recent_limit) {
                    $recent[] = $_recent;
                    $recent_limit--;
                    continue;
                }
                $this->getRecentModel()->deleteByField($_deal);
            }
        }

        $this->response = [
            'recent' => $this->filterData(
                $recent,
                [
                    'id',
                    'name',
                    'status_id',
                    'amount',
                    'currency_id',
                    'contact'
                ], [
                    'id'     => 'integer',
                    'amount' => 'float',
                ]
            ),
            'pinned' => $this->filterData(
                $pinned,
                [
                    'id',
                    'name',
                    'status_id',
                    'amount',
                    'currency_id',
                    'contact'
                ], [
                    'id'     => 'integer',
                    'amount' => 'float',
                ]
            )
        ];
    }
}
