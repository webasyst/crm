<?php

class crmMaxPluginBackendActions  extends waActions
{
    public function checkTokenAction()
    {
        $token = waRequest::request('token', null, waRequest::TYPE_STRING_TRIM);
        if (!$token) {
            $this->displayJson(['message' => 'Access token not specified'], true);
            return;
        }

        $api = new crmMaxPluginApi($token);
        $bot_data = $api->getMe();

        if (!$bot_data) {
            $error = $api->getLastError();
            $message = $error['error'] === 'http_error' && $error['http_code'] == 401 ?
                _wd('crm_max', 'Invalid access token.') :
                ifset($error, 'message', _w('Unknown error.'));

            $this->displayJson(['message' => $message], true);
            return;
        }

        $this->displayJson($bot_data);
    }

    public function settingsAction()
    {
        $this->getView()->assign(array(
            'source_settings_url' => wa()->getAppUrl().'settings/sources/?type=im',
            'need_show_review_widget' => wa()->appExists('installer')
        ));

        $template = wa()->getAppPath('plugins/max/templates/Settings.html');
        $this->getView()->display($template);
    }
}
