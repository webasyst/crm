<?php

class crmSettingsNotificationsEditSaveController extends crmJsonController
{
    public function execute()
    {
        $data = $this->getData();

        $errors = $this->validate($data);
        if ($errors) {
            $this->errors = $errors;
            return;
        }

        $notification = $this->saveData($data);

        $this->response = array(
            'notification' => $notification
        );
    }

    protected function getData()
    {
        $data = $this->getParameter('data', array(), waRequest::TYPE_ARRAY_TRIM);
        $data['status'] = !empty($data['status']) ? 1 : 0;
        $transport = ifset($data['transport']);

        $transports = crmNotification::getTransports();
        if (!isset($transports[$transport])) {
            $transport = key($transports);
        }

        $notification = crmNotification::factory(ifset($data['id']));
        if ($notification->getId() > 0) {
            $transport = $notification->getTransport();
        }

        $data['transport'] = $transport;
        if ($data['transport'] !== crmNotificationModel::TRANSPORT_EMAIL) {
            $data['subject'] = null;
        }
        if (!in_array($data['transport'], array(crmNotificationModel::TRANSPORT_EMAIL, crmNotificationModel::TRANSPORT_SMS))) {
            $data['body'] = null;
        }

        if (!empty($data['recipient'])) {
            $pattern = '/^[\+\d\(\)\ -]{4,14}\d$/';
            $phone = preg_match($pattern, $data['recipient']);

            if ($phone) {
                class_exists('waContactPhoneField');
                $formatter = new waContactPhoneFormatter();
                $data['recipient'] = $formatter->format($data['recipient']);
            }
        }

        if ($transport === crmNotificationModel::TRANSPORT_EMAIL) {
            $validator = new waEmailValidator();

            //Check email. If email not valid, check standard value
            if ($data['sender'] !== 'system' && !$validator->isValid($data['sender'])) {
                throw new waException('Email incorrect');
            }
        }

        //Check sender name. If name exist, then connect the name and email
        if (isset($data['sender_name'])) {
            $data['sender_name'] = preg_replace('/\|/', ';', $data['sender_name']);
            $data['sender'] = $data['sender'] . '|' . $data['sender_name'];
            unset($data['sender_name']);
        }

        if (isset($data['company_id']) && wa_is_int($data['company_id']) && $data['company_id'] > 0) {
            $data['company_id'] = (int)$data['company_id'];
        } else {
            $data['company_id'] = null;
        }

        if (isset($data['transport']) && !in_array($data['transport'], [crmNotificationModel::TRANSPORT_EMAIL, crmNotificationModel::TRANSPORT_SMS])) {
            unset($data['recipient']);
            unset($data['sender']);
            unset($data['subject']);
            unset($data['body']);
        }
        
        if (isset($data['transport']) && $data['transport'] !== crmNotificationModel::TRANSPORT_HTTP) {
            unset($data['url']);
            unset($data['method']);
            unset($data['headers']);
            unset($data['get']);
            unset($data['post']);
            unset($data['format']);
        }

        if (isset($data['transport']) && $data['transport'] !== crmNotificationModel::TRANSPORT_REMINDER) {
            unset($data['reminder_due_date']);
            unset($data['reminder_user_type']);
            unset($data['responsible_contact_id']);
            unset($data['reminder_type']);
            unset($data['reminder_content']);
        }

        return $data;
    }

    protected function validate($data)
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $errors = [];
        $required = ['event', 'name'];

        if (in_array($data['transport'], [crmNotificationModel::TRANSPORT_EMAIL, crmNotificationModel::TRANSPORT_SMS])) {
            $required[] = 'body';
            if ($data['transport'] == crmNotificationModel::TRANSPORT_EMAIL) {
                $required[] = 'subject';
            }
        } elseif ($data['transport'] == crmNotificationModel::TRANSPORT_HTTP) {
            $required[] = 'url';
        }

        foreach ($required as $field) {
            $value = (string)ifset($data[$field]);
            if (strlen($value) <= 0) {
                $errors['data['.$field.']'] = _w('This field is required');
            }
        }

        return $errors;
    }

    protected function saveData($data)
    {
        $notification = crmNotification::factory(ifset($data['id']));
        if ($notification->getId() > 0) {
            unset($data['transport']);
            if (!$notification->isInvoiceEvent()) {
                unset($data['company_id']);
            }
        }
        $notification->save($data);
        return $notification->getInfo();
    }
}
