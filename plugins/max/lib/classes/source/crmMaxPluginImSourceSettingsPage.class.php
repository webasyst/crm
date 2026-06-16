<?php

/**
 * MAX messenger settings page for CRM.
 */
class crmMaxPluginImSourceSettingsPage extends crmImSourceSettingsPage
{
    /**
     * @var crmMaxPluginImSource
     */
    protected $source;

    protected $data;

    /**
     * Validate form submission
     *
     * @param array $data Form data
     * @return array Errors array
     */
    protected function validateSubmit($data)
    {
        $errors = parent::validateSubmit($data);

        if (empty($data['params']['token'])) {
            $errors['params']['token'] = _wd('crm_max', 'Access token is required.');
            return $errors;
        }

        $token = ifset($data['params']['token']);
        $api = new crmMaxPluginApi($token, $this->source->getId());

        if (empty($data['params']['username']) || empty($data['params']['firstname'])) {
            $bot_data = $api->getMe();
            if (!$bot_data) {
                $error = $api->getLastError();
                $message = $error['error'] === 'http_error' && $error['http_code'] == 401 ?
                    _wd('crm_max', 'Invalid access token.') :
                    ifset($error, 'message', _w('Unknown error.'));

                $errors['params']['token'] = $message;
                return $errors;
            }

            $data['params']['bot_id'] = $bot_data['user_id'];
            $data['params']['username'] = $bot_data['username'];
            $data['params']['firstname'] = $bot_data['first_name'];
            if (empty($data['name'])) {
                $data['name'] = $bot_data['first_name'] ?: $bot_data['username'];
                unset($errors['name']);
            }

            $this->data = $data;
        }

        if (empty($data['params']['webhook_mode'])) {
            $subscribtions_res = $api->getSubscriptions();
            $subscribtions = ifset($subscribtions_res, 'subscriptions', null);
            if (!empty($subscribtions)) {
                $available_webhook_urls = $this->getWebhookUrls($api);
                $subscribtions = array_filter($subscribtions, function ($subscribtion) use ($available_webhook_urls) {
                    return !in_array($subscribtion['url'], $available_webhook_urls);
                });
            }
            if (!empty($subscribtions)) {
                $errors['params']['webhook_mode'] = _wd('crm_max', 'This MAX bot has webhooks enabled for other integrations. It can work in the webhook mode only.');
            }
        } else {
            if (empty(wa()->getRouting()->getByApp('crm'))) {
                $errors['params']['webhook_mode'] = _wd('crm_max', 'A CRM site rule is required. Add a rule in the <em>Site</em> app.');
            }
        }

        return $errors;
    }

    /**
     * Process form submission
     *
     * @param array $data Form data
     * @return array Result with status and errors
     */
    public function processSubmit($data)
    {
        if (!empty($this->data)) {
            $data = $this->data;
        }

        $is_new_source = empty($this->source->getId());
        $create_webhook = false;
        $created_webhook = false;
        $delete_webhook = false;
        $webhook_url = $this->source->getParam('webhook_url');

        $token = ifset($data['params']['token']);
        $api = new crmMaxPluginApi($token, $this->source->getId());

        // Handle webhook mode
        $webhook_mode = !empty($data['params']['webhook_mode']);
        if (!$webhook_mode) {
            // Disable webhook mode
            $data['params']['webhook_mode'] = '0';
            $data['params']['webhook_secret'] = '';
            $data['params']['webhook_url'] = '';
            $delete_webhook = true;
        } elseif (empty($data['params']['webhook_secret'])) {
            // Enable webhook but no secret set
            // Generate secret
            $data['params']['webhook_secret'] = waUtils::getRandomHexString(32);

            if ($is_new_source) {
                // Create webhook after source is saved (need source ID)
                $create_webhook = true;
            } else {
                // Try to create webhook now
                $result = $api->setWebhook(
                    null,
                    [ 'secret' => $data['params']['webhook_secret'] ]
                );

                if (empty($result) || isset($result['error'])) {
                    $error = $api->getLastError();
                    $error_msg = isset($error['message'])
                        ? $error['message']
                        : _wd('crm_max', 'Webhook creation error.');
                    return array(
                        'status' => 'failed',
                        'errors' => [
                            'params' => [
                                'webhook_mode' => sprintf(_wd('crm_max', 'Webhook creation error: %s'), $error_msg)
                            ]
                        ],
                        'response' => [],
                    );
                }
                $data['params']['webhook_url'] = $api->getWebhookUrl();
                $created_webhook = true;
            }
        }

        if (empty($data['params']['do_not_save_attachments'])) {
            $data['params']['do_not_save_attachments'] = '0';
        }

        // Save source
        $result = parent::processSubmit($data);

        if ($result['status'] !== 'ok') {
            // Rollback webhook if created
            if ($created_webhook) {
                $api->removeWebhook();
            }
            return $result;
        }

        // Create webhook for new source
        if ($create_webhook) {
            if ($is_new_source) {
                $api = new crmMaxPluginApi($token, $this->source->getId());
            }
            $result = $api->setWebhook(
                null,
                [ 'secret' => $data['params']['webhook_secret'] ]
            );

            if (empty($result) || isset($result['error'])) {
                $error = $api->getLastError();
                $error_msg = isset($error['message'])
                    ? $error['message']
                    : _wd('crm_max', 'Webhook creation error.');

                // Delete the created source
                $this->source->delete();

                return array(
                    'status' => 'failed',
                    'errors' => [
                        'params' => [
                            'webhook_mode' => sprintf(_wd('crm_max', 'Webhook creation error: %s'), $error_msg)
                        ]
                    ],
                    'response' => array(),
                );
            }

            $this->source->saveParam('webhook_url', $api->getWebhookUrl());
            $result['response']['source'] = $this->source->getInfo();
        } elseif ($delete_webhook) {
            // Remove existing webhook
            $api->removeWebhook($webhook_url);
        }

        return $result;
    }

