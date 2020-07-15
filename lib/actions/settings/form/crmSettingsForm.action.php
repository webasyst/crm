<?php

class crmSettingsFormAction extends crmSettingsViewAction
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        $captcha_settings_url = wa()->getAppUrl('webasyst') . 'webasyst/settings/captcha/';
        $this->view->assign(array(
            'forms' => $this->getFormModel()->getAllFormsForControllers(),
            'captcha_settings_url' => $captcha_settings_url,
        ));
    }
}
