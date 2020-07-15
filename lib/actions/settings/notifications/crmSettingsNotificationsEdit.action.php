<?php

class crmSettingsNotificationsEditAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $phone = wa()->getUser()->get('phone');
        $email = wa()->getUser()->get('email');

        $notification = $this->getNotification();

        /**
         * @event backend_settings_notification_edit
         * @return array[string][string]string $return[%plugin_id%]['top'] html output
         * @return array[string][string]string $return[%plugin_id%]['bottom'] html output
         */
        $backend_settings_notification_edit = wa('crm')->event('backend_settings_notification_edit');

        $this->view->assign(array(
            'notification'                       => $this->getInfo($notification),
            'events'                             => crmNotification::getEventTypes(true),
            'companies'                          => $this->getCompanies(),
            'transports'                         => crmNotification::getTransports(),
            'notifications'                      => crmNotification::getNotificationVariants(),
            'site_app_url'                       => wa()->getAppUrl('site'),
            'recipients'                         => crmNotification::getRecipient(),
            'senders'                            => crmNotification::getSender(),
            'sms_senders'                        => crmNotification::getSMSSenders(),
            'user_phone'                         => array_shift($phone),
            'user_email'                         => array_shift($email),
            'backend_settings_notification_edit' => $backend_settings_notification_edit,
        ));
    }

    protected function getId()
    {
        return (int)$this->getParameter('id');
    }

    protected function getCompanies()
    {
        $cm = new crmCompanyModel();
        $companies = $cm->getAll('id');
        return $companies;
    }

    /**
     * @return crmNotification
     */
    protected function getNotification()
    {
        return crmNotification::factory($this->getId());
    }

    /**
     * @param crmNotification $notification
     * @return array
     * @throws waDbException
     * @throws waException
     */
    protected function getInfo($notification)
    {
        $info = $notification->getInfo();
        $info['is_invoice_event'] = $notification->isInvoiceEvent();
        $info['company'] = $notification->getCompany();
        return $info;
    }

}
