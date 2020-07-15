<?php

class crmTelegramPluginImSourceSettingsPage extends crmImSourceSettingsPage
{
    /**
     * @var crmTelegramPluginImSource
     */
    protected $source;

    protected function validateSubmit($data)
    {
        $errors = parent::validateSubmit($data);
        if (empty($data['params']['access_token'])) {
            $errors['params']['access_token'] = _wd('crm_telegram', 'Access token is required');
        }

        if (empty($data['params']['username']) || empty($data['params']['firstname'])) {
            $errors['params']['access_token'] = _wd('crm_telegram', 'Invalid access token');
        }

        return $errors;
    }

    /**
     * @return string
     */
    protected function getSpecificSettingsBlock()
    {
        $app_url = $this->getAppUrl();
        $default_start_response = '';
        if ($this->source->getId() <= 0) {
            $loc = array(
                _wd('crm_telegram', 'Hello $contact_name!'),
                _wd('crm_telegram', 'Ask a question and we’ll promptly answer it.'),
                _wd('crm_telegram', 'Your $site_link'),
            );
            $default_start_response = implode("\n\n", $loc);
        }

        $template = wa()->getAppPath('plugins/telegram/templates/source/settings/ImSourceSettingsTelegramBlock.html');
        return $this->renderTemplate($template, array(
            'source'                 => $this->source->getInfo(),
            'app_url'                => $app_url,
            'plugin_static_url'      => wa()->getAppStaticUrl('crm', true).'plugins/telegram/',
            'site_app_url'           => wa()->getAppUrl('site').'#/routing/',
            'default_start_response' => $default_start_response,
        ));
    }

    protected function getAppUrl()
    {
        if ($this->source->getId() <= 0) {
            return '';
        }
        return wa()->getRouteUrl('crm/frontend/app', array(
            'id'        => $this->source->getId(),
            'plugin_id' => 'telegram'
        ), true);
    }
}
