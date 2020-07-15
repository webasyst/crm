<?php

class crmSettingsNotificationsAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $this->view->assign(array(
            'notifications' => $this->getAllNotifications(),
            'transports' => crmNotification::getTransports(),
            'events' => crmNotification::getEventTypes(true),
            'root_path' => $this->getConfig()->getRootPath() . DIRECTORY_SEPARATOR
        ));
    }

    public function getAllNotifications()
    {
        $notifications = $this->getNotificationModel()->getAllOrdered('name', 'id');

        $company_ids = waUtils::getFieldValues($notifications, 'company_id');
        $company_ids = waUtils::toIntArray($company_ids);
        $company_ids = waUtils::dropNotPositive($company_ids);

        $companies = $this->getCompanies($company_ids);
        $this->joinNotificationsWithCompanies($notifications, $companies);

        return $notifications;
    }

    protected function getCompanies($company_ids)
    {
        if ($company_ids) {
            return $this->getCompanyModel()->getById($company_ids);
        } else {
            return array();
        }
    }

    protected function joinNotificationsWithCompanies(&$notifications, $companies)
    {
        foreach ($notifications as &$notification) {
            if (wa_is_int($notification['company_id']) && $notification['company_id'] > 0 && isset($companies[$notification['company_id']])) {
                $notification['company'] = $companies[$notification['company_id']];
            } else {
                $notification['company'] = null;
            }
        }
        unset($notification);
    }
}
