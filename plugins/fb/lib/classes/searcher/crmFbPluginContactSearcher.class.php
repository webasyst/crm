<?php

class crmFbPluginContactSearcher
{
    protected $fb_info;
    protected $options;

    public function __construct($fb_info, $options = array())
    {
        $this->fb_info = $fb_info;
        $this->options = $options;
    }

    /**
     * @return crmContact|null
     */
    public function findByFbId()
    {
        if (empty($this->fb_info['id'])) {
            return null;
        }
        $id = $this->fb_info['id'];
        $cdm = new waContactDataModel();
        $items = $cdm->select('contact_id, value')
                     ->where("field = 'fb_source_id' AND value = {$cdm->escape($id, 'int')}")
                     ->fetchAll();

        $contact_ids = array();
        foreach ($items as $item) {
            $contact_ids[] = $item['contact_id'];
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
}