    /**
     * Get source-specific settings block HTML
     *
     * @return string
     */
    protected function getSpecificSettingsBlock()
    {
        $app_url = $this->getAppUrl();
        $default_start_response = '';

        if ($this->source->getId() <= 0) {
            $loc = array(
                _wd('crm_max', 'Hello $contact_name!'),
                _wd('crm_max', 'Ask a question, and we’ll reply promptly.'),
                _wd('crm_max', 'Your $site_link'),
            );
            $default_start_response = implode("\n\n", $loc);
        }

        $default_phone_request = _wd('crm_max', 'Please send your phone number.');
        $default_phone_request_button = _wd('crm_max', 'Send phone number');
        $default_phone_response = _wd('crm_max', 'Thank you!');

        $webhook_mode = false;
        $webhook_secret = $this->source->getParam('webhook_secret');
        $token = $this->source->getParam('token');
        if (!empty($token)) {
            $api = new crmMaxPluginApi($token, $this->source->getId());
            $subscribtions_res = $api->getSubscriptions();
            $subscribtions = ifset($subscribtions_res['subscriptions']);
            if (!empty($subscribtions)) {
                $available_webhook_urls = $this->getWebhookUrls($api);
                $subscribtions = array_filter($subscribtions, function ($subscribtion) use ($available_webhook_urls) {
                    return in_array($subscribtion['url'], $available_webhook_urls);
                });
                $webhook_mode = !empty($subscribtions);
            }
        }
        if (!$webhook_mode) {
            $webhook_secret = '';
        }

        $template = wa()->getAppPath('plugins/max/templates/source/settings/ImSourceSettingsMaxBlock.html');
        return $this->renderTemplate($template, [
            'source'                 => $this->source->getInfo(),
            'app_url'                => $app_url,
            'plugin_static_url'      => wa()->getAppStaticUrl('crm', true) . 'plugins/max/',
            'site_app_url'           => wa()->getAppUrl('site') . '#/routing/',
            'default_start_response' => $default_start_response,
            'default_phone_request'  => $default_phone_request,
            'default_phone_request_button' => $default_phone_request_button,
            'default_phone_response' => $default_phone_response,
            'webhook_mode'           => $webhook_mode,
            'webhook_secret'         => $webhook_secret,
        ]);
    }

    protected function getWebhookUrls($api)
    {
        $webhook_url = $this->source->getParam('webhook_url');
        $available_webhook_urls = $api->getWebhookUrl(true);
        if (!empty($webhook_url) && !in_array($webhook_url, $available_webhook_urls)) {
            $available_webhook_urls = array_map(function ($url) use ($webhook_url) {
                if (strpos($url, 'http://') === 0 && $webhook_url === str_replace('http://', 'https://', $url)) {
                    $url = $webhook_url;
                }
                return $url;
            }, $available_webhook_urls);
        }
        return $available_webhook_urls;
    }

    /**
     * Get app URL for this source
     *
     * @return string
     */
    protected function getAppUrl()
    {
        if ($this->source->getId() <= 0) {
            return '';
        }
        return wa()->getRouteUrl('crm/frontend/app', array(
            'id'        => $this->source->getId(),
            'plugin_id' => 'max'
        ), true);
    }
}
