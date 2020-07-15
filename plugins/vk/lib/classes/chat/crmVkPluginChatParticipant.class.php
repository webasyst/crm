<?php

class crmVkPluginChatParticipant extends crmVkPluginChatEntity
{
    /**
     * @var crmContact
     */
    protected $contact;

    public static function factory($id)
    {
        return new self($id);
    }

    public static function tieWith(crmVkPluginVkContact $vk_contact)
    {
        $instance = self::factoryByField(array('domain' => $vk_contact->getDomain()));
        $instance->tie($vk_contact);
        return $instance;
    }

    protected static function factoryByField($field)
    {
        $info = self::getParticipantModel()->getByField($field);
        if (!$info) {
            return self::factory(0);
        }
        $instance = self::factory($info['id']);
        $instance->getInfo();
        $instance->info = $info;
        return $instance;
    }

    protected function tie(crmVkPluginVkContact $vk_contact)
    {
        $data = array(
            'params' => array()
        );

        $contact = null;
        if (!$this->exists() || $this->getContactId() <= 0 || !$this->getContact()->exists()) {
            $domain = $vk_contact->getDomain();
            $data['domain'] = $domain;
            $contact = $this->provideContact($vk_contact);
            $data['contact_id'] = $contact->getId();
        }

        foreach ($vk_contact->getInfo() as $key => $value) {
            if ($key != 'domain') {
                $data['params'][$key] = $value;
            }
        }
        $this->save($data);

        if ($contact) {
            $this->getInfo();
            $this->contact = $contact;
        }
    }

    /**
     * @param crmVkPluginVkContact $vk_contact
     * @return crmContact
     */
    protected function provideContact(crmVkPluginVkContact $vk_contact)
    {
        $provider = crmVkPluginContactProvider::factory($vk_contact);
        $contact = $provider->provide();
        return $contact;
    }


    public function save($data, $delete_old_params = false)
    {
        if ($this->info && $this->info['domain'] && empty($data['domain'])) {
            $data['domain'] = $this->info['domain'];
        }
        parent::save($data, $delete_old_params);
        $this->contact = null;
    }

    public function getContact()
    {
        if ($this->contact) {
            return $this->contact;
        }
        $info = $this->getInfo();
        return $this->contact = new crmContact($info['contact_id']);
    }

    /**
     * @return bool
     */
    public function isContactJustExported()
    {
        return crmVkPluginContactExporter::isContactJustExported($this->getContactId());
    }

    public function getContactId()
    {
        $info = $this->getInfo();
        return $info['contact_id'];
    }

    public function getDomain()
    {
        $info = $this->getInfo();
        return $info['domain'];
    }

    protected function workupInfo($info)
    {
        $name_parts = array(
            (string)ifset($info['params']['first_name']),
            (string)ifset($info['params']['nickname']),
            (string)ifset($info['params']['last_name']),
            (string)ifset($info['params']['name'])
        );
        $name_parts = array_filter($name_parts);
        $info['full_name'] = join(' ', $name_parts);

        $photo_url_variants = array(
            (string)ifset($info['params']['photo_50']),
            (string)ifset($info['params']['photo_100']),
            (string)ifset($info['params']['photo_200']),
            (string)ifset($info['params']['photo_400']),
            (string)ifset($info['params']['photo_max']),
        );
        $photo_url_variants = array_filter($photo_url_variants);
        $info['photo_url_50'] = $photo_url_variants ? reset($photo_url_variants) : '';

        return $info;
    }

    /**
     * @return crmVkPluginModel
     */
    protected function getEntityModel()
    {
        return self::getParticipantModel();
    }

    /**
     * @return crmVkPluginParamsModel
     */
    protected function getEntityParamsModel()
    {
        return self::getParticipantParamsModel();
    }

    /**
     * @return crmVkPluginChatParticipantModel
     */
    protected static function getParticipantModel()
    {
        return self::getModel('participant', 'crmVkPluginChatParticipantModel');
    }

    /**
     * @return crmVkPluginChatParticipantParamsModel
     */
    protected static function getParticipantParamsModel()
    {
        return self::getModel('participant_params', 'crmVkPluginChatParticipantParamsModel');
    }

}
