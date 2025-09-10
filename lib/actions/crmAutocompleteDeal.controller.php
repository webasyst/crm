<?php

class crmAutocompleteDealController extends crmAutocompleteController
{
    const USERPIC = 32;

    public function execute()
    {
        $term = $this->getTerm();
        $with_closed = (bool) waRequest::get('with_closed', false);
        $with_contact = (bool) waRequest::get('with_contact', false);
        $deals = $this->getDeals($term, $with_closed, $with_contact);

        header('Content-type:application/json');

        die(json_encode($deals));
    }

    public function getDeals($term, $with_closed, $with_contact)
    {
        if (strlen($term) <= 0) {
            return [];
        }

        $key_names = [
            'id',
            'name',
            'funnel',
            'stage',
            'status_id',
            'create_datetime',
            'expected_date',
            'closed_datetime',
            'amount',
            'currency_id'
        ];

        $deal_model = new crmDealModel();
        if ($with_contact) {
            $where = '(cd.name LIKE s:like OR wc.name LIKE s:like)';
            $key_names[] = 'contact';
        } else {
            $where = 'cd.name LIKE s:like';
        }
        if (!$with_closed) {
            $where .= " AND cd.status_id = 'OPEN'";
        }
        $deals = $deal_model->query("
            SELECT cd.id, cd.name, cd.status_id, cd.funnel_id, cd.stage_id, cd.contact_id, cd.create_datetime, cd.expected_date, cd.closed_datetime, cd.amount, cd.currency_id,  wc.name AS contact_name
            FROM crm_deal cd
            LEFT JOIN wa_contact wc ON wc.id = cd.contact_id
            WHERE $where
            LIMIT i:limit
        ", [
            'like'  => '%'.$deal_model->escape($term).'%',
            'limit' => $this->limit
        ])->fetchAll();

        $funnel_model = new crmFunnelModel();
        $stage_model = new crmFunnelStageModel();
        $funnels = $funnel_model->getById(array_column($deals, 'funnel_id'));
        $funnels_with_stages = $stage_model->withStages($funnels);
        $sample = array_fill_keys($key_names, '');
        $deals = array_map(function ($deal) use ($funnels_with_stages, $with_contact, $sample) {
            if ($with_contact) {
                $deal['contact'] = [
                    'id'      => $deal['contact_id'],
                    'name'    => $deal['contact_name'],
                    'userpic' => rtrim(wa()->getConfig()->getHostUrl(), '/').(new crmContact($deal['contact_id']))->getPhoto(self::USERPIC)
                ];
            }
            foreach ($funnels_with_stages as $_funn_with_st) {
                if ($_funn_with_st['id'] == $deal['funnel_id']) {
                    $deal['funnel'] = [
                        'id'    => $_funn_with_st['id'],
                        'name'  => $_funn_with_st['name'],
                        'color' => $_funn_with_st['color'],
                        'icon' => $_funn_with_st['icon'] ?? 'fas fa-briefcase'
                    ];
                    foreach ($_funn_with_st['stages'] as $_stage) {
                        if ($_stage['id'] == $deal['stage_id']) {
                            $deal['stage'] = [
                                'id'    => $_stage['id'],
                                'name'  => $_stage['name'],
                                'color' => $_stage['color']
                            ];
                            break;
                        }
                    }
                    break;
                }
            }
            return array_intersect_key($deal, $sample);
        }, $deals);

        return $deals;
    }
}
