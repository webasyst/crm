<?php

class crmVkPluginPersonProvider extends crmVkPluginContactProvider
{
    protected $vk_user;
    public function __construct(crmVkPluginVkUser $vk_user)
    {
        $this->vk_user = $vk_user;
    }

    /**
     * @return crmContact
     */
    public function provide()
    {
        $contact = $this->findContact();
        return $contact ? $contact : $this->export();
    }

    /**
     * @return crmContact
     */
    protected function export()
    {
        $exporter = new crmVkPluginPersonExporter($this->vk_user);
        return $exporter->export();
    }


    /**
     * @return crmContact
     */
    protected function findContact()
    {
        $contact = $this->findContactByPhones();
        if ($contact) {
            return $contact;
        }
        return $this->findContactByVkIds();
    }

    protected function findContactByPhones()
    {
        $searcher = new crmVkPluginPersonSearcher(array(
            'home_phone' => $this->vk_user->getHomePhone(),
            'mobile_phone' => $this->vk_user->getMobilePhone()
        ));
        return $searcher->findByPhones();
    }

    protected function findContactByVkIds()
    {
        $searcher = new crmVkPluginPersonSearcher(array(
            'id' => $this->vk_user->getId(),
            'screen_name' => $this->vk_user->getScreenName(),
            'domain' => $this->vk_user->getDomain()
        ));
        return $searcher->findByVkIds();
    }
}
