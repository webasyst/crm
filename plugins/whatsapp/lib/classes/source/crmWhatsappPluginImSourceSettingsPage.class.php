<?php

class crmWhatsappPluginImSourceSettingsPage extends crmImSourceSettingsPage
{
    /**
     * @var crmWhatsappPluginImSource
     */
    protected $source;

    protected function validateSubmit($data)
    {
        $errors = parent::validateSubmit($data);
        $this->checkRequiredParams($data, $errors);

        if (empty($data['params']['valid_credentials_marker'])) {
            $errors['params']['access_token'] = _wd('crm_whatsapp', 'Access token or phone number ID is invalid');
            $errors['params']['phone_id'] = _wd('crm_whatsapp', 'Access token or phone number ID is invalid');
        }

        return $errors;
    }

    protected function checkRequiredParams($data, &$errors)
    {
        $error_msg = _wd('crm_whatsapp', 'Field is required');
        $fields = ['access_token', 'phone_id', 'app_secret'];
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
        $webhook_url = $this->getWebhookUrl();

        $template = wa()->getAppPath('plugins/whatsapp/templates/source/settings/ImSourceSettingsWhatsappBlock.html');
        return $this->renderTemplate($template, [
            'source'            => $this->source->getInfo(),
            'webhook_url'       => $webhook_url,
            'webhook_token'     => $this->source->getParam('webhook_token') ?: waUtils::getRandomHexString(16),
            'app_mode'          => $this->source->getParam('app_mode') ?: 'live',
            'plugin_static_url' => wa()->getAppStaticUrl('crm', true).'plugins/whatsapp/',
            'site_app_url'      => wa()->getAppUrl('site').'#/routing/',
            'locale'            => wa()->getLocale(),
        ]);
    }

    protected function getWebhookUrl()
    {
        if ($this->source->getId() <= 0 || !wa()->getRouting()->getByApp('crm')) {
            return '';
        }
        return wa()->getRouteUrl('crm', [
            'plugin' => 'whatsapp',
            'module' => 'frontend',
            'action' => 'webhook',
            'source_id' => $this->source->getId(),
        ], true);
    }
}
