<?php

class crmSettingsFieldAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $field_constructor = new crmFieldConstructor();

        $this->view->assign(array(
            'fields' => $field_constructor->getAllFields(),
            'locale' => $field_constructor->getLocale(),
            'other_locales' => $field_constructor->getOtherLocales(),
            'field_types' => $field_constructor->getFieldTypes(),
        ));
    }
}
