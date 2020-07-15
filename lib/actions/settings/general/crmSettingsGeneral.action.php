<?php

class crmSettingsGeneralAction extends crmSettingsViewAction
{
    public function execute()
    {
        $this->displayPersonalSettings();
        if (wa()->getUser()->isAdmin('crm')) {
            $this->displayCommonSettings();
        }
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

    protected function displayCommonSettings()
    {
        $config = wa('crm')->getConfig()->getOption();
        $captcha = ifset($config['factories']['captcha'][0]);
        if (!in_array($captcha, array('waCaptcha', 'waReCaptcha'))) {
            $captcha = 'waCaptcha';
        }
        $captcha_options = (array)ifset($config['factories']['captcha'][1]);

        $this->view->assign(array(
            'common_settings_has_access' => true,
            'common_settings' => array(
                'captcha'         => $captcha,
                'captcha_options' => $captcha_options,
                'one_name_field'  => wa()->getSetting('one_name_field', '', 'crm'),
                'name_order'      => $this->getNameOrder(),
            )
        ));
    }

    protected function getNameOrder()
    {
        if (waContactNameField::getNameOrder() == array('lastname', 'firstname', 'middlename')) {
            return 'lfm';
        } else {
            return 'fml';
        }
    }
}
