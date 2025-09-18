<?php

abstract class crmVkPluginContactSearcher
{
    protected $vk_info;
    protected $options;

    public function __construct($vk_info, $options = array())
    {
        $this->vk_info = $vk_info;
        $this->options = $options;
    }

    /**
     * @return crmContact|null
     */
    public function findByPhones()
    {
        $phones = array();

        if (!empty($this->vk_info['mobile_phone'])) {
            $phones[] = $this->vk_info['mobile_phone'];
        }
        if (!empty($this->vk_info['home_phone'])) {
            $phones[] = $this->vk_info['home_phone'];
        }


        $values = array();
        foreach ($phones as $phone) {
            $phone = preg_replace('/[\s|\(|\)|\+|\-]/', '', $phone);
            if ($phone) {
                $values[] = $phone;
            }
        }

        if (!$values) {
            return null;
        }

        $values = array_unique($values);

        $cdm = new waContactDataModel();
        $contact_id = $cdm->select('contact_id')
            ->where("field='phone' AND value IN (:phones)", array(
                'phones' => $values
            ))->fetchField();

        return $contact_id > 0 ? new crmContact($contact_id) : null;
    }

    /**
     * @return crmContact|null
     * @throws waException
     */
    public function findByVkIds()
    {
        $vk_ids = array();
        if (!empty($this->vk_info['id'])) {
            $vk_ids[] = $this->vk_info['id'];
            if (wa_is_int($this->vk_info['id'])) {
                $vk_ids[] = 'id' . $this->vk_info['id'];
            }
        }
        if (!empty($this->vk_info['domain'])) {
            $vk_ids[] = $this->vk_info['domain'];
        }
        if (!empty($this->vk_info['screen_name'])) {
            $vk_ids[] = $this->vk_info['screen_name'];
        }

        $vk_ids = array_unique($vk_ids);
        if (!$vk_ids) {
            return null;
        }

        $cdm = new waContactDataModel();

        $values = array();
        foreach ($vk_ids as $vk_id) {
            $v = strtolower($vk_id);

            $looks_like_url = substr($v, 0, 5) === 'http:' || substr($v, 0, 6) === 'https:';

            $values[] = $v;

            if (!$looks_like_url) {
                $domains   = ['vk.com', 'vkontakte.ru', 'vk.ru'];
                $schemas   = array('http', 'https');
                $end_parts = array('/', '');
                foreach ($domains as $domain) {
                    foreach ($schemas as $schema) {
                        foreach ($end_parts as $end_part) {
                            $values[] = "{$schema}://{$domain}/{$vk_id}{$end_part}";
                        }
                    }
                }
            }
        }

        // throw off not unique values
        $values = array_unique($values);

        if (!$values) {
            return null;
        }

        $max_limit = 500;   // just in case

        // Here found contact IDs
        $contact_ids = array();


        // First search by 'socialnetwork' with 'ext' = 'vk'|'vkontakte'
        $where = join(' AND ', array(
            "field='socialnetwork'",
            "(LOWER(value) IN (:values))",
            "ext IN (:exts)",
        ));
        // Do query and extract items
        $items = $cdm->select('contact_id, value')
            ->where($where, array(
                'values' => $values,
                'exts' => array('vk', 'vkontakte')
            ))
            ->limit($max_limit)
            ->fetchAll('contact_id');


        // Than search by 'vkontakte_id' param (see vkontakteAuth::getUserData AND waViewHelper::oauth methods)
        // 'vkontakte_id' it is old format but with backward compatibility
        $where = join(' AND ', array(
            "field='vkontakte_id'",
            "(LOWER(value) IN (:values))"
        ));
        // Do query and extract items
        $extra_items = $cdm->select('contact_id, value')
            ->where($where, array(
                'values' => $values
            ))
            ->limit($max_limit)
            ->fetchAll('contact_id');

        // Merge result items
        foreach ($extra_items as $item) {
            $items[$item['contact_id']] = $item;
        }

        // Collect contact IDS with that match vk IDS
        foreach ($items as $item) {
            $vk_value = strtolower(trim($item['value']));
            if ($this->looksLikeUrl($vk_value)) {
                $vk_value = $this->cleanUrlFromSchema($vk_value);
                $vk_value = rtrim($vk_value, '/');
            }
            if (in_array($vk_value, $vk_ids)) {
                $contact_ids[] = $item['contact_id'];
            }
        }

        // sorry, bro
        if (!$contact_ids) {
            return null;
        }

        // GET first existing contact (company or person)
        $cm = new waContactModel();
        $contact_id = $cm->select('id')
            ->where('id IN (:ids) AND is_company = :is_company', array(
                'ids' => $contact_ids,
                'is_company' => ifset($this->options['is_company']) ? 1 : 0
            ))->fetchField();

        return $contact_id > 0 ? new crmContact($contact_id) : null;
    }

    /**
     * @return crmContact|null
     */
    public function findBySite()
    {
        if (!isset($this->vk_info['site'])) {
            return null;
        }

        $url = isset($this->vk_info['site']) ? $this->vk_info['site'] : '';
        $url = rtrim(trim($url), '/');

        $domain = $this->cleanUrlFromSchema($url);

        $cdm = new waContactDataModel();

        $contact_ids = array();
        $items = $cdm->select('contact_id, value')
                ->where("field = 'url' AND value LIKE '%l:v%'", array(
                    'v' => $domain
                ))->fetchAll();
        foreach ($items as $item) {
            $value_domain = $this->cleanUrlFromSchema(rtrim(trim($item['value']), '/'));
            if ($value_domain == $domain) {
                $contact_ids[] = $item['contact_id'];
            }
        }

        if (!$contact_ids) {
            return null;
        }

        $cm = new crmContactModel();
        $contact_id = $cm->select('id')
            ->where("id IN (:ids) AND is_company = :is_company", array(
                'ids' => $contact_ids,
                'is_company' => ifset($this->options['is_company']) ? 1 : 0
            ))->fetchField();
        return $contact_id > 0 ? new crmContact($contact_id) : null;
    }

    /**
     * @return crmContact|null
     */
    public function findByName()
    {
        if (!isset($this->vk_info['name']) || strlen($this->vk_info['name']) <= 0) {
            return null;
        }

        $cm = new crmContactModel();

        // search by like, to prevent register case influence
        $where = "name LIKE 'l:name'";
        if (ifset($this->options['is_company'])) {
            $where .= " OR company LIKE 'l:name'";
            $where = "($where)";
        }
        $where .= " AND is_company = :is_company";

        $contact_id = $cm->select('id')
            ->where($where, array(
                'name' => $this->vk_info['name'],
                'is_company' => ifset($this->options['is_company']) ? 1 : 0
            ))
            ->fetchField();
        return $contact_id > 0 ? new crmContact($contact_id) : null;
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
