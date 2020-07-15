<?php

class crmVkPluginPersonExporter extends crmVkPluginContactExporter
{
    /**
     * @var crmVkPluginVkUser
     */
    protected $vk_user;

    protected $options;

    /**
     * crmVkPluginPersonExporter constructor.
     * @param crmVkPluginVkUser $vk_user
     * @param array $options
     */
    public function __construct(crmVkPluginVkUser $vk_user, $options = array())
    {
        $this->vk_user = $vk_user;
        $this->options = $options;
    }

    /**
     * @return crmContact
     */
    public function doExport()
    {
        $data = array();
        $this->prepareSimpleFields($data);
        $this->prepareBirthDate($data);
        $this->prepareSex($data);
        $this->prepareCompany($data);
        $this->prepareJobTitle($data);
        $this->prepareAddress($data);

        $contact = new crmContact();
        $contact->save($data);

        $this->setPhoto($contact, $this->vk_user->getPhotoUrl());

        return $contact;
    }

    protected function prepareSimpleFields(&$data)
    {
        $data = array(
            'firstname' => $this->vk_user->getFirstName(),
            'lastname' => $this->vk_user->getLastName(),
            'middlename' => $this->vk_user->getNickname(),
            'socialnetwork.vkontakte' => $this->vk_user->getDomain(),
            'phone.mobile' => $this->vk_user->getMobilePhone(),
            'phone.home' => $this->vk_user->getHomePhone(),
            'url.personal' => $this->vk_user->getSite(),
            'create_app_id' => 'crm',
            'create_contact_id' => 0,
            'create_method' => 'source/im/vk'
        );
        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
            }
        }
    }

    /**
     * @param &$data
     */
    protected function prepareBirthDate(&$data)
    {
        $birth_date = $this->vk_user->getBirthDate();
        if (!$birth_date) {
            return;
        }
        $parts = explode('.', $birth_date);
        $data['birth_day'] = $parts[0];
        $data['birth_month'] = $parts[1];
        if (!empty($parts[2])) {
            $data['birth_year'] = $parts[2];
        }
    }

    /**
     * @param &$data
     */
    protected function prepareSex(&$data)
    {
        $sex = $this->vk_user->getSex();
        if (!$sex) {
            return;
        }
        if ($sex == 2) {
            $data['sex'] = 'm';
        } else if ($sex == 1) {
            $data['sex'] = 'f';
        }
    }

    /**
     * @param &$data
     */
    protected function prepareCompany(&$data)
    {
        $vk_group = $this->vk_user->getLastJobGroup();
        if ($vk_group) {
            $provider = crmVkPluginCompanyProvider::factory($vk_group);
            $company = $provider->provide();
            $data['company_contact_id'] = $company->getId();
            $data['company'] = $company->get('company') ? $company->get('company') : $company->get('name');
            return;
        }

        $job = $this->vk_user->getLastJob();
        if (!$job) {
            return;
        }

        $company = null;
        $company_name = isset($job['name']) ? $job['name'] : $job['company'];

        // than try find company in our db by site if company_name (retrieved from vk) looks like site
        if ($this->looksLikeUrl($company_name)) {
            $searcher = new crmVkPluginCompanySearcher(array(
                'site' => $company_name
            ));
            $company = $searcher->findBySite();
        }

        // finally try find company in our db by name
        if (!$company) {
            $searcher = new crmVkPluginCompanySearcher(array(
                'name' => $company_name
            ));
            $company = $searcher->findByName();
        }

        $data['company'] = $company_name;
        if ($company) {
            $data['company_contact_id'] = $company->getId();
            $data['company'] = $company->get('company') ? $company->get('company') : $company->get('name');
        }
    }

    /**
     * @param &$data
     */
    protected function prepareJobTitle(&$data)
    {
        $job = $this->vk_user->getLastJob();
        if ($job && !empty($job['position'])) {
            $data['jobtitle'] = $job['position'];
        }
    }

    /**
     * @param &$data
     */
    protected function prepareAddress(&$data)
    {
        $city = $this->vk_user->getCity();
        if ($city) {
            $data['address.home']['city'] = $city['title'];
        }

        $vk_country_en = $this->vk_user->getCountryEn();
        if (!$vk_country_en) {
            return;
        }

        $country = $this->foundCountryByName($vk_country_en['title']);
        if ($country) {
            $data['address.home']['country'] = $country['iso3letter'];
            return;
        }

        $vk_country = $this->vk_user->getCountry();
        if ($vk_country) {
            $data['address.home']['country'] = $vk_country['title'];
        }
    }

    private function looksLikeUrl($url)
    {
        $url = trim($url);
        return substr($url, 0, 7) === 'http://' || substr($url, 0, 8) === 'https://';
    }
}
