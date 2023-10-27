<?php

class crmSettingsPersonalAction extends crmSettingsViewAction
{
    public function execute()
    {
        $this->displayPersonalSettings();
    }

    protected function displayPersonalSettings()
    {
        $app_counter = wa()->getUser()->getSettings('crm', 'app_counter');
        if ($app_counter) {
            $app_counter = json_decode($app_counter, true);
        }
        if (!$app_counter || !is_array($app_counter)) {
            $app_counter = array(
                'new_messages' => 1,
                'new_deals' => 1,
                'overdue_reminders' => 1
            );
        }

        $this->view->assign(array(
            'personal_settings' => array(
                'app_counter' => $app_counter,
                'reminder_settings_html' => crmHelper::renderViewAction(new crmReminderSettingsAction(array(
                    'is_dialog' => false
                )))
            ),
        ));
    }

}
