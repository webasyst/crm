<?php

class crmNotificationModel extends crmModel
{
    protected $table = 'crm_notification';

    const TRANSPORT_SMS = 'sms';
    const TRANSPORT_EMAIL = 'email';
    const TRANSPORT_HTTP = 'http';
    const TRANSPORT_REMINDER = 'reminder';
    const RECIPIENT_CLIENT = 'client';
    const RECIPIENT_RESPONSIBLE = 'responsible';
    const RECIPIENT_OTHER = 'other';
    const SENDER_SYSTEM = 'system';
    const SENDER_SPECIFIED = 'specified';

    public function getNotification($id)
    {
        if (empty($id)) {
            return [];
        }
        $notification = $this->getById((int)$id);
        if (empty($notification)) {
            return [];
        }
        $params = (new crmNotificationParamsModel)->get((int)$id) ?: [];
        $notification += $params;
        return $notification;
    }

    public function getNotificationsByEvent($event, $only_enabled = true)
    {
        $field = [ 'event' => (string)$event ];
        if ($only_enabled) {
            $field['status'] = 1;
        }
        $records = $this->getByField($field, 'id');
        $params = (new crmNotificationParamsModel)->get(array_keys($records));
        $records = array_map(function ($record) use ($params) {
            return array_merge($record, $params[$record['id']]);
        }, $records);
        return $records;
    }

    public function delete($id)
    {
        (new crmNotificationParamsModel)->delete($id);
        return $this->deleteById($id);
    }

    public function save($data)
    {
        $fields = $this->getMetadata();
        $gorizontal_data = array_intersect_key($data, $fields);
        $vertical_data = array_diff_key($data, $fields);
        $id = null;
        if (empty($gorizontal_data['id'])) {
            $id = $this->insert($data);
        } else {
            $id = $gorizontal_data['id'];
            $this->updateById($id, $gorizontal_data);
        }
        (new crmNotificationParamsModel)->set([$id], $vertical_data);
        return $id;
    }
}
