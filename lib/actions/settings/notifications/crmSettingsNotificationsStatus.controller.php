<?php

class crmSettingsNotificationsStatusController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

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
        $data['status'] = (int)(!waRequest::post('status'));
        $data['id'] = (int)waRequest::post('id');

        return $data;
    }

    protected function validate($data)
    {
        $errors = array();

        $required = array('id', 'status');

        foreach ($required as $field) {
            $value = (string)ifset($data[$field]);
            if (strlen($value) <= 0) {
                $errors['data[' . $field . ']'] = _w('This field is required');
            }
        }

        return $errors;
    }

    protected function saveData($data)
    {
        $notification_model = self::getNotificationModel();
        $notification_model->updateById($data['id'], ['status' => $data['status']]);
        
        return $notification_model->getNotification($data['id']) ?: [];
    }
}
