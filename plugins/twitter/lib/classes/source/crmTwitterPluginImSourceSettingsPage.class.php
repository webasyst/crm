<?php

class crmTwitterPluginImSourceSettingsPage extends crmImSourceSettingsPage
{
    /**
     * @var crmTwitterPluginImSource
     */
    protected $source;

    protected function validateSubmit($data)
    {
        $errors = parent::validateSubmit($data);
        $this->checkRequiredParams($data, $errors);

        if (empty($data['params']['account_name']) || empty($data['params']['username']) || empty($data['params']['userid'])) {
            $errors['params']['access_token_secret'] = _wd('crm_twitter', 'Make sure the entered data is correct or try again after 15 minutes.');
        }

        return $errors;
    }

    protected function checkRequiredParams($data, &$errors)
    {
        $error_msg = _wd('crm_twitter', 'Field is required');
        $fields = array('consumer_key', 'consumer_secret', 'access_token', 'access_token_secret');
        foreach ($fields as $field) {
            if (empty($data['params'][$field])) {
                $errors['params'][$field] = $error_msg;
            }
        }
    }

    /**
     * @return string
     */
    protected function getSpecificSettingsBlock()
    {
        $callback_url = $this->getCallbackUrl();

        $template = wa()->getAppPath('plugins/twitter/templates/source/settings/ImSourceSettingsTwitterBlock.html');
        return $this->renderTemplate($template, array(
            'source'            => $this->source->getInfo(),
            'callback_url'      => $callback_url,
            'plugin_static_url' => wa()->getAppStaticUrl('crm', true).'plugins/twitter/',
            'site_app_url'      => wa()->getAppUrl('site').'#/routing/',
            'locale' => wa()->getLocale(),
        ));
    }

    protected function getCallbackUrl()
    {
        if ($this->source->getId() <= 0) {
            return '';
        }
        return wa()->getRouteUrl('crm/frontend/callback', array(
            'id'        => $this->source->getId(),
            'plugin_id' => 'twitter',
        ), true);
    }
}
