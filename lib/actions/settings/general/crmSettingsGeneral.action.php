<?php

class crmSettingsGeneralAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (wa()->getUser()->isAdmin('crm')) {
            $this->displayCommonSettings();
        }
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
