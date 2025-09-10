<?php

class crmAutocompleteSidebarController extends crmAutocompleteController
{
    public function execute()
    {
        $data = $this->getContacts($this->getTerm());

        $fields = $this->getJoinFields();
        if ($fields) {
            $data = $this->join($data, $fields);
        }
        $data = array_merge($data, $this->getDeals($this->getTerm()), $this->getInvoices($this->getTerm()));

        die(json_encode($data));
    }

    public function getDeals($q)
    {
        if (strlen($q) <= 0) {
            return [];
        }
        $dm = new crmDealModel();
        $res = $dm->select('id, name')->where("name LIKE '%".$dm->escape($q)."%'")->limit($this->limit)->fetchAll('id');

        $tm = new crmTagModel();
        $sql = "SELECT ct.contact_id, t.name
            FROM crm_contact_tags AS ct
            INNER JOIN crm_tag AS t ON t.id=ct.tag_id
            WHERE t.name LIKE '%".$tm->escape($q, 'like')."%' AND ct.contact_id < 0
            ORDER BY contact_id
            LIMIT ".$this->limit;
        $tags = $tm->query($sql)->fetchAll();

        $term_safe = htmlspecialchars($q);

        foreach ($tags as $t) {
            $deal_id = abs($t['contact_id']);
            $tag = $this->prepare($t['name'], $term_safe);
            $res[$deal_id]['tags'] = isset($res[$deal_id]['tags']) ? $res[$deal_id]['tags'].' '.$tag : $tag;
        }
        if (empty($res)) {
            return [];
        }

        $deals = $dm->getList([
            'id'           => array_keys($res),
            'limit'        => $this->limit,
            'check_rights' => true
        ]);
        $funnels = (new crmFunnelModel)->getAllFunnels(true);

        $out = [];
        foreach ($res as $deal_id => $r) {
            if (empty($deals[$deal_id])) {
                continue;
            }
            $label = isset($r['name']) ? $this->prepare($r['name'], $term_safe) : $this->prepare(ifset($deals[$deal_id]['name']), $term_safe);
            $deal_name = ifset($r, 'name', $deals[$deal_id]['name']);
            if (!empty($r['tags'])) {
                $label .= ' <i class="icon16 tags"></i>'.$r['tags'];
            }
            $funnel = ifset($funnels[$deals[$deal_id]['funnel_id']], null);
            $icon = ifset($funnel, 'icon', 'fas fa-briefcase');
            if ($deals[$deal_id]['status_id'] == crmDealModel::STATUS_WON) {
                $icon = 'fas fa-flag-checkered';
            } elseif ($deals[$deal_id]['status_id'] == crmDealModel::STATUS_LOST) {
                $icon = 'fas fa-ban';
            }
            $label_string = (wa()->whichUI() === '2.0') ? '<div class = "c-layout-deal"><span class="larger custom-mr-6" style="color: '.ifset($funnel, 'color', 'lightgray').';"><i class="'.$icon.'"></i></span><span class = "c-layout-deal-name">'.$label.'</span></div>' : '<div class = "c-layout inline"><div class = "c-column" style = "width: 16px;padding: 0 4px 0 0;"><i class = "icon16 funnel" style = "margin: 0; top: 0;"></i></div><div class = "c-column middle">'.$label.'</div></div>';

            $out[] = [
                'label' => $label_string,
                'link'  => 'deal/'.$deal_id.'/',
                'name' => $deal_name,
                'id' => $deal_id,
                'icon' => $icon,
                'color' => ifset($funnel, 'color', 'lightgray'),
            ];
        }

        return $out;
    }

    public function getInvoices($q)
    {
        if (strlen($q) <= 0) {
            return array();
        }
        $im = new crmInvoiceModel();
        $res = $im->select('id, number, create_datetime, amount, currency_id')->where(
            "number LIKE '%".$im->escape($q)."%'"
        )->limit($this->limit)->fetchAll();
        $term_safe = htmlspecialchars($q);
        foreach ($res as &$r) {
            $_label = $this->prepare($r['number'], $term_safe) .' <i>'.wa_date('date', $r['create_datetime']).'</i>&nbsp;' .waCurrency::format('%{s}', $r['amount'], $r['currency_id']);
            $r['label'] = '<div class="c-layout inline"><div class="c-column" style="width: 16px;padding:0 4px 0 0;"><i class="icon16 invoice" style="margin: 0; top: 0;"></i></div><div class="c-column middle">'. $_label .'</div></div>';
            $r['link'] = 'invoice/'.$r['id'].'/';
        }
        unset($r);
        unset($_label);

        return $res;
    }
}