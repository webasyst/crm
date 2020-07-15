<?php

class crmSettingsGeneralSaveController extends crmJsonController
{
    public function execute()
    {
        $this->savePersonalSettings();
        if (wa()->getUser()->isAdmin('crm')) {
            $this->saveCommonSettings();
        }
    }

    protected function saveCommonSettings()
    {
        $data = $this->getData();
        $this->saveNameField($data);
        $this->saveNameOrder($data);
    }

    protected function saveNameField($data)
    {
        if (!isset($data['one_name_field'])) {
            return;
        }

        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('crm', 'one_name_field', $data['one_name_field'] ? '1' : null);
    }

    protected function saveNameOrder($data)
    {
        if (!isset($data['name_order'])) {
            return;
        }

        $field = waContactFields::get('name');
        if ($data['name_order'] == 'fml') {
            $order = array('firstname', 'middlename', 'lastname');
        } else {
            $order = array('lastname', 'firstname', 'middlename');
        }

        $field->setParameter('subfields_order', $order);
        waContactFields::updateField($field);
    }

    protected function getData()
    {
        $settings = $this->getRequest()->post('settings');
        if (isset($settings['common']) && is_array($settings['common'])) {
            return $settings['common'];
        } else {
            return array();
        }
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
