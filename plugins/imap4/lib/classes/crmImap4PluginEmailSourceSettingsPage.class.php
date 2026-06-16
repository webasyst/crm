<?php

class crmImap4PluginEmailSourceSettingsPage extends crmEmailSourceSettingsPage
{
    protected function getConnectionSettingsBlock()
    {
        waSystem::pushActivePlugin('imap4', 'crm');

        $info = $this->source->getInfo();
        $params = isset($info['params']) && is_array($info['params']) ? $info['params'] : array();

        $leave_messages_on = !isset($params['leave_messages_on_server']) || $params['leave_messages_on_server'] === '1';
        $delete_messages_on_server_form_checked = !$leave_messages_on;
        if (!$this->source->exists()) {
            $skip_existing = array_key_exists('skip_existing_on_create', $params)
                ? ((string) $params['skip_existing_on_create'] === '1')
                : true;
        } else {
            $skip_existing = ((string) ifset($params['skip_existing_on_create'], '1') === '1');
        }
        $load_existing_on_create_form_checked = !$skip_existing;
        $additional_settings_expanded = $delete_messages_on_server_form_checked || $load_existing_on_create_form_checked;

        $template = wa()->getAppPath('plugins/imap4/templates/source/settings/EmailSourceSettingsImap4Block.html');
        $html = $this->renderTemplate($template, array(
            'source' => $info,
            'imap_providers' => crmEmailSource::getMailProvidersForUi(),
            'imap_presets' => crmEmailSource::getMailProviderPresets('imap'),
            'imap_provider_domain_map' => crmEmailSource::getMailProviderDomainMap(),
            'imap_provider_default' => crmEmailSource::DEFAULT_MAIL_PROVIDER_ID,
            'login_differs_from_email' => crmEmailSourceSettingsPage::emailSourceLoginDiffersFromEmail($info),
            'delete_messages_on_server_form_checked' => $delete_messages_on_server_form_checked,
            'load_existing_on_create_form_checked' => $load_existing_on_create_form_checked,
            'additional_settings_expanded' => $additional_settings_expanded,
        ));

        waSystem::popActivePlugin();

        return $html;
    }

    protected function getMailProviderConnectionKind()
    {
        return 'imap';
    }

    protected function getMailProviderParamKey()
    {
        return 'imap_provider';
    }

    protected function workupSubmitData($data)
    {
        $data = parent::workupSubmitData($data);
        if (!isset($data['params']) || !is_array($data['params'])) {
            return $data;
        }
        if (array_key_exists('load_existing_on_create', $data['params'])) {
            $load = (string) $data['params']['load_existing_on_create'] === '1';
            $data['params']['skip_existing_on_create'] = $load ? '2' : '1';
            unset($data['params']['load_existing_on_create']);
        }
        if (array_key_exists('delete_messages_on_server', $data['params'])) {
            $delete = (string) $data['params']['delete_messages_on_server'] === '1';
            $data['params']['leave_messages_on_server'] = $delete ? '2' : '1';
            unset($data['params']['delete_messages_on_server']);
        }
        return $data;
    }

    /**
     * After successful save, set last_imap_uid baseline when skip-existing is on and UID tracking starts.
     *
     * @param array $data
     * @return array
     */
    public function processSubmit($data)
    {
        $result = parent::processSubmit($data);
        if ($result['status'] === 'ok' && $this->source instanceof crmImap4PluginEmailSource) {
            $this->source->applyBaselineAfterCreateIfNeeded();
            $result['response']['source'] = $this->source->getInfo();
        }
        return $result;
    }
}
