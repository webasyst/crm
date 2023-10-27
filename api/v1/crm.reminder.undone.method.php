<?php

class crmReminderUndoneMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $reminder_id = (int) ifempty($_json, 'id', 0);
        if ($reminder_id === 0) {
            throw new waAPIException('required_param', 'Required parameter is missing: id', 400);
        } else if ($reminder_id < 1) {
            throw new waAPIException('not_found', 'Reminder not found', 404);
        }

        $reminder = $this->getReminderModel()->getById($reminder_id);
        if ($reminder === null) {
            throw new waAPIException('not_found', 'Reminder not found', 404);
        } else if (!$this->getCrmRights()->reminderEditable($reminder)) {
            throw new waAPIException('forbidden', 'Access denied', 403);
        } else if (!$reminder['complete_datetime']) {
            $this->http_status_code = 204;
            $this->response = null;
            return;
        }

        $this->getReminderModel()->updateById($reminder_id, ['complete_datetime' => null]);

        try {
            crmDeal::updateReminder($reminder['contact_id']);
        } catch (waException $e) {
        }

        $action = 'reminder_undone';
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        $log_model->add($action, ['reminder_id' => $reminder_id]);
        wa('crm');

        $this->http_status_code = 204;
        $this->response = null;
    }
}
