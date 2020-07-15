<?php

class crmNotificationModel extends crmModel
{
    protected $table = 'crm_notification';

    const TRANSPORT_SMS = 'sms';
    const TRANSPORT_EMAIL = 'email';
    const RECIPIENT_CLIENT = 'client';
    const RECIPIENT_RESPONSIBLE = 'responsible';
    const RECIPIENT_OTHER = 'other';
    const SENDER_SYSTEM = 'system';
    const SENDER_SPECIFIED = 'specified';

    public function getNotification($id)
    {
        return $this->getById((int)$id);
    }

    public function getNotificationsByEvent($event, $only_enabled = true)
    {
        $field = array(
            'event' => (string)$event
        );
        if ($only_enabled) {
            $field['status'] = 1;
        }
        return $this->getByField($field, 'id');
    }

    public function delete($id)
    {
        return $this->deleteById($id);
    }
}
