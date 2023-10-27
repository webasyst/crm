<?php

class crmInvoiceAutocompleteController extends waController
{
    protected $term;
    protected $limit = 10;

    public function execute()
    {
        $data = $this->getInvoices($this->getTerm());

        die(json_encode($data));
    }

    /**
     * @return string
     */
    protected function getTerm()
    {
        return $this->term !== null ? $this->term : ($this->term = trim((string)$this->getRequest()->request('term')));
    }

    public function getInvoices($q)
    {
        if (strlen($q) <= 0) {
            return array();
        }
        $im = new crmInvoiceModel();
        $res = $im->select('id, number, create_datetime, amount, currency_id, state_id, contact_id')->where(
            "number LIKE '%".$im->escape($q)."%'"
        )->limit($this->limit)->fetchAll();

        $contact_ids = array();
        foreach ($res as &$r) {
            $contact_ids[$r['contact_id']] = $r['contact_id'];
        }
        unset($r);
        $collection = new crmContactsCollection(array_keys($contact_ids));
        $contacts = $collection->getContacts('name', 0, count($contact_ids));

        $term_safe = htmlspecialchars($q);
        foreach ($res as &$r) {
            $_name = ifset($contacts[$r['contact_id']]['name']);
            if (!empty($_name)) { $_name = htmlspecialchars($_name); }

            $icon_class = wa()->whichUI() === '1.3' ? 'icon16 invoice' : 'fas fa-file-invoice-dollar custom-mr-8';
            $r['label'] = ''
                .'<i class="'.$icon_class.'"></i>'
                .'<span class="c-number bold '.strtolower($r['state_id']).'">'.$this->prepare($r['number'], $term_safe).'</span> '
                .'<span class="c-date hint">'.wa_date('date', $r['create_datetime']).'</span> '
                .'<span class="c-price nowrap">'.waCurrency::format('%{s}', $r['amount'], $r['currency_id']).'</span> '
                .'<span class="c-user small">'. $_name .'</span>';
            $r['link'] = 'invoice/'.$r['id'].'/';
        }
        unset($r);

        return $res;
    }

    protected function prepare($str, $term_safe, $escape = true)
    {
        $pattern = '~('.preg_quote($term_safe, '~').')~ui';
        $template = '<span class="bold highlighted">\1</span>';
        if ($escape) {
            $str = htmlspecialchars($str, ENT_QUOTES, 'utf-8');
        }
        return preg_replace($pattern, $template, $str);
    }
}
