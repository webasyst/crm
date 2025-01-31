<?php

class crmTelegramPluginContactExporter
{
    protected $telegram_info;
    protected $options;

    public function __construct($telegram_info, $options = array())
    {
        $this->telegram_info = $telegram_info;
        $this->options = $options;
    }

    /**
     * @return crmContact
     */
    public function export()
    {
        $data = array();
        $this->prepareSimpleFields($data);
        $contact = new crmContact();
        if ($errors = $contact->save($data)) {
            waLog::log(waUtils::jsonEncode($errors), 'crm/plugins/telegram.log');
        }
        return $contact;
    }

    protected function prepareSimpleFields(&$data)
    {
        $data = array(
            'firstname'         => ifset($this->telegram_info['first_name']),
            'lastname'          => ifset($this->telegram_info['last_name']),
            'im.telegram'       => ifset($this->telegram_info['username']),
            'telegram_id'       => $this->telegram_info['id'],
            'locale'            => ifset($this->telegram_info['language_code']) === 'ru' ? 'ru_RU' : 'en_US',
            'create_app_id'     => 'crm',
            'create_contact_id' => 0,
            'create_method'     => 'source/im/telegram',
            'crm_user_id'       => ifset($this->options['crm_user_id']),
        );
        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
            }
        }
    }
}
