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

    public function processSubmit($data)
    {
        $is_new_source = empty($this->source->getId());
        $delete_webhook = false;
        $create_webhook = false;
        $created_webhook = false;
        $api = new crmTelegramPluginApi($data['params']['access_token']);
        if (empty($data['params']['webhook_mode'])) {
            $data['params']['webhook_mode'] = '0';
            $data['params']['webhook_token'] = '';
            // Webhook need to be deleted after source saving
            $delete_webhook = true;
        } elseif (empty($data['params']['webhook_token'])) {
            if (empty(wa()->getRouting()->getByApp('crm'))) {
                return [
                    'status' => 'fail',
                    'errors' => [ _wd('crm_telegram', 'A CRM settlement is required.'),
                                  _w('Use Site app to add a settlement for CRM.')],
                    'response' => []
                ];
            }

            $data['params']['webhook_token'] = waUtils::getRandomHexString(64);
            if ($is_new_source) {
                // Case of new cource creating
                // Webhook was need to be created after getting source ID
                $create_webhook = true;
            } else {
                $res = $api->setWebhook($this->source->getId(), $data['params']['webhook_token']);
                if (empty($res['ok'])) {
                    return [
                        'status' => 'fail',
                        'errors' => [ sprintf(_wd('crm_telegram', 'Error creating webhook: %s'), $res['description']) ],
                        'response' => []
                    ];
                }
                // Webhook was created
                $created_webhook = true;
            }
        }
            
        $result = parent::processSubmit($data);
        if ($result['status'] !== 'ok') {
            if ($created_webhook) {
                $api->deleteWebhook();  
            }
            return $result;
        }

        if ($create_webhook) {
            // Webhook was need to be created after getting source ID
            $res = $api->setWebhook($this->source->getId(), $data['params']['webhook_token']);
            if (empty($res['ok'])) {
                $this->source->delete();
                return [
                    'status' => 'fail',
                    'errors' => [ sprintf(_wd('crm_telegram', 'Error creating webhook: %s'), $res['description']) ],
                    'response' => []
                ];
            }
        } elseif ($delete_webhook) {
            // Webhook need to be deleted
            $api->deleteWebhook();
        }

        return $result;
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
        $default_phone_request = _wd('crm_telegram', 'Please send us your phone number.');
        $default_phone_request_button = _wd('crm_telegram', 'Send phone number');
        $default_phone_response = _wd('crm_telegram', 'Thank you!');
        $template = wa()->getAppPath('plugins/telegram/templates/source/settings/ImSourceSettingsTelegramBlock.html');
        return $this->renderTemplate($template, array(
            'source'                 => $this->source->getInfo(),
            'app_url'                => $app_url,
            'plugin_static_url'      => wa()->getAppStaticUrl('crm', true).'plugins/telegram/',
            'site_app_url'           => wa()->getAppUrl('site').'#/routing/',
            'default_start_response' => $default_start_response,
            'default_phone_request'  => $default_phone_request,
            'default_phone_request_button' => $default_phone_request_button,
            'default_phone_response' => $default_phone_response,
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
