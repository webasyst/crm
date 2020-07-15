<?php

class crmSettingsNotificationsDeleteController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $id = $this->getId();
        $notification = crmNotification::factory($id);
        $notification->delete();
    }

    protected function getId()
    {
        return (int)$this->getParameter('id');
    }
}
