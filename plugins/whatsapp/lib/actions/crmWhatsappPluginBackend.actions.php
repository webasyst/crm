<?php

class crmWhatsappPluginBackendActions  extends waActions
{
    public function settingsAction()
    {
        $this->getView()->assign(array(
            'source_settings_url' => wa()->getAppUrl().'settings/message-source/whatsapp/',
            'need_show_review_widget' => wa()->appExists('installer')
        ));

        $template = wa()->getAppPath('plugins/whatsapp/templates/WhatsappPluginSettings.html');
        $this->getView()->display($template);
    }

    public function checkTokenAction()
    {
        $token = waRequest::request('access_token', null, waRequest::TYPE_STRING_TRIM);
        $phone_id = waRequest::request('phone_id', null, waRequest::TYPE_STRING_TRIM);
        $api_endpoint = waRequest::request('api_endpoint', null, waRequest::TYPE_STRING_TRIM);
        if (empty($token)) {
            $this->displayJson(['message' => _wd('crm_whatsapp', 'Access token is required')], true);
            return;
        }
        if (empty($phone_id)) {
            $this->displayJson(['message' => _wd('crm_whatsapp', 'Phone number ID is required')], true);
            return;
        }
        $options = empty($api_endpoint) ? [] : ['api_endpoint' => $api_endpoint];
        $api = new crmWhatsappPluginApi($token, $phone_id, null, $options);
        $phone_data = $api->getPhoneNumber();

        if (isset($phone_data['error'])) {
            $this->displayJson([], $phone_data['error']);
            return;
        }

        $this->displayJson($phone_data);
    }
}
