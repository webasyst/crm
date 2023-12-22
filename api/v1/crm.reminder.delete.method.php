<?php

class crmReminderDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $reminder_id = (int) $this->get('id', true);

        if ($reminder_id < 1) {
            throw new waAPIException('not_found', _w('Reminder not found.'), 404);
        }
        $reminder = $this->getReminderModel()->getById($reminder_id);
        if ($reminder === null || $reminder['complete_datetime']) {
            throw new waAPIException('not_found', _w('Reminder not found.'), 404);
        } else if (!$this->getCrmRights()->reminderEditable($reminder)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        $this->getReminderModel()->deleteById($reminder_id);
        crmDeal::updateReminder($reminder['contact_id']);

        $this->http_status_code = 204;
        $this->response = null;
    }
}
