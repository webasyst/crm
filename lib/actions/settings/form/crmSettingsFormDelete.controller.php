<?php

class crmSettingsFormDeleteController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $id = (int)$this->getRequest()->request('id');
        $form_constructor = new crmFormConstructor($id);
        $form_constructor->deleteForm();
    }
}
