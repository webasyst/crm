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
    public function findByTelegram()
    {
        $items = null;
        $contact_data_model = new waContactDataModel();
        $search_by_id = false;
        if (ifset($this->telegram_info['username'])) {
            $items = $contact_data_model->getByField([
                'field' => 'im', 
                'ext'   => 'telegram',
                'value' => [
                    $this->telegram_info['username'], 
                    'https://t.me/'.$this->telegram_info['username'], 
                    'https://t.me/'.$this->telegram_info['username'].'/', 
                    '@'.$this->telegram_info['username'],
                ],
            ], 'contact_id');
        }
        if (empty($items)) {
            $items = $contact_data_model->getByField([
                'field' => 'telegram_id', 
                'value' => $this->telegram_info['id'], 
            ], 'contact_id');
            $search_by_id = true;
        }

        if (empty($items)) {
            return null;
        }
        $contact_ids = array_keys($items);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (empty($contact_ids)) {
            return null;
        }
        $contact_id = $contact_ids[0];
        if (isset($this->options['is_company'])) {
            $contact_record = (new waContactModel)->getByField([
                'id' => $contact_ids,
                'is_company' => (bool)$this->options['is_company'] ? 1 : 0
            ]);
            if (empty($contact_record)) {
                return null;
            }
            $contact_id = $contact_record['id'];
        }

        $contact = new crmContact($contact_id);
        if (empty($contact) || !$contact->exists()) {
            return null;
        }
        if ($search_by_id && ifset($this->telegram_info['username'])) {
            $contact->add('im.telegram', $this->telegram_info['username']);
            $contact->save();
        }
        return $contact;
    }

}