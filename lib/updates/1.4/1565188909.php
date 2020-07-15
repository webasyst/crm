<?php

class crmMigrateOnesignalSettingsToFramework
{
    public function __construct()
    {
        if (!class_exists('waPushSubscribersModel')) {
            throw new waException('Framework is not updated or not ready');
        }
    }

    public function run()
    {
        $this->migrateAppSettings();
        $this->migrateContactSettings();
    }

    protected function migrateAppSettings()
    {
        $push_onesignal = wa()->getPush('onesignal');
        $onesignal_system_key = $push_onesignal->getSettings('api_token');

        $app_settings_model = new waAppSettingsModel();
        $onesignal_crm_key = $app_settings_model->get('crm', 'onesignal_api_key', null);
        if (empty($onesignal_crm_key) || !empty($onesignal_system_key)) {
            return;
        }

        $push_onesignal->saveSettings(array('api_token' => $onesignal_crm_key));
        $app_settings_model->set('webasyst', 'push_adapter', 'onesignal');
        $app_settings_model->del('crm', 'onesignal_api_key');
    }

    protected function migrateContactSettings()
    {
        $subscriber_model = new waPushSubscribersModel();
        if (!empty($subscriber_model->getAll())) {
            return;
        }

        $settings_model = new waContactSettingsModel();

        $fields = array(
            'app_id' => 'crm',
            'name'   => array('onesignal_app_id', 'onesignal_user_id'),
        );

        $rows = $settings_model->getByField($fields, true);

        if (empty($rows)) {
            return;
        }

        $subscribers = array();

        foreach ($rows as $row) {
            if ($row['name'] == 'onesignal_app_id') {
                $subscribers[$row['contact_id']]['api_app_id'] = $row['value'];
            } elseif ($row['name'] == 'onesignal_user_id') {
                $subscribers[$row['contact_id']]['api_user_id'] = $row['value'];
            }
        }

        $domain = waRequest::server('HTTP_HOST');
        if (empty($domain)) {
            $app_settings_model = new waAppSettingsModel();
            $url = $app_settings_model->get('webasyst', 'url', '#');
            $url = parse_url($url);
            $domain = ifempty($url, 'host', '#');
        }

        $rows = array();
        foreach ($subscribers as $contact_id => $subscriber) {
            $rows[] = array(
                'provider_id'     => 'onesignal',
                'domain'          => $domain,
                'create_datetime' => date("Y-m-d H:i:s"),
                'contact_id'      => $contact_id,
                'subscriber_data' => json_encode($subscriber),
            );
        }

        $subscriber_model->multipleInsert($rows);

        $settings_model->deleteByField($fields, true);
    }
}

if (class_exists('crmMigrateOnesignalSettingsToFramework')) {
    $migrate_class = new crmMigrateOnesignalSettingsToFramework();
    $migrate_class->run();
}

$_file_paths = array();
$_file_paths[] = wa()->getAppPath('lib/classes/crmOnesignalApi.class.php', 'crm');
$_file_paths[] = wa()->getAppPath('lib/actions/settings/pbx/crmSettingsPbxApi.actions.php', 'crm');
$_file_paths[] = wa()->getAppPath('templates/actions/settings/SettingsPbxApiDialog.html', 'crm');

foreach ($_file_paths as $_file_path) {
    if (file_exists($_file_path)) {
        try {
            waFiles::delete($_file_path);
        } catch (Exception $e) {
        }
    }
}

waAppConfig::clearAutoloadCache('crm');