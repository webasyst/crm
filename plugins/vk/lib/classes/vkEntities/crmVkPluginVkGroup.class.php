<?php

class crmVkPluginVkGroup extends crmVkPluginVkContact
{
    protected $id;
    protected $cache;
    protected $access_token;
    protected $service_token;

    public function __construct($id, $options = array())
    {
        parent::__construct($id, $options);
        $this->access_token = (string)ifset($options['access_token']);
        $this->service_token = (string)ifset($options['service_token']);
    }

    protected function loadInfo()
    {
        $fields = array(
            'screen_name',
            'photo_50',
            'photo_200',
            'contacts',
            'city',
            'country',
            'description',
            'site',
            'place'
        );

        $api = new crmVkPluginApi($this->access_token);
        return $api->getGroup($this->id, $fields);
    }

    public function getName()
    {
        return $this->getField('name');
    }

    public function getScreenName()
    {
        return $this->getField('screen_name');
    }

    public function getDomain()
    {
        $screen_name = $this->getScreenName();
        return $screen_name ? $screen_name : ('club' . $this->getId());
    }

    public function getDescription()
    {
        return $this->getField('description');
    }

    public function getSite()
    {
        return $this->getField('site');
    }

    public function getPhotoUrl()
    {
        return $this->getField('photo_200');
    }


    /**
     * @return array|null
     */
    public function getCity()
    {
        $info = $this->getInfo();
        if (empty($info['city']) || empty($info['city']['id'])) {
            return null;
        }
        if (!empty($info['city']['title'])) {
            return $info['city'];
        }
        if (!empty($info['extra']['city'])) {
            return $info['extra']['city'];
        }
        $api = new crmVkPluginApi($this->service_token);
        return $info['extra']['city'] = $api->getCity($info['city']['id']);
    }

    protected function getPlaceField($name)
    {
        $place = $this->getField('place');
        return $place && isset($place[$name]) ? $place[$name] : null;
    }

    public function getPlaceAddress()
    {
        return $this->getPlaceField('address');
    }

    public function getPlaceLatitude()
    {
        return $this->getPlaceField('latitude');
    }

    public function getPlaceLongitude()
    {
        return $this->getPlaceField('longitude');
    }

    /**
     * @return array|null
     */
    public function getCountry()
    {
        $info = $this->getInfo();
        if (empty($info['country']) || empty($info['country']['id'])) {
            return null;
        }
        if (!empty($info['extra']['country'])) {
            return $info['extra']['country'];
        }
        $res = $this->getCountries();
        return $info['extra']['country'] = $res[0];
    }

    public function getCountryEn()
    {
        $info = $this->getInfo();
        if (empty($info['country']) || empty($info['country']['id'])) {
            return null;
        }
        if (!empty($info['extra']['country_en'])) {
            return $info['extra']['country_en'];
        }
        $res = $this->getCountries();
        return $info['extra']['country_en'] = $res[1];
    }

    protected function getCountries()
    {
        if (empty($this->cache['countries'])) {
            $this->loadCountries();
        }
        return $this->cache['countries'];
    }

    protected function loadCountries()
    {
        $info = $this->getInfo();
        $api = new crmVkPluginApi($this->service_token);
        $country = $api->getCountry($info['country']['id']);
        $country_en = $country;
        if ($api->getLang() != 'en') {
            $api->setLang('en');
            $country_en = $api->getCountry($info['country']['id']);
        }
        $this->cache['countries'] = array($country, $country_en);
    }
}

