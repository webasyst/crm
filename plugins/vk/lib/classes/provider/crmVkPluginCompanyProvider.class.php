<?php

class crmVkPluginCompanyProvider extends crmVkPluginContactProvider
{
    protected $vk_group;
    public function __construct(crmVkPluginVkGroup $vk_group)
    {
        $this->vk_group = $vk_group;
    }

    /**
     * @return crmContact
     */
    public function provide()
    {
        $company = $this->findCompany();
        if (!$company) {
            $exporter = new crmVkPluginCompanyExporter($this->vk_group);
            $company = $exporter->export();
        }
        return $company;
    }

    protected function findCompany()
    {
        $company = null;

        // search by site, first try
        $searcher = new crmVkPluginCompanySearcher(array(
            'site' => $this->vk_group->getSite(),
        ));
        $company = $searcher->findBySite();
        if ($company) {
            return $company;
        }

        // search by site, second try
        $domain = $this->vk_group->getDomain();
        $searcher = new crmVkPluginCompanySearcher(array(
            'site' => "https://vk.com/{$domain}",
        ));
        $company = $searcher->findBySite();
        if ($company) {
            return $company;
        }

        // now try search by vk_ids (id, screen_name) and than by name
        $searcher = new crmVkPluginCompanySearcher(array(
            'id' => $this->vk_group->getId(),
            'name' => $this->vk_group->getName(),
            'domain' => $domain
        ));

        $company = $searcher->findByVkIds();
        if ($company) {
            return $company;
        }
        return $searcher->findByName();
    }
}
