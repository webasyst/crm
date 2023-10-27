<?php

class crmSettingsPersonalSaveController extends crmJsonController
{
    public function execute()
    {
        $this->savePersonalSettings();
    }

    protected function savePersonalSettings()
    {
        $settings = $this->getRequest()->post('settings');
        if (isset($settings['personal']) && is_array($settings['personal'])) {
            $settings = $settings['personal'];
        }

        // save app counter settings
        $app_counter = null;
        if (isset($settings['app_counter']) && is_array($settings['app_counter'])) {
            $app_counter = $settings['app_counter'];
            $app_counter = json_encode($app_counter);
        }
        wa()->getUser()->setSettings('crm', 'app_counter', $app_counter);

        // save personal reminder settings
        $controller = new crmReminderSettingsSaveController();
        $controller->execute();
    }
}
