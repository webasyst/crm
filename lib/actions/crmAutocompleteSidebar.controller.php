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
            return array();
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
        if ($res) {
            $deals = $dm->select('id, name')->where("id IN(".join(",", array_keys($res)).")")->limit($this->limit)->fetchAll('id');
        }
        $out = array();
        foreach ($res as $deal_id => $r) {
            $label = isset($r['name']) ? $this->prepare($r['name'], $term_safe) : $this->prepare(ifset($deals[$deal_id]['name']), $term_safe);
            if (!empty($r['tags'])) {
                $label .= ' <i class="icon16 tags"></i>'.$r['tags'];
            }
            $out[] = array(
                'label' => '<div class = "c-layout inline"><div class = "c-column" style = "width: 16px;padding: 0 4px 0 0;"><i class = "icon16 funnel" style = "margin: 0; top: 0;"></i></div><div class = "c-column middle">'.$label.'</div></div>',
                'link'  => 'deal/'.$deal_id.'/'
            );
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