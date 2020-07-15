<?php

class crmVkPluginVkUser extends crmVkPluginVkContact
{
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
            'nickname',
            'screen_name',
            'photo_50',
            'photo_max',
            'contacts',
            'domain',
            'city',
            'country',
            'bdate',
            'sex',
            'site',
            'career',
            'occupation',
            'connections'
        );

        $api = new crmVkPluginApi($this->access_token);
        return $api->getUser($this->id, $fields);
    }

    protected function getField($name)
    {
        $info = $this->getInfo();
        return isset($info[$name]) ? $info[$name] : null;
    }

    /**
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->getField('first_name');
    }

    public function getLastName()
    {
        return $this->getField('last_name');
    }

    public function getNickname()
    {
        return $this->getField('nickname');
    }

    public function getDomain()
    {
        return $this->getField('domain');
    }

    public function getScreenName()
    {
        return $this->getField('screen_name');
    }


    public function getMobilePhone()
    {
        return $this->getField('mobile_phone');
    }

    public function getHomePhone()
    {
        return $this->getField('home_phone');
    }

    public function getSite()
    {
        return $this->getField('site');
    }

    public function getBirthDate()
    {
        return $this->getField('bdate');
    }

    public function getSex()
    {
        return $this->getField('sex');
    }

    public function getPhotoUrl()
    {
        return $this->getField('photo_max');
    }

    public function getCareer()
    {
        return (array)$this->getField('career');
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

    /**
     * @return array|null
     */
    public function getLastJob()
    {
        $career = $this->getCareer();
        if (!$career) {
            return null;
        }
        $info = $this->getInfo();
        if (array_key_exists('last_job', $info)) {
            return $info['last_job'];
        }
        return $this->info['last_job'] = array_pop($career);
    }


    /**
     * @return crmVkPluginVkGroup|null
     */
    public function getLastJobGroup()
    {
        $career = $this->getLastJob();
        if (!$career || !isset($career['group_id'])) {
            return null;
        }
        $info = $this->getInfo();
        if (!empty($info['extra']['last_job_group'])) {
            return $info['extra']['last_job_group'];
        }
        $vk_group = new crmVkPluginVkGroup($career['group_id'], array(
            'service_token' => $this->service_token,
            'access_token' => $this->access_token
        ));
        return $this->info['extra']['last_job_group'] = $vk_group;
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
        $country = $api->getCountry($info['country']);
        $country_en = $country;
        if ($api->getLang() != 'en') {
            $api->setLang('en');
            $country_en = $api->getCountry($info['country']);
        }
        $this->cache['countries'] = array($country, $country_en);
    }
}
