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

        $groups = crmHelper::getAvailableGroups(null, true);
        $http_methods = [
            waNet::METHOD_GET => waNet::METHOD_GET, 
            waNet::METHOD_POST => waNet::METHOD_POST, 
            waNet::METHOD_PUT => waNet::METHOD_PUT, 
            waNet::METHOD_PATCH => waNet::METHOD_PATCH, 
            waNet::METHOD_DELETE => waNet::METHOD_DELETE,
        ];

        $this->view->assign(array(
            'notification'                       => $this->getInfo($notification),
            'events'                             => crmNotification::getEventTypes(true),
            'companies'                          => $this->getCompanies(),
            'funnels'                            => (new crmFunnelModel)->getAllFunnels(),
            'transports'                         => crmNotification::getTransports(),
            'notifications'                      => crmNotification::getNotificationVariants(),
            'site_app_url'                       => wa()->getAppUrl('site'),
            'recipients'                         => crmNotification::getRecipient(),
            'senders'                            => crmNotification::getSender(),
            'sms_senders'                        => crmNotification::getSMSSenders(),
            'user_phone'                         => array_shift($phone),
            'user_email'                         => array_shift($email),
            'user_name'                          => waContactNameField::formatName(wa()->getUser()),
            'user_photo'                         => wa()->getUser()->getPhoto(),
            'backend_settings_notification_edit' => $backend_settings_notification_edit,
            'reminder_types'                     => crmConfig::getReminderTypeUI2(),
            'groups'                             => ifempty($groups['backend'], []),
            'http_methods'                       => $http_methods,
            'params_placeholder'                 => 'email={$customer->get(\'email\', \'default\')}'."\n".'invoice={$invoice.number}',
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
        if (empty($info['responsible_group_id'])) {
            $info['responsible_group_id'] = !empty($info['responsible_contact_id']) && $info['responsible_contact_id'] > 0 ? 
                'personally' : 
                abs(ifset($info['responsible_contact_id'], 0));
        }
        if ($info['responsible_group_id'] == 'personally') {
            $contact = new waContact($info['responsible_contact_id']);
            $info['responsible_contact'] = [
                'id'        => $info['responsible_contact_id'],
                'name'      => waContactNameField::formatName($contact),
                'photo_url' => waContact::getPhotoUrl($contact['id'], $contact['photo'], 96),
            ];
        } else {
            $info['responsible_contact'] = [
                'id'    => '',
                'name'  => '',
                'photo_url' => '',
            ];
        }

        return $info;
    }

}
