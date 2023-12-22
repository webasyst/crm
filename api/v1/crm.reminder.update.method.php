<?php

class crmReminderUpdateMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_PUT;
    private $reminder_types = ['OTHER', 'MEETING', 'CALL', 'MESSAGE'];

    public function execute()
    {
        $reminder_id = (int) $this->get('id', true);
        $_json = $this->readBodyAsJson();
        $user_id = (int) ifempty($_json, 'user_id', 0);
        $content = (string) ifempty($_json, 'content', '');
        $content = trim($content);
        $type = (string) ifempty($_json, 'type', reset($this->reminder_types));
        $due_date = (string) ifempty($_json, 'due_date', '');
        $due_datetime = (string) ifempty($_json, 'due_datetime', '');
        $contact_id = (int) ifempty($_json, 'contact_id', 0);
        $deal_id = (int) ifempty($_json, 'deal_id', 0);

        $reminder = $this->validate($reminder_id, $user_id, $content, $deal_id, $contact_id, $type);
        $this->saveData($reminder, $user_id, $content, $deal_id, $contact_id, $type, $due_date, $due_datetime);

        $this->http_status_code = 204;
        $this->response = null;
    }

    private function validate($reminder_id, $user_id, $content, $deal_id, $contact_id, $type)
    {
        if (empty($user_id)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'user_id'), 400);
        }
        if ($user_id < 1) {
            throw new waAPIException('invalid_user', sprintf_wp('Invalid “%s” value.', 'user_id'), 400);
        }
        if ($reminder_id < 1) {
            throw new waAPIException('not_found', _w('Reminder not found.'), 404);
        }
        if (empty($content) && $type === 'OTHER') {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'content'), 400);
        }
        if (!in_array($type, $this->reminder_types)) {
            throw new waAPIException('invalid_param', _w('Invalid parameter type.'), 400);
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
        if ($deal_id) {
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
        } else if ($contact_id) {
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

    private function saveData($reminder, $user_id, $content, $deal_id, $contact_id, $type, $due_date, $due_datetime)
    {
        $reminder['update_datetime'] = date('Y-m-d H:i:s');
        $reminder['type'] = $type;
        $reminder['content'] = $content;
        $reminder['user_contact_id'] = $user_id;
        if ($deal_id || $contact_id) {
            $reminder['contact_id'] = ($deal_id ? $deal_id * -1 : $contact_id);
        } else {
            $reminder['contact_id'] = null;
        }

        if (empty($due_date) && empty($due_datetime)) {
            $dt = crmNaturalInput::matchDueDate($content);
            $reminder['content'] = $content;
            $reminder['due_date'] = ifempty($dt, 'due_date', null);
            $reminder['due_datetime'] = ifempty($dt, 'due_datetime', null);
        } elseif (empty($due_datetime)) {
            $reminder['due_date'] = date('Y-m-d', strtotime($due_date));
            $reminder['due_datetime'] = null;
        } else {
            $reminder['due_date'] = date('Y-m-d', strtotime($due_datetime));
            $reminder['due_datetime'] = date('Y-m-d H:i:s', strtotime($due_datetime));
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
