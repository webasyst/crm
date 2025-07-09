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

        // save push settings
        $push = null;
        if (isset($settings['push']) && is_array($settings['push'])) {
            $push = $settings['push'];
            $push = json_encode($push);
        }
        wa()->getUser()->setSettings('crm', 'push', $push);
        
        // save personal reminder settings
        $controller = new crmReminderSettingsSaveController();
        $controller->execute();
    }
}
