<?php

class crmTwitterPluginContactSearcher
{
    /**
     * @var crmTwitterPluginUser
     */
    protected $twitter_user;

    /**
     * @var array
     */
    protected $options;

    public function __construct($twitter_user, $options = array())
    {
        $this->twitter_user = $twitter_user;
        $this->options = $options;
    }

    public function findByTwitterIds()
    {
        $twitter_ids = array();
        if (!$this->twitter_user->getId()) {
            return null;
        }
        $twitter_ids[] = $this->twitter_user->getId();
        if ($this->twitter_user->getLogin()) {
            $twitter_ids[] = $this->twitter_user->getLogin();
        }

        $twitter_ids = array_unique($twitter_ids);
        if (!$twitter_ids) {
            return null;
        }

        $cdm = new waContactDataModel();

        $likes = array();
        foreach ($twitter_ids as $twitter_id) {
            $v = $cdm->escape($twitter_id, 'like');
            $likes[] = "value LIKE '%{$v}%'";
        }

        $socialnetwork = join(' AND ', array(
            "field='socialnetwork'",
            "ext IN (:exts)",
            '(' . join(' OR ', $likes) . ')'
        ));

        $t_id = join(' AND ', array(
            "field='twitter_id'",
            '(' . join(' OR ', $likes) . ')'
        ));

        $items = $cdm->select('contact_id, value')
                     ->where($socialnetwork, array('exts' => array('Twitter', 'twitter', 'Твиттер', 'твиттер')))
                     ->fetchAll();

        if (empty($items)) {
            $items = $cdm->select('contact_id, value')
                         ->where($t_id)
                         ->fetchAll();
        }

        $contact_ids = array();
        foreach ($items as $item) {
            $twitter_value = trim($item['value']);
            if ($this->looksLikeUrl($twitter_value)) {
                $twitter_value = $this->cleanUrlFromSchema($twitter_value);
                $twitter_value = substr($twitter_value,strrpos($twitter_value,"/")+1);
                $twitter_value = rtrim($twitter_value, '/');
            }
            if (in_array($twitter_value, $twitter_ids)) {
                $contact_ids[] = $item['contact_id'];
            }
        }

        if (!$contact_ids) {
            return null;
        }

        $cm = new crmContactModel();
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