<?php

class crmReminderUpdateMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_PUT;
    private static $reminder_types = [
        crmReminderModel::TYPE_CALL,
        crmReminderModel::TYPE_MEETING,
        crmReminderModel::TYPE_MESSAGE,
        crmReminderModel::TYPE_OTHER,
    ];

    public function execute()
    {
        $reminder_id = (int) $this->get('id', true);
        $_json = $this->readBodyAsJson();
        $user_id = (int) ifempty($_json, 'user_id', 0);
        $content = (string) ifempty($_json, 'content', '');
        $content = trim($content);
        $report = (string) ifempty($_json, 'report', '');
        $report = trim($report);
        $report = ifempty($report, null);
        $type = (string) ifempty($_json, 'type', crmReminderModel::TYPE_OTHER);
        $due_date = (string) ifempty($_json, 'due_date', '');
        $due_datetime = (string) ifempty($_json, 'due_datetime', '');
        $contact_id = (int) ifempty($_json, 'contact_id', 0);
        $deal_id = (int) ifempty($_json, 'deal_id', 0);

        $reminder = $this->validate($reminder_id, $user_id, $content, $report, $deal_id, $contact_id, $type);
        $this->saveData($reminder, $user_id, $content, $report, $deal_id, $contact_id, $type, $due_date, $due_datetime);

        $this->http_status_code = 204;
        $this->response = null;
    }

    private function validate($reminder_id, $user_id, $content, $report, $deal_id, $contact_id, $type)
    {
        if (empty($user_id)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'user_id'), 400);
        }
        if ($user_id < 1) {
            throw new waAPIException('invalid_user', sprintf_wp('Invalid “%s” value.', 'user_id'), 400);
        }
        if (empty($reminder_id) || $reminder_id < 1) {
            throw new waAPIException('not_found', _w('Reminder not found.'), 404);
        }
        if (empty($type) || !in_array($type, self::$reminder_types)) {
            throw new waAPIException('invalid_type', _w('Invalid reminder type specified.'), 400);
        }
        if (empty($content) && $type === crmReminderModel::TYPE_OTHER) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'content'), 400);
        }
        $user = $this->getContactModel()->getById($user_id);
        if (empty($user) || intval($user['is_user']) !== 1) {
            throw new waAPIException('invalid_user', sprintf_wp('Invalid “%s” value.', 'user_id'), 400);
        }
        $reminder = $this->getReminderModel()->getById($reminder_id);
        if ($reminder === null) {
            throw new waAPIException('not_found', _w('Reminder not found.'), 404);
        }
        if (!$this->getCrmRights()->reminderEditable($reminder)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        if (!empty($report) && empty($reminder['complete_datetime'])) {
            throw new waAPIException('invalid_param', _w('A report can be set only for completed reminders.'), 400);
        }
        if (!empty($deal_id)) {
            if ($deal_id < 0) {
                throw new waAPIException('invalid_deal', _w('Invalid deal specified.'), 400);
            }
            $deal = $this->getDealModel()->getById($deal_id);
            if ($deal === null) {
                throw new waAPIException('invalid_deal', _w('Invalid deal specified.'), 400);
            }
            if (!$this->getCrmRights()->deal($deal)) {
                throw new waAPIException('forbidden', _w('Access denied'), 403);
            }
        } else if (!empty($contact_id)) {
            if ($contact_id < 0) {
                throw new waAPIException('invalid_contact', _w('Invalid contact specified.'), 400);
            }
            $contact = $this->getContactModel()->getById($contact_id);
            if ($contact === null) {
                throw new waAPIException('invalid_contact', _w('Invalid contact specified.'), 400);
            }
            if (!$this->getCrmRights()->contact($contact)) {
                throw new waAPIException('forbidden', _w('Access denied'), 403);
            }
        }
        return $reminder;
    }

    private function saveData($reminder, $user_id, $content, $report, $deal_id, $contact_id, $type, $due_date, $due_datetime)
    {
        $reminder['update_datetime'] = date('Y-m-d H:i:s');
        $reminder['type'] = ifempty($type, crmReminderModel::TYPE_OTHER);
        $reminder['content'] = ifempty($content, '');
        $reminder['report'] = ifempty($report, null);
        $reminder['user_contact_id'] = ifempty($user_id, 0);
        $reminder['contact_id'] = !empty($deal_id) ? $deal_id * -1 : ifempty($contact_id, null);

        if (empty($due_date) && empty($due_datetime)) {
            $dt = crmNaturalInput::matchDueDate($content);
            $reminder['content'] = $content;
            $reminder['due_date'] = ifempty($dt, 'due_date', null);
            $reminder['due_datetime'] = ifempty($dt, 'due_datetime', null);
        } elseif (!empty($due_datetime)) {
            $reminder['due_date'] = date('Y-m-d', strtotime($due_datetime));
            $reminder['due_datetime'] = date('Y-m-d H:i:s', strtotime($due_datetime));
        } elseif (!empty($due_date)) {
            $reminder['due_date'] = date('Y-m-d', strtotime($due_date));
            $reminder['due_datetime'] = null;
        }

        $this->getReminderModel()->updateById($reminder['id'], $reminder);
        if (!empty($reminder['contact_id'])) {
            crmDeal::updateReminder($reminder['contact_id']);
        }

        $action = 'reminder_update';
        $this->getLogModel()->log($action, ifempty($reminder['contact_id']), $reminder['id']);
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        $log_model->add($action, ['reminder_id' => $reminder['id']]);
        wa('crm');
    }
}
