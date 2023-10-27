<?php

class crmVkPluginImSource extends crmImSource
{
    protected $provider = 'vk';

    public function getProviderName()
    {
        return 'VK';
    }

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/vk/img/', true) . 'vk.png';
    }

    public function getGroupId()
    {
        return (int)$this->getParam('group_id');
    }

    public function getAccessToken()
    {
        return $this->getParam('access_token');
    }

    public function getSecretKey()
    {
        return $this->getParam('secret_key');
    }

    public function getAppId()
    {
        return (int)$this->getParam('app_id');
    }

    public function getServiceToken()
    {
        return $this->getParam('service_token');
    }

    public function getApiParams()
    {
        $params = array();
        $param_keys = array(
            'group_id', 'access_token', 'secret_key', 'app_id', 'service_token'
        );
        foreach ($param_keys as $key) {
            $params[$key] = $this->getParam($key);
        }
        return $params;
    }

    public function getFontAwesomeBrandIcon()
    {
        return [
            'icon_fab' => 'vk',
            'icon_color' => '#4C75A3',
        ];
    }
}
