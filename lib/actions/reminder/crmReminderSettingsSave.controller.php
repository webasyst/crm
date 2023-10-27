<?php

/**
 * Modify reminder settings.
 */
class crmReminderSettingsSaveController extends crmJsonController
{
    public function execute()
    {
        $data = $this->getData();

        $this->validate($data);

        $this->saveData($data);

        $this->startHandler();
    }

    public function startHandler()
    {
        wa('crm')->event('backend_reminders_settings_save');
    }

    protected function validate($data)
    {
        if (!isset($data['setting']) && !isset($data['recap'])) {
            throw new waException('Setting or recap not found');
        };
    }

    protected function saveData($data)
    {
        foreach ($data as $key => $value) {
            wa()->getUser()->setSettings('crm', "reminder_$key", $data[$key]);
        }
    }

    protected function getData()
    {
        $default_settings = array(
            'disable_assign' => '',
            'disable_done' => '',
            'setting' => '',
            'recap' => '',
            'daily' => '',
            'pop_up_disabled' => '',
            'pop_up_min' => ''
        );

        $settings = $this->getRequest()->post('settings');
        $reminder_settings = isset($settings['personal']['reminder']) && is_array($settings['personal']['reminder']) ? $settings['personal']['reminder'] : array();

        $data = array_merge($default_settings, $reminder_settings);

        if ($data['setting'] === 'groups') {
            $groups = (array) ifset($data, 'groups', []);
            $groups = waUtils::toIntArray($groups);
            $data['setting'] = join(",", $groups);
            unset($data['groups']);
        }

        /* Check pop-up data */

        // If the user enters these settings the first time - declare an key
        if(!array_key_exists("pop_up_disabled", $data)){
            $data['pop_up_disabled'] = 0;
        }

        // Minutes
        $data['pop_up_min'] = ltrim(round($data['pop_up_min']),'0'); // remove zeros at the beginning

        if (!wa_is_int($data['pop_up_min']) or $data['pop_up_min'] < 0) {
            $data['pop_up_min'] = 0;
        }
        // maximum - day (1440 minutes)
        if ($data['pop_up_min'] > 1440) {
            $data['pop_up_min'] = 1440;
        }

        foreach ($default_settings as $key => $item) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = 0;
            }
        }

        return $data;
    }
}
