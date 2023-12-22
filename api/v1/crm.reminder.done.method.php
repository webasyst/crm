<?php

class crmReminderDoneMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $reminder_id = (int) ifempty($_json, 'id', 0);
        if ($reminder_id === 0) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        } else if ($reminder_id < 1) {
            throw new waAPIException('not_found', _w('Reminder not found.'), 404);
        }

        $reminder = $this->getReminderModel()->getById($reminder_id);
        if ($reminder === null) {
            throw new waAPIException('not_found', _w('Reminder not found.'), 404);
        } else if (!$this->getCrmRights()->reminderEditable($reminder)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } else if ($reminder['complete_datetime']) {
            $this->http_status_code = 204;
            $this->response = null;
            return;
        }

        $this->getReminderModel()->updateById($reminder_id, ['complete_datetime' => date('Y-m-d H:i:s')]);

        if (
            $reminder['user_contact_id'] != wa()->getUser()->getId()
            || $reminder['creator_contact_id'] != wa()->getUser()->getId()
        ) {
            $contact = new waContact($reminder['user_contact_id']);
            if (!$contact->getSettings('crm', 'reminder_disable_done')) {
                $to = [];
                if ($reminder['user_contact_id'] != wa()->getUser()->getId()) {
                    $to[$reminder['user_contact_id']] = 1;
                }
                if ($reminder['creator_contact_id'] != wa()->getUser()->getId()) {
                    $to[$reminder['creator_contact_id']] = 1;
                }
                $locale = $contact->getLocale();
                $old_locale = wa()->getLocale();
                if ($locale != $old_locale) {
                    wa()->setLocale($locale);
                }
                crmReminder::sendNotification($reminder, array_keys($to), 'reminder_done', _w('Done').': ');
                if ($locale != $old_locale) {
                    wa()->setLocale($old_locale);
                }
            }
        }

        try {
            crmDeal::updateReminder($reminder['contact_id']);
        } catch (waException $e) {
        }

        $action = 'reminder_done';
        $this->getLogModel()->log($action, $reminder['contact_id'], $reminder_id);
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
