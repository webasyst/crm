<?php

class crmVkPluginCompanyExporter extends crmVkPluginContactExporter
{
    /**
     * @var crmVkPluginVkGroup
     */
    protected $vk_group;

    /**
     * @var array
     */
    protected $options;

    /**
     * crmVkPluginCompanyExporter constructor.
     * @param crmVkPluginVkGroup $vk_group
     * @param array $options
     */
    public function __construct(crmVkPluginVkGroup $vk_group, $options = array())
    {
        $this->vk_group = $vk_group;
        $this->options = $options;
    }

    /**
     * @return crmContact
     */
    public function doExport()
    {
        $data = array();
        $this->prepareSimpleFields($data);
        $this->prepareAddress($data);

        $company = new crmContact();
        $company->save($data);

        $this->setPhoto($company, $this->vk_group->getPhotoUrl());

        return $company;
    }

    protected function prepareSimpleFields(&$data)
    {
        $data = array(
            'name' => $this->vk_group->getName(),
            'company' => $this->vk_group->getName(),
            'about' => $this->vk_group->getDescription(),
            'is_company' => 1,
            'create_app_id' => 'crm',
            'create_contact_id' => 0,
            'create_method' => 'source/im/vk'
        );

        $site = $this->vk_group->getSite();
        $screen_name = $this->vk_group->getScreenName();
        $socialnetwork_enabled = (bool)waContactFields::get('socialnetwork', 'company');

        if ($site) {
            $data['url.work'] = $site;
        } elseif ($screen_name && !$socialnetwork_enabled) {
            $data['url.work'] = "https://vk.ru/{$screen_name}";
        }

        if ($screen_name && $socialnetwork_enabled) {
            $data['socialnetwork.vkontakte'] = $screen_name;
        }

        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
            }
        }
    }

    /**
     * @param &$data
     */
    protected function prepareAddress(&$data)
    {
        $this->prepareAddressCity($data);
        $this->prepareAddressCountry($data);
        $this->prepareAddressStreet($data);
        $this->prepareAddressGeoCoordinates($data);
    }

    protected function prepareAddressCity(&$data)
    {
        $city = $this->vk_group->getCity();
        if ($city) {
            $data['address.work']['city'] = $city['title'];
        }
    }

    protected function prepareAddressCountry(&$data)
    {
        $vk_country_en = $this->vk_group->getCountryEn();
        if (!$vk_country_en) {
            return;
        }

        $country = $this->foundCountryByName($vk_country_en['title']);
        if ($country) {
            $data['address.work']['country'] = $country['iso3letter'];
            return;
        }

        $vk_country = $this->vk_group->getCountry();
        if ($vk_country) {
            $data['address.work']['country'] = $vk_country['title'];
        }
    }

    protected function prepareAddressStreet(&$data)
    {
        $address = $this->vk_group->getPlaceAddress();
        if ($address) {
            $data['address.work']['street'] = $address;
        }
    }

    protected function prepareAddressGeoCoordinates(&$data)
    {
        $lng = $this->vk_group->getPlaceLongitude();
        $lat = $this->vk_group->getPlaceLatitude();
        if ($lat && $lng) {
            $data['address.work']['lng'] = $lng;
            $data['address.work']['lat'] = $lat;
        }
    }
}
