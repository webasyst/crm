<?php

class crmVkPluginImSourceSettingsPage extends crmImSourceSettingsPage
{
    /**
     * @var crmVkPluginImSource
     */
    protected $source;

    protected function validateSubmit($data)
    {
        $errors = parent::validateSubmit($data);
        $this->checkRequiredParams($data, $errors);
        return $errors;
    }

    protected function checkRequiredParams($data, &$errors)
    {
        $error_msg = _wd('crm_vk', 'Field is required');
        $fields = array('access_token', 'group_id', 'secret_key', 'service_token', 'app_id', 'app_secret');
        if ($this->source->getId() > 0) {
            $fields[] = 'verify_code';
        }
        foreach ($fields as $field) {
            if (empty($data['params'][$field])) {
                $errors['params'][$field] = $error_msg;
            }
        }
    }


    public function processSubmit($data)
    {
        $result = parent::processSubmit($data);
        if ($result['status'] !== 'ok') {
            return $result;
        }

        try {
            $vk_group = new crmVkPluginVkGroup($data['params']['group_id'], $data['params']);
            $this->source->saveParam('group_info', $vk_group->getInfo());
        } catch (crmVkPluginException $e) {

        }

    }

    /**
     * @return string
     */
    protected function getSpecificSettingsBlock()
    {
        $app_url = $this->getAppUrl();
        $callback_url = $this->getCallbackUrl();
        $group_settings_url = $this->getGroupSettingsUrl();

        $group_info = $this->source->getParam('group_info');
        if ($group_info && is_array($group_info) && intval(ifset($group_info['id'])) == $this->source->getGroupId()) {
            $vk_group = new crmVkPluginVkGroup($group_info);
            $group_info = array(
                'name' => $vk_group->getName(),
                'photo_url' => $vk_group->getPhotoUrl(),
                'domain' => $vk_group->getDomain()
            );
        } else {
            $group_info = null;
        }

        $template = wa()->getAppPath('plugins/vk/templates/source/settings/ImSourceSettingsVkBlock.html');
        return $this->renderTemplate($template, array(
            'source' => $this->source->getInfo(),
            'app_url' => $app_url,
            'callback_url' => $callback_url,
            'plugin_static_url' => wa()->getAppStaticUrl('crm', true) . 'plugins/vk/',
            'site_app_url' => wa()->getAppUrl('site') . '#/routing/',
            'group_settings_url' => $group_settings_url,
            'group_info' => $group_info,
            'locale' => wa()->getLocale()
        ));
    }

    protected function getGroupSettingsUrl()
    {
        if ($this->source->getId() <= 0) {
            return '';
        }
        $group_id = $this->source->getGroupId();
        return "https://vk.com/club{$group_id}?act=api&server=1";
    }

    protected function getCallbackUrl()
    {
        if ($this->source->getId() <= 0 || !wa()->getRouting()->getByApp('crm')) {
            return '';
        }

        return wa()->getRouteUrl('crm', array(
            'plugin' => 'vk',
            'module' => 'frontend',
            'action' => 'callback',
            'id'     => $this->source->getId(),
        ), true);
    }

    protected function getAppUrl()
    {
        if ($this->source->getId() <= 0) {
            return '';
        }
        return wa()->getRouteUrl('crm/frontend/app', array(
            'id' => $this->source->getId(),
            'plugin_id' => 'vk'
        ), true);
    }
}
