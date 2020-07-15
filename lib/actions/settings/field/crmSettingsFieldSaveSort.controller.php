<?php

class crmSettingsFieldSaveSortController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $field_ids = $this->getRequest()->post('fields');
        if (!$field_ids) {
            return;
        }

        $constructor = new crmFieldConstructor();
        $constructor->saveFieldsOrder($field_ids);

        $this->response = array(
            'done' => true
        );
    }
}