<?php

class crmTelegramPluginContactSearcher
{
    protected $telegram_info;
    protected $options;

    public function __construct($telegram_info, $options = array())
    {
        $this->telegram_info = $telegram_info;
        $this->options = $options;
    }

    /**
     * @return crmContact|null
     */
    public function findByTelegramIds()
    {
        $telegram_ids = array();
        if (!isset($this->telegram_info['id'])) {
            return null;
        }
        $telegram_ids[] = $this->telegram_info['id'];
        if (isset($this->telegram_info['username'])) {
            $telegram_ids[] = $this->telegram_info['username'];
        }

        $telegram_ids = array_unique($telegram_ids);
        if (!$telegram_ids) {
            return null;
        }

        $cdm = new waContactDataModel();

        $likes = array();
        foreach ($telegram_ids as $telegram_id) {
            $v = $cdm->escape($telegram_id, 'like');
            $likes[] = "value LIKE '%{$v}%'";
        }

        $im = join(' AND ', array(
            "field='im'",
            "ext IN (:exts)",
            '(' . join(' OR ', $likes) . ')'
        ));

        $socialnetwork = join(' AND ', array(
            "field='socialnetwork'",
            "ext IN (:exts)",
            '(' . join(' OR ', $likes) . ')'
        ));

        $t_id = join(' AND ', array(
            "field='telegram_id'",
            '(' . join(' OR ', $likes) . ')'
        ));

        $items = $cdm->select('contact_id, value')
                     ->where($socialnetwork, array('exts' => array('T', 'Telegram', 'Телеграм')))
                     ->fetchAll();

        if (empty($items)) {
            $items = $cdm->select('contact_id, value')
                         ->where($im, array('exts' => array('T', 'Telegram', 'Телеграм')))
                         ->fetchAll();
        }

        if (empty($items)) {
            $items = $cdm->select('contact_id, value')
                         ->where($t_id)
                         ->fetchAll();
        }

        $contact_ids = array();
        foreach ($items as $item) {
            $telegram_value = trim($item['value']);
            if ($this->looksLikeUrl($telegram_value)) {
                $telegram_value = $this->cleanUrlFromSchema($telegram_value);
                $telegram_value = substr($telegram_value,strrpos($telegram_value,"/")+1);
                $telegram_value = rtrim($telegram_value, '/');
            }
            if (in_array($telegram_value, $telegram_ids)) {
                $contact_ids[] = $item['contact_id'];
            }
        }

        if (!$contact_ids) {
            return null;
        }

        $cm = new waContactModel();
        $contact_id = $cm->select('id')
                         ->where('id IN (:ids) AND is_company = :is_company', array(
                             'ids'        => $contact_ids,
                             'is_company' => ifset($this->options['is_company']) ? 1 : 0
                         ))->fetchField();

        if ($contact_id <= 0) {
            return null;
        }

        $contact = new crmContact($contact_id);
        if (empty($contact) || !$contact->exists()) {
            return null;
        }
        return $contact;
    }

    private function cleanUrlFromSchema($url)
    {
        $url = trim($url);
        if (substr($url, 0, 7) === 'http://') {
            return substr($url, 7);
        } elseif (substr($url, 0, 8) === 'https://') {
            return substr($url, 8);
        } else {
            return $url;
        }
    }

    private function looksLikeUrl($url)
    {
        $url = trim($url);
        return substr($url, 0, 7) === 'http://' || substr($url, 0, 8) === 'https://';
    }
}